<?php
use Catenis\WP\GuzzleHttp\Psr7\Uri;
use Catenis\WP\Ratchet\RFC6455\Handshake\ClientNegotiator;
use Catenis\WP\Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Catenis\WP\Ratchet\RFC6455\Messaging\FrameInterface;
use Catenis\WP\Ratchet\RFC6455\Messaging\MessageBuffer;
use Catenis\WP\Ratchet\RFC6455\Messaging\MessageInterface;
use Catenis\WP\React\Promise\Deferred;
use Catenis\WP\Ratchet\RFC6455\Messaging\Frame;
use Catenis\WP\React\Socket\ConnectionInterface;
use Catenis\WP\React\Socket\Connector;

require __DIR__ . '/../bootstrap.php';

define('AGENT', 'RatchetRFC/0.0.0');

$testServer = "127.0.0.1";

$loop = Catenis\WP\React\EventLoop\Factory::create();

$connector = new Connector($loop);

function echoStreamerFactory($conn)
{
    return new MessageBuffer(
        new CloseFrameChecker,
        function (MessageInterface $msg) use ($conn) {
            /** @var Frame $frame */
            foreach ($msg as $frame) {
                $frame->maskPayload();
            }
            $conn->write($msg->getContents());
        },
        function (FrameInterface $frame) use ($conn) {
            switch ($frame->getOpcode()) {
                case Frame::OP_PING:
                    return $conn->write((new Frame($frame->getPayload(), true, Frame::OP_PONG))->maskPayload()->getContents());
                    break;
                case Frame::OP_CLOSE:
                    return $conn->end((new Frame($frame->getPayload(), true, Frame::OP_CLOSE))->maskPayload()->getContents());
                    break;
            }
        },
        false
    );
}

function getTestCases() {
    global $testServer;
    global $connector;

    $deferred = new Deferred();

    $connector->connect($testServer . ':9001')->then(function (ConnectionInterface $connection) use ($deferred) {
        $cn = new ClientNegotiator();
        $cnRequest = $cn->generateRequest(new Uri('ws://127.0.0.1:9001/getCaseCount'));

        $rawResponse = "";
        $response = null;

        /** @var MessageBuffer $ms */
        $ms = null;

        $connection->on('data', function ($data) use ($connection, &$rawResponse, &$response, &$ms, $cn, $deferred, &$context, $cnRequest) {
            if ($response === null) {
                $rawResponse .= $data;
                $pos = strpos($rawResponse, "\r\n\r\n");
                if ($pos) {
                    $data = substr($rawResponse, $pos + 4);
                    $rawResponse = substr($rawResponse, 0, $pos + 4);
                    $response = \Catenis\WP\GuzzleHttp\Psr7\parse_response($rawResponse);

                    if (!$cn->validateResponse($cnRequest, $response)) {
                        $connection->end();
                        $deferred->reject();
                    } else {
                        $ms = new MessageBuffer(
                            new CloseFrameChecker,
                            function (MessageInterface $msg) use ($deferred, $connection) {
                                $deferred->resolve($msg->getPayload());
                                $connection->close();
                            },
                            null,
                            false
                        );
                    }
                }
            }

            // feed the message streamer
            if ($ms) {
                $ms->onData($data);
            }
        });

        $connection->write(\GuzzleHttp\Psr7\str($cnRequest));
    });

    return $deferred->promise();
}

function runTest($case)
{
    global $connector;
    global $testServer;

    $casePath = "/runCase?case={$case}&agent=" . AGENT;

    $deferred = new Deferred();

    $connector->connect($testServer . ':9001')->then(function (ConnectionInterface $connection) use ($deferred, $casePath, $case) {
        $cn = new ClientNegotiator();
        $cnRequest = $cn->generateRequest(new Uri('ws://127.0.0.1:9001' . $casePath));

        $rawResponse = "";
        $response = null;

        $ms = null;

        $connection->on('data', function ($data) use ($connection, &$rawResponse, &$response, &$ms, $cn, $deferred, &$context, $cnRequest) {
            if ($response === null) {
                $rawResponse .= $data;
                $pos = strpos($rawResponse, "\r\n\r\n");
                if ($pos) {
                    $data = substr($rawResponse, $pos + 4);
                    $rawResponse = substr($rawResponse, 0, $pos + 4);
                    $response = \Catenis\WP\GuzzleHttp\Psr7\parse_response($rawResponse);

                    if (!$cn->validateResponse($cnRequest, $response)) {
                        $connection->end();
                        $deferred->reject();
                    } else {
                        $ms = echoStreamerFactory($connection);
                    }
                }
            }

            // feed the message streamer
            if ($ms) {
                $ms->onData($data);
            }
        });

        $connection->on('close', function () use ($deferred) {
            $deferred->resolve();
        });

        $connection->write(\GuzzleHttp\Psr7\str($cnRequest));
    });

    return $deferred->promise();
}

function createReport() {
    global $connector;
    global $testServer;

    $deferred = new Deferred();

    $connector->connect($testServer . ':9001')->then(function (ConnectionInterface $connection) use ($deferred) {
        $reportPath = "/updateReports?agent=" . AGENT . "&shutdownOnComplete=true";
        $cn = new ClientNegotiator();
        $cnRequest = $cn->generateRequest(new Uri('ws://127.0.0.1:9001' . $reportPath));

        $rawResponse = "";
        $response = null;

        /** @var MessageBuffer $ms */
        $ms = null;

        $connection->on('data', function ($data) use ($connection, &$rawResponse, &$response, &$ms, $cn, $deferred, &$context, $cnRequest) {
            if ($response === null) {
                $rawResponse .= $data;
                $pos = strpos($rawResponse, "\r\n\r\n");
                if ($pos) {
                    $data = substr($rawResponse, $pos + 4);
                    $rawResponse = substr($rawResponse, 0, $pos + 4);
                    $response = \Catenis\WP\GuzzleHttp\Psr7\parse_response($rawResponse);

                    if (!$cn->validateResponse($cnRequest, $response)) {
                        $connection->end();
                        $deferred->reject();
                    } else {
                        $ms = new MessageBuffer(
                            new CloseFrameChecker,
                            function (MessageInterface $msg) use ($deferred, $stream) {
                                $deferred->resolve($msg->getPayload());
                                $stream->close();
                            },
                            null,
                            false
                        );
                    }
                }
            }

            // feed the message streamer
            if ($ms) {
                $ms->onData($data);
            }
        });

        $connection->write(\GuzzleHttp\Psr7\str($cnRequest));
    });

    return $deferred->promise();
}


$testPromises = [];

getTestCases()->then(function ($count) use ($loop) {
    $allDeferred = new Deferred();

    $runNextCase = function () use (&$i, &$runNextCase, $count, $allDeferred) {
        $i++;
        if ($i > $count) {
            $allDeferred->resolve();
            return;
        }
        runTest($i)->then($runNextCase);
    };

    $i = 0;
    $runNextCase();

    $allDeferred->promise()->then(function () {
        createReport();
    });
});

$loop->run();
