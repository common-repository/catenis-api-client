<?php
/**
 * Created by claudio on 2018-12-21
 */

namespace Catenis\WP\Notification;

use Exception;
use DateTime;
use Catenis\WP\React\EventLoop\Factory;
use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\Stream\ReadableResourceStream;
use Catenis\WP\Catenis\ApiClient as CatenisApiClient;
use Catenis\WP\Catenis\Exception\WsNotifyChannelAlreadyOpenException;

class NotificationCtrl
{
    private $clientUID;
    private $eventLoop;
    private $commPipe;
    private $inputPipeStream;
    private $inputCtrlPipeStream;
    private $checkParentAliveTimer;
    private $commCommand;
    private $isParentAlive = false;
    private $ctnApiClient;
    private $wsNofifyChannels = [];

    private static $logLevels = [
        'ERROR' => 10,
        'WARN' => 20,
        'INFO' => 30,
        'DEBUG' => 40,
        'TRACE' => 50,
        'ALL' => 100
    ];

    public static function execProcess($clientUID)
    {
        return exec('php "' . __DIR__ . '/catenis_notify_proc.php" ' . $clientUID . ' > /dev/null &');
    }

    public static function logError($message)
    {
        self::logMessage('ERROR', $message);
    }

    public static function logWarn($message)
    {
        self::logMessage('WARN', $message);
    }

    public static function logInfo($message)
    {
        self::logMessage('INFO', $message);
    }

    public static function logDebug($message)
    {
        self::logMessage('DEBUG', $message);
    }

    public static function logTrace($message)
    {
        self::logMessage('TRACE', $message);
    }

    private static function logMessage($level, $message)
    {
        global $LOGGING, $LOG_LEVEL, $LOG;
        static $pid;

        if ($LOGGING && self::$logLevels[$level] <= self::$logLevels[$LOG_LEVEL]) {
            if (empty($pid)) {
                $pid = getmypid();
            }

            try {
                fwrite(
                    $LOG,
                    sprintf(
                        "%s - %-5s [%d]: %s\n",
                        (new DateTime())->format('Y-m-d\Th:i:s.u\Z'),
                        $level,
                        $pid,
                        $message
                    )
                );
                fflush($LOG);
            } catch (Exception $ex) {
            }
        }
    }

    private function setParentAlive()
    {
        $this->isParentAlive = true;
    }

    private function resetParentAlive()
    {
        $this->isParentAlive = false;
    }

    private function processParentDeath()
    {
        // Parent process (WordPress page using Catenis API client) has stopped responded.
        //  Just terminate process
        $this->terminate('Parent process stopped responding');
    }

    private function processCommands()
    {
        while ($this->commCommand->hasReceivedCommand()) {
            $command = $this->commCommand->getNextCommand();
            self::logDebug('Process command: ' . print_r($command, true));

            switch (($commandType = CommCommand::commandType($command))) {
                case CommCommand::OPEN_NOTIFY_CHANNEL_CMD:
                    $this->processOpenNotifyChannelCommand($command);
                    break;

                case CommCommand::CLOSE_NOTIFY_CHANNEL_CMD:
                    $this->processCloseNotifyChannelCommand($command);
                    break;

                default:
                    self::logDebug('Unknown communication command received: ' . $commandType);
            }
        }
    }

    private function processControlCommands()
    {
        while ($this->commCommand->hasReceivedControlCommand()) {
            $command = $this->commCommand->getNextControlCommand();
            self::logDebug('Process control command: ' . print_r($command, true));

            switch (($commandType = CommCommand::commandType($command))) {
                case CommCommand::INIT_CMD:
                    $this->processInitCommand($command);
                    break;

                case CommCommand::PING_CMD:
                    // Nothing to do
                    break;

                default:
                    self::logDebug('Unknown communication control command received: ' . $commandType);
            }
        }
    }

    private function processInitCommand($command)
    {
        $errorMsg = '';
        $terminate = false;

        if (!isset($this->ctnApiClient)) {
            try {
                // Convert client options from stdClass object to array
                $ctnClientOptions = json_decode(json_encode(
                    $command->data->ctnClientOptions,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ), true);
                $ctnClientOptions['eventLoop'] = $this->eventLoop;

                // Instantiate new Catenis API client
                $this->ctnApiClient = new CatenisApiClient(
                    $command->data->ctnClientCredentials->deviceId,
                    $command->data->ctnClientCredentials->apiAccessSecret,
                    $ctnClientOptions
                );
                self::logDebug('Process init command: Catenis API client successfully instantiated');
            } catch (Exception $ex) {
                $errorMsg = $ex->getMessage();
                $terminate = true;
            }
        } else {
            $errorMsg = 'Notification process already initialized';
        }

        try {
            // Send response
            if (!empty($errorMsg)) {
                $this->commCommand->sendInitResponseCommand(false, $errorMsg);

                if ($terminate) {
                    $this->terminate('Failure to initialize process', -5);
                }
            } else {
                $this->commCommand->sendInitResponseCommand(true);
            }
        } catch (Exception $ex) {
            // Error sending init response. Terminate process
            $this->terminate('Error sending init response: ' . $ex->getMessage(), -4);
        }

        // Start receiving (regular) commands
        $this->inputPipeStream = new ReadableResourceStream($this->commPipe->getInputPipe(), $this->eventLoop);
        $this->inputPipeStream->on('data', [$this, 'receiveCommand']);
    }

    private function processOpenNotifyChannelCommand($command)
    {
        // Make sure that it had been successfully initialized
        if (isset($this->ctnApiClient)) {
            $channelId = $command->data->channelId;
            $eventName = $command->data->eventName;
            $wsNotifyChannel = $this->ctnApiClient->createWsNotifyChannel($eventName);
            self::logDebug('Process open notification channel: WS notify channel object successfully created');

            $this->handleNotifyChannelEvents($channelId, $wsNotifyChannel);

            // Open notification channel
            $wsNotifyChannel->open()->then(function () use ($channelId, $wsNotifyChannel) {
                self::logDebug('Process open notification channel: open channel succeeded');
                // Underlying WebSocket connection successfully open. Save notification channel reference,
                //  and wait for 'open' event indicating that notification channel is successfully open
                $this->wsNofifyChannels[$channelId] = $wsNotifyChannel;
            }, function (Exception $ex) use ($channelId, $wsNotifyChannel) {
                self::logDebug('Process open notification channel: open channel failed');
                if ($ex instanceof WsNotifyChannelAlreadyOpenException) {
                    // Notification channel already opened. Make sure we have its reference saved...
                    $this->wsNofifyChannels[$channelId] = $wsNotifyChannel;
                } else {
                    // Error opening notification channel
                    self::logError('Error opening notification channel: ' . $ex->getMessage());
                    try {
                        // Send command notifying that there was an error while opening notification channel
                        $this->commCommand->sendNotifyChannelOpenedCommand($channelId, $ex->getMessage());
                    } catch (Exception $ex) {
                        self::logError('Error sending notification channel opened (failure) command: '
                            . $ex->getMessage());
                    }
                }
            });
        } else {
            // Notification process not yet initialized
            self::logError('Command received while process was not yet initialized: ' . print_r($command, true));
        }
    }

    private function processCloseNotifyChannelCommand($command)
    {
        // Make sure that it had been successfully initialized
        if (isset($this->ctnApiClient)) {
            $channelId = $command->data->channelId;

            if (isset($this->wsNofifyChannels[$channelId])) {
                $this->wsNofifyChannels[$channelId]->close();
            }
        } else {
            // Notification process not yet initialized
            self::logError('Command received while process was not yet initialized: ' . print_r($command, true));
        }
    }

    private function handleNotifyChannelEvents($channelId, $wsNotifyChannel)
    {
        // Wire up event handlers
        $wsNotifyChannel->on('error', function ($error) use ($channelId) {
            self::logTrace('Notification channel error');
            try {
                // Send command back to parent process notifying of notification channel error
                $this->commCommand->sendNotifyChannelErrorCommand($channelId, $error);
            } catch (Exception $ex) {
                self::logError('Error sending notification channel error command: ' . $ex->getMessage());
            }
        });

        $wsNotifyChannel->on('close', function ($code, $reason) use ($channelId) {
            self::logTrace('Notification channel close');
            // Remove notification channel reference
            unset($this->wsNofifyChannels[$channelId]);

            try {
                // Send command back to parent process notifying that notification channel has been closed
                $this->commCommand->sendNotifyChannelClosedCommand($channelId, $code, $reason);
            } catch (Exception $ex) {
                self::logError('Error sending notification channel closed command: ' . $ex->getMessage());
            }
        });

        $wsNotifyChannel->on('open', function () use ($channelId) {
            self::logTrace('Notification channel open');
            try {
                // Send command notifying that notification channel is successfully open
                $this->commCommand->sendNotifyChannelOpenedCommand($channelId);
            } catch (Exception $ex) {
                self::logError('Error sending notification channel opened command: ' . $ex->getMessage());
            }
        });

        $wsNotifyChannel->on('notify', function ($data) use ($channelId) {
            self::logTrace('Notification channel notify');
            try {
                // Send command back to parent process notifying of new notification event
                $this->commCommand->sendNotificationCommand($channelId, $data);
            } catch (Exception $ex) {
                self::logError('Error sending notification command: ' . $ex->getMessage());
            }
        });
    }

    /**
     * NotificationCtrl constructor.
     * @param string $clientUID
     * @param mixed - Event loop
     * @param int $keepAliveInterval - Time (in seconds) for continuously checking whether parent process is still alive
     * @throws Exception
     */
    public function __construct($clientUID, &$loop, $keepAliveInterval = 120)
    {
        $this->clientUID = $clientUID;

        try {
            $this->commPipe = new CommPipe(
                $clientUID,
                false,
                CommPipe::SEND_COMM_MODE | CommPipe::RECEIVE_COMM_MODE | CommPipe::SEND_COMM_CTRL_MODE
                    | CommPipe::RECEIVE_COMM_CTRL_MODE
            );
        } catch (Exception $ex) {
            throw new Exception('Error opening communication pipe: ' . $ex->getMessage());
        }

        try {
            // Create event loop
            $this->eventLoop = $loop = Factory::create();

            // Prepare to start receiving control commands only first
            $this->inputCtrlPipeStream = new ReadableResourceStream($this->commPipe->getInputCtrlPipe(), $loop);
            $this->commCommand = new CommCommand($this->commPipe);
        } catch (Exception $ex) {
            // Make sure that communication pipes are deleted
            $this->terminate();
            throw new Exception('Error setting up communication pipe: ' . $ex->getMessage());
        }

        // Start receiving control commands
        $this->inputCtrlPipeStream->on('data', [$this, 'receiveControlCommand']);

        // Start timer to check if parent process is still alive
        $this->checkParentAliveTimer = $loop->addPeriodicTimer($keepAliveInterval, [$this, 'checkParentAlive']);

        // Set up handler to process signals to end process
        pcntl_signal(SIGHUP, [$this, 'endProcSignalHandler']);
        pcntl_signal(SIGINT, [$this, 'endProcSignalHandler']);
        pcntl_signal(SIGQUIT, [$this, 'endProcSignalHandler']);
        pcntl_signal(SIGABRT, [$this, 'endProcSignalHandler']);
        pcntl_signal(SIGTERM, [$this, 'endProcSignalHandler']);
    }

    public function receiveCommand($data)
    {
        self::logTrace('Receive command handler: ' . print_r($data, true));
        // Command receive. Indicate that parent is alive
        $this->setParentAlive();
        $this->commCommand->parseCommands($data);
        $this->processCommands();
    }

    public function receiveControlCommand($data)
    {
        self::logTrace('Receive control command handler: ' . print_r($data, true));
        // Control command received. Indicate that parent is alive
        $this->setParentAlive();
        $this->commCommand->parseControlCommands($data);
        $this->processControlCommands();
    }

    public function checkParentAlive()
    {
        self::logTrace('Check parent alive handler');
        if (!$this->isParentAlive) {
            $this->processParentDeath();
        } else {
            $this->resetParentAlive();
        }
    }

    public function endProcSignalHandler($signo)
    {
        switch ($signo) {
            case SIGHUP:
                $sigName = 'SIGHUP';
                break;

            case SIGINT:
                $sigName = 'SIGINT';
                break;

            case SIGQUIT:
                $sigName = 'SIGQUIT';
                break;

            case SIGABRT:
                $sigName = 'SIGABRT';
                break;

            case SIGTERM:
                $sigName = 'SIGTERM';
                break;
        }

        // A signal to end the process has been received. So terminate the process appropriately
        $this->terminate("Process forced to end: $sigName ($signo) received", -6);
    }

    public function terminate($reason = '', $exitCode = 0)
    {
        global $EXIT_CODE;

        if ($this->eventLoop) {
            // Stop event loop
            if ($this->checkParentAliveTimer) {
                $this->eventLoop->cancelTimer($this->checkParentAliveTimer);
            }

            $this->eventLoop->stop();
        }

        // Delete communication pipes
        $this->commPipe->delete();

        if (!empty($reason)) {
            if ($exitCode !== 0) {
                self::logError('Terminated: ' . $reason);
            } else {
                self::logInfo('Terminated: ' . $reason);
            }
        }

        $EXIT_CODE = $exitCode;
    }
}
