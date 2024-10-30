<?php
/**
 * Created by claudio on 2019-04-02
 */

namespace Catenis\WP\Catenis\Tests;

use Exception;
use DateTime;
use Catenis\WP\PHPUnit\Framework\TestCase;
use Catenis\WP\React\EventLoop;
use Catenis\WP\Catenis\ApiClient;

if (empty($GLOBALS['__testEnv'])) {
    // Load default (development) test environment
    include_once __DIR__ . '/inc/DevTestEnv.php';
}

/**
 * Test cases for Catenis API Client for PHP
 */
class PHPClientTest extends TestCase
{
    protected static $testStartDate;
    protected static $testEnv;
    protected static $device1;
    protected static $accessKey1;
    protected static $device2;
    protected static $accessKey2;
    protected static $ctnClient1;
    protected static $ctnClient2;
    protected static $ctnClientAsync1;
    protected static $ctnClientAsync2;
    protected static $loop;

    public static function setUpBeforeClass(): void
    {
        self::$testStartDate = new DateTime();
        self::$testEnv = $GLOBALS['__testEnv'];
        self::$device1 = [
            'id' => self::$testEnv->device1->id
        ];
        self::$accessKey1 = self::$testEnv->device1->accessKey;
        self::$device2 = [
            'id' => self::$testEnv->device2->id
        ];
        self::$accessKey2 = self::$testEnv->device2->accessKey;

        echo "\nPHPClientTest test class\n";

        echo 'Enter device #1 ID: [' . self::$device1['id'] . '] ';
        $id = rtrim(fgets(STDIN));

        if (!empty($id)) {
            self::$device1['id'] = $id;
        }

        echo 'Enter device #1 API access key: ';
        $key = rtrim(fgets(STDIN));

        if (!empty($key)) {
            self::$accessKey1 = $key;
        }

        echo 'Enter device #2 ID: [' . self::$device2['id'] . '] ';
        $id = rtrim(fgets(STDIN));

        if (!empty($id)) {
            self::$device2['id'] = $id;
        }

        echo 'Enter device #2 API access key: ';
        $key = rtrim(fgets(STDIN));

        if (!empty($key)) {
            self::$accessKey2 = $key;
        }

        // Instantiate (synchronous) Catenis API clients
        self::$ctnClient1 = new ApiClient(self::$device1['id'], self::$accessKey1, [
            'host' => self::$testEnv->host,
            'environment' => self::$testEnv->environment,
            'secure' => self::$testEnv->secure
        ]);

        self::$ctnClient2 = new ApiClient(self::$device2['id'], self::$accessKey2, [
            'host' => self::$testEnv->host,
            'environment' => self::$testEnv->environment,
            'secure' => self::$testEnv->secure
        ]);

        // Instantiate event loop
        self::$loop = EventLoop\Factory::create();

        // Instantiate asynchronous Catenis API clients
        self::$ctnClientAsync1 = new ApiClient(self::$device1['id'], self::$accessKey1, [
            'host' => self::$testEnv->host,
            'environment' => self::$testEnv->environment,
            'secure' => self::$testEnv->secure,
            'eventLoop' => self::$loop
        ]);

        self::$ctnClientAsync2 = new ApiClient(self::$device2['id'], self::$accessKey2, [
            'host' => self::$testEnv->host,
            'environment' => self::$testEnv->environment,
            'secure' => self::$testEnv->secure,
            'eventLoop' => self::$loop
        ]);
    }

    /**
     * Test logging a message to the blockchain
     *
     * @medium
     * @return array Info about the logged message
     */
    public function testLogMessage()
    {
        $message = 'Test message #' . rand();

        $data = self::$ctnClient1->logMessage($message);

        $this->assertTrue(isset($data->messageId));

        return [
            'message' => $message,
            'messageId' => $data->messageId,
        ];
    }

    /**
     * Test logging a message in chunks to the blockchain
     *
     * @medium
     * @return array Info about the logged message
     */
    public function testLogMessageInChunks()
    {
        $message = [
            'Test message #' . rand() . ' (part #1)',
            "\nTest message #" . rand() . ' (part #2)'
        ];

        // Pass part #1 of message
        $data = self::$ctnClient1->logMessage([
            'data' => $message[0],
            'isFinal' => false
        ]);

        $this->assertTrue(isset($data->continuationToken));

        // Pass final part (#2) of message
        $data = self::$ctnClient1->logMessage([
            'data' => $message[1],
            'isFinal' => true,
            'continuationToken' => $data->continuationToken
        ]);

        $this->assertTrue(isset($data->messageId));

        return [
            'message' => implode('', $message),
            'messageId' => $data->messageId,
        ];
    }

    /**
     * Test asynchronously logging a message to the blockchain
     *
     * @medium
     * @return array Info about the logged message
     */
    public function testAsyncLogMessage()
    {
        $message = 'Test message #' . rand();

        $data = self::$ctnClient1->logMessage($message, [
            'async' => true
        ]);

        $this->assertTrue(isset($data->provisionalMessageId));

        return [
            'message' => $message,
            'ephemeralMessageId' => $data->provisionalMessageId,
        ];
    }

    /**
     * Test sending a message to another device in chunks
     *
     * @medium
     * @return void
     */
    public function testSendMessageInChunks()
    {
        $message = [
            'Test message #' . rand() . ' (part #1)',
            "\nTest message #" . rand() . ' (part #2)'
        ];

        // Pass part #1 of message
        $data = self::$ctnClient1->sendMessage([
            'data' => $message[0],
            'isFinal' => false
        ], self::$device2);

        $this->assertTrue(isset($data->continuationToken));

        // Pass final part (#2) of message
        $data = self::$ctnClient1->sendMessage([
            'data' => $message[1],
            'isFinal' => true,
            'continuationToken' => $data->continuationToken
        ], self::$device2);

        $this->assertTrue(isset($data->messageId));
    }

    /**
     * Test asynchronously sending a message to another device
     *
     * @medium
     * @return avoid
     */
    public function testAsyncSendMessage()
    {
        $message = 'Test message #' . rand();

        $data = self::$ctnClient1->sendMessage($message, self::$device2, [
            'async' => true
        ]);

        $this->assertTrue(isset($data->provisionalMessageId));
    }

    /**
     * Test reading a message that had been logged
     *
     * @depends testLogMessage
     * @medium
     * @param array $messageInfo Info about the logged message
     * @return void
     */
    public function testReadLoggedMessage(array $messageInfo)
    {
        $data = self::$ctnClient1->readMessage($messageInfo['messageId']);

        $this->assertEquals($messageInfo['message'], $data->msgData);
    }

    /**
     * Test reading a message (that had been logged) in chunks
     *
     * @depends testLogMessageInChunks
     * @medium
     * @param array $messageInfo Info about the logged message
     * @return void
     */
    public function testReadLoggedMessageInChunks(array $messageInfo)
    {
        $readMessage = [];

        // Read first part of message
        $data = self::$ctnClient1->readMessage($messageInfo['messageId'], [
            'encoding' => 'utf8',
            'dataChunkSize' => (int)(strlen($messageInfo['message']) / 2) + 1
        ]);

        $this->assertThat(
            $data,
            $this->logicalAnd(
                $this->objectHasAttribute('msgInfo'),
                $this->objectHasAttribute('msgData'),
                $this->objectHasAttribute('continuationToken')
            )
        );

        $readMessage[] = $data->msgData;

        // Read final part of message
        $data = self::$ctnClient1->readMessage($messageInfo['messageId'], [
            'encoding' => 'utf8',
            'continuationToken' => $data->continuationToken
        ]);


        $this->assertThat(
            $data,
            $this->logicalAnd(
                $this->objectHasAttribute('msgData'),
                $this->logicalNot($this->objectHasAttribute('continuationToken'))
            )
        );

        $readMessage[] = $data->msgData;

        $this->assertEquals($messageInfo['message'], implode('', $readMessage));
    }

    /**
     * Test retrieving message container
     *
     * @depends testLogMessage
     * @medium
     * @param array $messageInfo Info about the logged message
     * @return void
     */
    public function testRetrieveMessageContainer(array $messageInfo)
    {
        $data = self::$ctnClient1->retrieveMessageContainer($messageInfo['messageId']);

        $this->assertObjectHasAttribute('offChain', $data, 'Inconsistent data returned for message container');
    }

    /**
     * Test retrieving message progress
     *
     * @depends testAsyncLogMessage
     * @medium
     * @param array $messageInfo Info about the logged message
     * @return void
     */
    public function testRetrieveMessageProgress(array $messageInfo)
    {
        $data = null;

        do {
            $data = self::$ctnClient1->retrieveMessageProgress($messageInfo['ephemeralMessageId']);

            $this->assertThat(
                $data,
                $this->logicalAnd(
                    $this->objectHasAttribute('action'),
                    $this->objectHasAttribute('progress')
                )
            );
            $this->assertObjectHasAttribute('done', $data->progress);
        } while (!$data->progress->done);

        $this->assertObjectHasAttribute('success', $data->progress);

        if (!$data->progress->success) {
            throw new Exception($data->progress->error->message);
        }

        $this->assertObjectHasAttribute('result', $data);
        $this->assertObjectHasAttribute('messageId', $data->result);
    }

    /**
     * Test retrieving device identification info
     *
     * @medium
     * @return void
     */
    public function testRetrieveDeviceIdentificationInfo()
    {
        $data = self::$ctnClient1->retrieveDeviceIdentificationInfo(self::$device1['id']);

        $this->assertThat(
            $data,
            $this->logicalAnd(
                $this->objectHasAttribute('catenisNode'),
                $this->objectHasAttribute('client'),
                $this->objecthasAttribute('device')
            ),
            'Returned device identification info not well formed'
        );
    }

    /**
     * Test listing permission events
     *
     * @medium
     * @return void
     */
    public function testListPermissionEvents()
    {
        $data = self::$ctnClient1->listPermissionEvents();

        $this->assertObjectHasAttribute('receive-msg', $data, 'Returned list of permission events not well formed');
    }

    /**
     * Test setting permission for device #2 to receive messages sent from device #1
     *
     * @depends testListPermissionEvents
     * @depends testRetrieveDeviceIdentificationInfo
     * @medium
     * @return void
     */
    public function testSetPermissionToReceiveMessage()
    {
        $data = self::$ctnClient2->setPermissionRights(
            'receive-msg',
            [
                'device' => [
                    'allow' => self::$device1
                ]
            ]
        );

        $this->assertTrue($data->success === true);
    }

    /**
     * Test setting permission for device #2 to receive notification of new messages sent from device #1
     *
     * @depends testListPermissionEvents
     * @medium
     * @return void
     */
    public function testSetPermissionToReceiveNewMessageNotification()
    {
        $data = self::$ctnClient2->setPermissionRights(
            'receive-notify-new-msg',
            [
                'device' => [
                    'allow' => self::$device1
                ]
            ]
        );

        $this->assertTrue($data->success === true);
    }

    /**
     * Test listing notification events
     *
     * @medium
     * @return void
     */
    public function testListNotificationEvents()
    {
        $data = self::$ctnClient1->listNotificationEvents();

        $this->assertThat(
            $data,
            $this->logicalAnd(
                $this->objectHasAttribute('new-msg-received'),
                $this->objectHasAttribute('final-msg-progress')
            ),
            'Returned list of notification events not well formed'
        );
    }

    /**
     * Test checking effective permission right
     *
     * @depends testSetPermissionToReceiveMessage
     * @depends testSetPermissionToReceiveNewMessageNotification
     * @return void
     */
    public function testCheckEffectivePermissionRight()
    {
        $deviceId = self::$device1['id'];

        $data = self::$ctnClient2->checkEffectivePermissionRight('receive-msg', $deviceId);

        $this->assertTrue(
            isset($data->$deviceId) && $data->$deviceId == true,
            'receive-msg permission right not properly set'
        );

        $data = self::$ctnClient2->checkEffectivePermissionRight('receive-notify-new-msg', $deviceId);

        $this->assertTrue(
            isset($data->$deviceId) && $data->$deviceId == true,
            'receive-notify-new-msg permission right not properly set'
        );
    }

    /**
     * Test that WebSocket notification channel object emits 'open' event
     *
     * @depends testListNotificationEvents
     * @medium
     * @return void
     */
    public function testWSNotifyChannelOpenEvent()
    {
        $wsNtfyChannel = self::$ctnClientAsync1->createWsNotifyChannel('new-msg-received');

        $error = null;

        $wsNtfyChannel->on('error', function ($err) use (&$error) {
            // Record error and stop event loop
            $error = new Exception("'Error' event received instead of 'open' event. Returned error: $err");
            self::$loop->stop();
        });
        
        $wsNtfyChannel->on('close', function ($code, $reason) use (&$error) {
            // Record error and stop event loop
            $error = new Exception(
                "'Close' event received instead of 'open' event. Returned close info: [$code] $reason"
            );
            self::$loop->stop();
        });
        
        $wsNtfyChannel->on('notify', function ($retVal) use (&$error) {
            // Record error and stop event loop
            $error = new Exception(
                "'Notify' event received instead of 'open' event. Returned data: " . print_r($retVal, true)
            );
            self::$loop->stop();
        });
        
        $wsNtfyChannel->on('open', function () {
            // Just stop event loop
            self::$loop->stop();
        });
    
        $wsNtfyChannel->open()->then(
            function () {
                // WebSocket client successfully connected. Wait for open event
            },
            function (\Catenis\Exception\WsNotificationException $ex) use (&$error) {
                // Get error and stop event loop
                $error = $ex;
                self::$loop->stop();
            }
        );

        // Start event loop
        self::$loop->run();

        if (isset($error)) {
            throw $error;
        }

        $this->assertTrue(true);
    }

    /**
     * Test receiving notification of new message received
     *
     * @depends testListNotificationEvents
     * @depends testCheckEffectivePermissionRight
     * @depends testWSNotifyChannelOpenEvent
     * @large
     * @return array Info about the sent message
     */
    public function testReceiveNewMessageNotification()
    {
        $wsNtfyChannel = self::$ctnClientAsync2->createWsNotifyChannel('new-msg-received');

        $data = null;
        $error = null;
        $message = null;
        $messageId = null;

        $wsNtfyChannel->on('error', function ($err) use (&$error) {
            // Get error and stop event loop
            $error = new Exception('Error in the underlying WebSocket connection: ' . $err);
            self::$loop->stop();
        });
        
        $wsNtfyChannel->on('close', function ($code, $reason) use (&$data, &$error) {
            if (is_null($data)) {
                // Get close reason and stop event loop
                $error = new Exception("WebSocket connection has been closed: [$code] $reason");
                self::$loop->stop();
            }
        });
        
        $wsNtfyChannel->on('open', function () use (&$message, &$messageId, &$error) {
            // WebSocket notification channel successfully open and ready to send notifications.
            //  Send message from device #1 to device #2
            $message = 'Test message #' . rand();

            try {
                // Save message and save returned message ID
                $messageId = self::$ctnClient1->sendMessage($message, self::$device2)->messageId;
            } catch (Exception $ex) {
                // Get error and stop event loop
                $error = $ex;
                self::$loop->stop();
            }
        });
        
        $wsNtfyChannel->on('notify', function ($retVal) use (&$data, &$wsNtfyChannel, &$messageId) {
            if ($retVal->messageId == $messageId) {
                // Notification (for the expected message) received.
                //  Get returned data, close notification channel, and stop event loop
                $data = $retVal;
                $wsNtfyChannel->close();
                self::$loop->stop();
            }
        });
    
        $wsNtfyChannel->open()->then(
            function () {
                // WebSocket client successfully connected. Wait for open event
            },
            function (\Catenis\Exception\WsNotificationException $ex) use (&$error) {
                // Get error and stop event loop
                $error = $ex;
                self::$loop->stop();
            }
        );

        // Start event loop
        self::$loop->run();

        // Process result
        if (!is_null($data)) {
            $this->assertEquals($data->messageId, $messageId);

            return [
                'message' => $message,
                'messageId' => $messageId
            ];
        } else {
            throw $error;
        }
    }

    /**
     * Test receiving notification of final message progress
     *
     * @depends testListNotificationEvents
     * @depends testWSNotifyChannelOpenEvent
     * @large
     * @return void
     */
    public function testFinalMessageProcessNotification()
    {
        $wsNtfyChannel = self::$ctnClientAsync1->createWsNotifyChannel('final-msg-progress');

        $data = null;
        $error = null;
        $message = null;
        $ephemeralMessageId = null;
        
        $wsNtfyChannel->on('error', function ($err) use (&$error) {
            // Get error and stop event loop
            $error = new Exception('Error in the underlying WebSocket connection: ' . $err);
            self::$loop->stop();
        });
        
        $wsNtfyChannel->on('close', function ($code, $reason) use (&$data, &$error) {
            if (is_null($data)) {
                // Get close reason and stop event loop
                $error = new Exception("WebSocket connection has been closed: [$code] $reason");
                self::$loop->stop();
            }
        });
        
        $wsNtfyChannel->on('open', function () use (&$message, &$ephemeralMessageId, &$error) {
            // WebSocket notification channel successfully open and ready to send notifications.
            //  Asynchronously log a message
            $message = 'Test message #' . rand();

            try {
                // Save returned provisional message ID
                $ephemeralMessageId = self::$ctnClient1->logMessage($message, [
                    'async' => true
                ])->provisionalMessageId;
            } catch (Exception $ex) {
                // Get error and stop event loop
                $error = $ex;
                self::$loop->stop();
            }
        });
        
        $wsNtfyChannel->on('notify', function ($retVal) use (&$data, &$wsNtfyChannel, &$ephemeralMessageId) {
            if ($retVal->ephemeralMessageId == $ephemeralMessageId) {
                // Notification (for the expected message) received.
                //  Get returned data, close notification channel, and stop event loop
                $data = $retVal;
                $wsNtfyChannel->close();
                self::$loop->stop();
            }
        });
    
        $wsNtfyChannel->open()->then(
            function () {
                // WebSocket client successfully connected. Wait for open event
            },
            function (\Catenis\Exception\WsNotificationException $ex) use (&$error) {
                // Get error and stop event loop
                $error = $ex;
                self::$loop->stop();
            }
        );

        // Start event loop
        self::$loop->run();

        // Process result
        if (!is_null($data)) {
            $this->assertThat(
                $data,
                $this->logicalAnd(
                    $this->objectHasAttribute('ephemeralMessageId'),
                    $this->objectHasAttribute('action'),
                    $this->objectHasAttribute('progress')
                )
            );
            $this->assertThat(
                $data->progress,
                $this->logicalAnd(
                    $this->objectHasAttribute('done'),
                    $this->objectHasAttribute('success')
                )
            );

            if (!$data->progress->success) {
                throw new Exception($data->progress->error->message);
            }

            $this->assertEquals($data->ephemeralMessageId, $ephemeralMessageId);

            $this->assertObjectHasAttribute('result', $data);
            $this->assertObjectHasAttribute('messageId', $data->result);
        } else {
            throw $error;
        }
    }

    /**
     * Test reading a message that had been sent
     *
     * @depends testReceiveNewMessageNotification
     * @large
     * @param array $messageInfo Info about the logged message
     * @return void
     */
    public function testReadSentMessage(array $messageInfo)
    {
        $data = self::$ctnClient2->readMessage($messageInfo['messageId']);

        $this->assertEquals($messageInfo['message'], $data->msgData);
    }

    /**
     * Test listing received messages
     *
     * @depends testReceiveNewMessageNotification
     * @large
     * @param array $messageInfo Info about the logged message
     * @return void
     */
    public function testListReceivedMessages(array $messageInfo)
    {
        $data = self::$ctnClient2->listMessages([
            'action' => 'send',
            'direction' => 'inbound',
            'fromDevices' => [
                self::$device1
            ],
            'startDate' => self::$testStartDate
        ]);

        $this->assertTrue($data->msgCount > 0, 'No received message could be found');

        // Look for the expected message
        $messageFound = false;

        foreach ($data->messages as $message) {
            if ($message->messageId == $messageInfo['messageId']) {
                $messageFound = true;
                break;
            }
        }

        $this->assertTrue($messageFound, 'Unable to find received message');
    }

    /**
     * Test issuing new asset
     *
     * @medium
     * @return array Info about the issued asset
     */
    public function testIssueAsset()
    {
        $assetName = 'Test asset #' . rand();

        $data = self::$ctnClient1->issueAsset([
            'name' => $assetName,
            'description' => 'Asset used for testing purpose',
            'canReissue' => true,
            'decimalPlaces' => 2
        ], 100.00);

        $this->assertTrue(isset($data->assetId));

        return [
            'assetName' => $assetName,
            'assetId' => $data->assetId
        ];
    }

    /**
     * Test reissuing additional quantity of an asset
     *
     * @depends testIssueAsset
     * @medium
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testReissueAsset(array $assetInfo)
    {
        $data = self::$ctnClient1->reissueAsset($assetInfo['assetId'], 100.00);

        $this->assertEquals(200.00, $data->totalExistentBalance, 'Unexpected reported total issued asset amount');
    }

    /**
     * Test transferring an amount of asset
     *
     * @depends testIssueAsset
     * @medium
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testTransferAsset(array $assetInfo)
    {
        $data = self::$ctnClient1->transferAsset($assetInfo['assetId'], 50.00, self::$device2);

        $this->assertEquals(150.00, $data->remainingBalance, 'Unexpected reported remaining asset amount');
    }

    /**
     * Test retrieving asset info
     *
     * @depends testIssueAsset
     * @medium
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testRetrieveAssetInfo(array $assetInfo)
    {
        $data = self::$ctnClient1->retrieveAssetInfo($assetInfo['assetId']);
     
        $this->assertThat(
            $data,
            $this->logicalAnd(
                $this->objectHasAttribute('name'),
                $this->objectHasAttribute('description'),
                $this->objectHasAttribute('canReissue'),
                $this->objectHasAttribute('decimalPlaces'),
                $this->objectHasAttribute('issuer'),
                $this->objectHasAttribute('totalExistentBalance')
            ),
            'Unexpected returned asset info'
        );
        $this->assertObjectHasAttribute('deviceId', $data->issuer, 'Unexpected returned asset info');
        $this->assertEquals($data->name, $assetInfo['assetName'], 'Unexpected returned asset info');
        $this->assertEquals($data->description, 'Asset used for testing purpose', 'Unexpected returned asset info');
        $this->assertEquals($data->canReissue, true, 'Unexpected returned asset info');
        $this->assertEquals($data->decimalPlaces, 2, 'Unexpected returned asset info');
        $this->assertEquals($data->issuer->deviceId, self::$device1['id'], 'Unexpected returned asset info');
        $this->assertEquals($data->totalExistentBalance, 200.00, 'Unexpected returned asset info');
    }

    /**
     * Test getting asset balance
     *
     * @depends testIssueAsset
     * @depends testTransferAsset
     * @medium
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testGetAssetBalance(array $assetInfo)
    {
        $data = self::$ctnClient1->getAssetBalance($assetInfo['assetId']);

        $this->assertEquals(150, $data->total, 'Unexpected reported asset balance');
    }

    /**
     * Test listing owned assets
     *
     * @depends testIssueAsset
     * @depends testTransferAsset
     * @medium
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testListOwnedAssets(array $assetInfo)
    {
        $data = self::$ctnClient1->listOwnedAssets();

        $this->assertTrue(count($data->ownedAssets) > 0, 'Invalid number of owned assets');

        $testAsset = null;

        foreach ($data->ownedAssets as $ownedAsset) {
            if ($ownedAsset->assetId == $assetInfo['assetId']) {
                $testAsset = $ownedAsset;
                break;
            }
        }

        $this->assertFalse(is_null($testAsset), 'Test asset not listed as one of owned assets');
        $this->assertEquals($testAsset->balance->total, 150, 'Unexpected balance of owned asset');
    }

    /**
     * Test listing issued assets
     *
     * @depends testIssueAsset
     * @large
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testListIssuedAssets(array $assetInfo)
    {
        $data = self::$ctnClient1->listIssuedAssets();

        $this->assertTrue(count($data->issuedAssets) > 0, 'Invalid number of issued assets');

        $testAsset = null;

        foreach ($data->issuedAssets as $issuedAsset) {
            if ($issuedAsset->assetId == $assetInfo['assetId']) {
                $testAsset = $issuedAsset;
                break;
            }
        }

        $this->assertFalse(is_null($testAsset), 'Test asset not listed as one of issued assets');
        $this->assertEquals($testAsset->totalExistentBalance, 200, 'Unexpected balance of issued asset');
    }

    /**
     * Test retrieving asset issuing history
     *
     * @depends testIssueAsset
     * @medium
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testRetrieveAssetIssuingHistory(array $assetInfo)
    {
        $data = self::$ctnClient1->retrieveAssetIssuanceHistory($assetInfo['assetId']);

        $this->assertTrue(count($data->issuanceEvents) == 2, 'Unexpected number of asset issuance events');

        foreach ($data->issuanceEvents as $issuanceEvent) {
            $this->assertThat(
                $issuanceEvent,
                $this->logicalAnd(
                    $this->objectHasAttribute('amount'),
                    $this->objectHasAttribute('holdingDevice'),
                    $this->objecthasAttribute('date')
                ),
                'Asset issuance entry not well formed'
            );
        }
    }

    /**
     * Test listing asset holders
     *
     * @depends testIssueAsset
     * @depends testTransferAsset
     * @medium
     * @param array $assetInfo Info about the issued asset
     * @return void
     */
    public function testListAssetHolders(array $assetInfo)
    {
        $data = self::$ctnClient1->listAssetHolders($assetInfo['assetId']);

        $this->assertTrue(count($data->assetHolders) == 2, 'Unexpected number of asset holders');

        foreach ($data->assetHolders as $assetHolder) {
            $this->assertThat(
                $assetHolder,
                $this->logicalAnd(
                    $this->objectHasAttribute('holder'),
                    $this->objecthasAttribute('balance')
                ),
                'Asset holder entry not well formed'
            );

            switch ($assetHolder->holder->deviceId) {
                case self::$device1['id']:
                    $this->assertTrue($assetHolder->balance->total == 150, 'Unexpected asset balance for device #1');
                    break;

                case self::$device2['id']:
                    $this->assertTrue($assetHolder->balance->total == 50, 'Unexpected asset balance for device #2');
                    break;

                default:
                    $this->assertTrue(false, 'Unexpected asset holder');
            }
        }
    }
}
