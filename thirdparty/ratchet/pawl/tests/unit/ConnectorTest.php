<?php

use Catenis\WP\PHPUnit\Framework\TestCase;
use Catenis\WP\Ratchet\Client\Connector;
use Catenis\WP\React\EventLoop\Factory;
use Catenis\WP\React\Promise\RejectedPromise;
use Catenis\WP\React\Promise\Promise;

class ConnectorTest extends TestCase
{
    public function uriDataProvider() {
        return [
            ['ws://127.0.0.1', 'tcp://127.0.0.1:80'],
            ['wss://127.0.0.1', 'tls://127.0.0.1:443'],
            ['ws://127.0.0.1:1234', 'tcp://127.0.0.1:1234'],
            ['wss://127.0.0.1:4321', 'tls://127.0.0.1:4321']
        ];
    }

    /**
     * @dataProvider uriDataProvider
     */
    public function testSecureConnectionUsesTlsScheme($uri, $expectedConnectorUri) {
        $loop = Factory::create();

        $connector = $this->getMock('Catenis\WP\React\Socket\ConnectorInterface');

        $connector->expects($this->once())
            ->method('connect')
            ->with($this->callback(function ($uri) use ($expectedConnectorUri) {
                return $uri === $expectedConnectorUri;
            }))
            // reject the promise so that we don't have to mock a connection here
            ->willReturn(new RejectedPromise(new Exception('')));

        $pawlConnector = new Connector($loop, $connector);

        $pawlConnector($uri);
    }

    public function testConnectorRejectsWhenUnderlyingSocketConnectorRejects()
    {
        $exception = new RuntimeException('Connection failed');

        $loop = $this->getMockBuilder('Catenis\WP\React\EventLoop\LoopInterface')->getMock();
        $connector = $this->getMockBuilder('Catenis\WP\React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn(\React\Promise\reject($exception));

        $pawlConnector = new Connector($loop, $connector);

        $promise = $pawlConnector('ws://localhost');

        $actual = null;
        $promise->then(null, function ($reason) use (&$actual) {
            $actual = $reason;
        });
        $this->assertSame($exception, $actual);
    }

    public function testCancelConnectorShouldCancelUnderlyingSocketConnectorWhenSocketConnectionIsPending()
    {
        $promise = new Promise(function () { }, function () use (&$cancelled) {
            ++$cancelled;
        });

        $loop = $this->getMockBuilder('Catenis\WP\React\EventLoop\LoopInterface')->getMock();
        $connector = $this->getMockBuilder('Catenis\WP\React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn($promise);

        $pawlConnector = new Connector($loop, $connector);

        $promise = $pawlConnector('ws://localhost');

        $this->assertNull($cancelled);
        $promise->cancel();
        $this->assertEquals(1, $cancelled);

        $message = null;
        $promise->then(null, function ($reason) use (&$message) {
            $message = $reason->getMessage();
        });
        $this->assertEquals('Connection to ws://localhost cancelled during handshake', $message);
    }

    public function testCancelConnectorShouldCloseUnderlyingSocketConnectionWhenHandshakeIsPending()
    {
        $connection = $this->getMockBuilder('Catenis\WP\React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('close');

        $loop = $this->getMockBuilder('Catenis\WP\React\EventLoop\LoopInterface')->getMock();
        $connector = $this->getMockBuilder('Catenis\WP\React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $pawlConnector = new Connector($loop, $connector);

        $promise = $pawlConnector('ws://localhost');

        $promise->cancel();

        $message = null;
        $promise->then(null, function ($reason) use (&$message) {
            $message = $reason->getMessage();
        });
        $this->assertEquals('Connection to ws://localhost cancelled during handshake', $message);
    }
}
