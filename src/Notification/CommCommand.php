<?php
/**
 * Created by claudio on 2018-12-22
 */

namespace Catenis\WP\Notification;

use stdClass;
use Exception;
use Catenis\WP\Evenement\EventEmitterInterface;
use Catenis\WP\Evenement\EventEmitterTrait;

class CommCommand implements EventEmitterInterface
{
    use EventEmitterTrait;

    // Control commands
    const INIT_CMD = 'init';
    const PING_CMD = 'ping';
    const INIT_RESPONSE_CMD = 'init_response';

    // Regular (notification) commands
    const OPEN_NOTIFY_CHANNEL_CMD = 'open_notify_channel';
    const CLOSE_NOTIFY_CHANNEL_CMD = 'close_notify_channel';
    const NOTIFICATION_CMD = 'notification';
    const NOTIFY_CHANNEL_OPENED_CMD = 'notify_channel_opened';
    const NOTIFY_CHANNEL_ERROR_CMD = 'notify_channel_error';
    const NOTIFY_CHANNEL_CLOSED_CMD = 'notify_channel_closed';

    private static $commandSeparator = '|';

    private $commPipe;
    private $receivedCommands;
    private $receivedCtrlCommands;

    /**
     * @param $command
     * @param mixed|null $data
     * @throws Exception
     */
    private function sendCommand($command, $data = null)
    {
        $cmdObj = new stdClass();

        $cmdObj->cmd = $command;

        if (!empty($data)) {
            $cmdObj->data = $data;
        }

        $this->commPipe->send(self::$commandSeparator . json_encode(
            $cmdObj,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    /**
     * @param $command
     * @param mixed|null $data
     * @throws Exception
     */
    private function sendControlCommand($command, $data = null)
    {
        $cmdObj = new stdClass();

        $cmdObj->cmd = $command;

        if (!empty($data)) {
            $cmdObj->data = $data;
        }

        $this->commPipe->sendControl(self::$commandSeparator . json_encode(
            $cmdObj,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    public static function commandType(stdClass $command)
    {
        return !empty($command->cmd) && is_string($command->cmd) ? $command->cmd : '';
    }

    /**
     * CommCommand constructor.
     * @param CommPipe $commPipe
     */
    public function __construct(CommPipe $commPipe)
    {
        $this->commPipe = $commPipe;
        $this->receivedCommands = [];
        $this->receivedCtrlCommands = [];
    }

    /**
     * @param stdClass $ctnClientData
     * @throws Exception
     */
    public function sendInitCommand(stdClass $ctnClientData)
    {
        $this->sendControlCommand(self::INIT_CMD, $ctnClientData);
    }

    /**
     * @throws Exception
     */
    public function sendPingCommand()
    {
        $this->sendControlCommand(self::PING_CMD);
    }

    /**
     * @param bool $success
     * @param string|null $error
     * @throws Exception
     */
    public function sendInitResponseCommand($success = true, $error = null)
    {
        $cmdData = new stdClass();
        $cmdData->success = $success;

        if (!$success && !empty($error)) {
            $cmdData->error = $error;
        }

        $this->sendControlCommand(self::INIT_RESPONSE_CMD, $cmdData);
    }

    /**
     * @param string $channelId
     * @param string $eventName
     * @throws Exception
     */
    public function sendOpenNotifyChannelCommand($channelId, $eventName)
    {
        $this->sendCommand(self::OPEN_NOTIFY_CHANNEL_CMD, [
            'channelId' => $channelId,
            'eventName' => $eventName
        ]);
    }

    /**
     * @param string $channelId
     * @throws Exception
     */
    public function sendCloseNotifyChannelCommand($channelId)
    {
        $this->sendCommand(self::CLOSE_NOTIFY_CHANNEL_CMD, [
            'channelId' => $channelId
        ]);
    }

    /**
     * @param string $channelId
     * @param stdClass $eventData
     * @throws Exception
     */
    public function sendNotificationCommand($channelId, stdClass $eventData)
    {
        $this->sendCommand(self::NOTIFICATION_CMD, [
            'channelId' => $channelId,
            'eventData' => $eventData
        ]);
    }

    /**
     * @param $channelId
     * @param bool $success
     * @param string|null $error
     * @throws Exception
     */
    public function sendNotifyChannelOpenedCommand($channelId, $error = null)
    {
        $cmdData = new stdClass();
        $cmdData->channelId = $channelId;

        if (isset($error)) {
            $cmdData->error = $error;
        }

        $this->sendCommand(self::NOTIFY_CHANNEL_OPENED_CMD, $cmdData);
    }

    /**
     * @param string $channelId
     * @param string $error
     * @throws Exception
     */
    public function sendNotifyChannelErrorCommand($channelId, $error)
    {
        $this->sendCommand(self::NOTIFY_CHANNEL_ERROR_CMD, [
            'channelId' => $channelId,
            'error' => $error
        ]);
    }

    /**
     * @param string $channelId
     * @param int $code
     * @param string $reason
     * @throws Exception
     */
    public function sendNotifyChannelClosedCommand($channelId, $code, $reason)
    {
        $this->sendCommand(self::NOTIFY_CHANNEL_CLOSED_CMD, [
            'channelId' => $channelId,
            'code' => $code,
            'reason' => $reason
        ]);
    }

    /**
     * @param string $data
     */
    public function parseCommands($data)
    {
        if (is_string($data) && !empty($data)) {
            $dataChunks = explode(self::$commandSeparator, $data);

            foreach ($dataChunks as $idx => $jsonCmd) {
                if (!empty($jsonCmd)) {
                    $command = json_decode($jsonCmd);

                    if (!empty($command) && $command instanceof stdClass && !empty($command->cmd)
                            && is_string($command->cmd)) {
                        switch ($command->cmd) {
                            case self::OPEN_NOTIFY_CHANNEL_CMD:
                            case self::CLOSE_NOTIFY_CHANNEL_CMD:
                            case self::NOTIFICATION_CMD:
                            case self::NOTIFY_CHANNEL_OPENED_CMD:
                            case self::NOTIFY_CHANNEL_ERROR_CMD:
                            case self::NOTIFY_CHANNEL_CLOSED_CMD:
                                // Store received command
                                $this->receivedCommands[] = $command;
                                break;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $data
     */
    public function parseControlCommands($data)
    {
        if (is_string($data) && !empty($data)) {
            $dataChunks = explode(self::$commandSeparator, $data);

            foreach ($dataChunks as $idx => $jsonCmd) {
                if (!empty($jsonCmd)) {
                    $command = json_decode($jsonCmd);

                    if (!empty($command) && $command instanceof stdClass && !empty($command->cmd)
                            && is_string($command->cmd)) {
                        switch ($command->cmd) {
                            case self::INIT_CMD:
                            case self::PING_CMD:
                            case self::INIT_RESPONSE_CMD:
                                // Store received command
                                $this->receivedCtrlCommands[] = $command;
                                break;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param int $timeoutSec - Seconds component of timeout for waiting on data to receive
     * @param int $timeoutUSec - Microseconds component of timeout for waiting on data to receive
     * @throws Exception
     */
    public function receive($timeoutSec = 0, $timeoutUSec = 0)
    {
        $this->parseCommands($this->commPipe->receive($timeoutSec, $timeoutUSec));

        return $this->hasReceivedCommand();
    }

    public function hasReceivedCommand()
    {
        return !empty($this->receivedCommands);
    }

    public function getNextCommand()
    {
        return array_shift($this->receivedCommands);
    }

    /**
     * @param int $timeoutSec - Seconds component of timeout for waiting on data to receive
     * @param int $timeoutUSec - Microseconds component of timeout for waiting on data to receive
     * @throws Exception
     */
    public function receiveControl($timeoutSec = 0, $timeoutUSec = 0)
    {
        $this->parseControlCommands($this->commPipe->receiveControl($timeoutSec, $timeoutUSec));

        return $this->hasReceivedControlCommand();
    }

    public function hasReceivedControlCommand()
    {
        return !empty($this->receivedCtrlCommands);
    }

    public function getNextControlCommand()
    {
        return array_shift($this->receivedCtrlCommands);
    }
}
