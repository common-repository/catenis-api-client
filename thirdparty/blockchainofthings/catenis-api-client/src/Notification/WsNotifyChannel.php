<?php
/**
 * Created by claudio on 2018-12-05
 */

namespace Catenis\WP\Catenis\Notification;

use Exception;
use Catenis\WP\GuzzleHttp\Promise;
use Catenis\WP\GuzzleHttp\Promise\PromiseInterface;
use Catenis\WP\Ratchet\Client as WsClient;
use Catenis\WP\Ratchet\Client\WebSocket;
use Catenis\WP\Evenement\EventEmitterInterface;
use Catenis\WP\Evenement\EventEmitterTrait;
use Catenis\WP\Catenis\ApiClient;
use Catenis\WP\Catenis\Exception\WsNotifyChannelAlreadyOpenException;
use Catenis\WP\Catenis\Exception\OpenWsConnException;
use Catenis\WP\Catenis\Internal\ApiPackage;

class WsNotifyChannel extends ApiPackage implements EventEmitterInterface
{
    use EventEmitterTrait;

    private $ctnApiClient;
    private $eventName;
    private $ws;

    /**
     * WsNotifyChannel constructor.
     * @param ApiClient $ctnApiClient
     * @param string $eventName
     */
    public function __construct(ApiClient $ctnApiClient, $eventName)
    {
        $this->ctnApiClient = $ctnApiClient;
        $this->eventName = $eventName;
        $this->notifyChannelOpenMsg = $this->invokeMethod($this->ctnApiClient, 'getNotifyChannelOpenMsg');
    }

    /**
     * Open WebSocket notification channel
     * @return PromiseInterface
     */
    public function open()
    {
        return Promise\task(function () {
            if (isset($this->ws)) {
                // Notification channel already open. Throw exception
                throw new WsNotifyChannelAlreadyOpenException();
            }

            try {
                $wsNotifyReq = $this->invokeMethod($this->ctnApiClient, 'getWSNotifyRequest', $this->eventName);

                return WsClient\connect(
                    (string)$wsNotifyReq->getUri(),
                    [$this->invokeMethod($this->ctnApiClient, 'getNotifyWsSubprotocol')],
                    [],
                    $this->accessProperty($this->ctnApiClient, 'eventLoop')
                )->then(function (WebSocket $ws) use ($wsNotifyReq) {
                    // WebSocket connection successfully open. Save it
                    $this->ws = $ws;

                    // Wire up WebSocket connection event handlers
                    $ws->on('error', function ($error) {
                        // Emit error event
                        $this->emit('error', [$error]);

                        // And try to close WebSocket connection
                        if (isset($this->ws)) {
                            $this->ws->close(1100);
                        }
                    });

                    $ws->on('close', function ($code, $reason) {
                        // Emit close event
                        $this->emit('close', [$code, $reason]);

                        // Unset WebSocket connection object
                        $this->ws = null;
                    });

                    $ws->on('message', function ($message) {
                        if ($message == $this->notifyChannelOpenMsg) {
                            // Special notification channel open message. Emit open event indicating that
                            //  notification channel is successfully open and ready to send notifications
                            $this->emit('open');
                        } else {
                            // Emit notify event passing the parsed contents of the message
                            $this->emit('notify', [json_decode($message)]);
                        }
                    });

                    // Send authentication message
                    $authMsgData = [];
                    $timestampHeader = $this->invokeMethod($this->ctnApiClient, 'getTimestampHeader');

                    $authMsgData[strtolower($timestampHeader)] = $wsNotifyReq->getHeaderLine($timestampHeader);
                    $authMsgData['authorization'] = $wsNotifyReq->getHeaderLine('Authorization');

                    $ws->send(json_encode($authMsgData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }, function (Exception $ex) {
                    // Error opening WebSocket connection.
                    //  Just re-throws exception for now
                    throw new OpenWsConnException(null, $ex);
                });
            } catch (Exception $ex) {
                // Just re-throws exception for now
                throw new OpenWsConnException(null, $ex);
            }
        });
    }

    /**
     * Close WebSocket notification channel
     */
    public function close()
    {
        if (isset($this->ws)) {
            // Close the WebSocket connection
            $this->ws->close(1000);
        }
    }
}
