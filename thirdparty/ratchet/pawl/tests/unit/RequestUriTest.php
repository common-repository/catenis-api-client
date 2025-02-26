<?php
/**
 * Created by claudio on 2018-12-31
 */

use Catenis\WP\PHPUnit\Framework\TestCase;
use Catenis\WP\React\EventLoop\Factory;
use Catenis\WP\Ratchet\Client\Connector;
use Catenis\WP\Psr\Http\Message\RequestInterface;

class RequestUriTest extends TestCase {
    protected static function getPrivateClassMethod($className, $methodName) {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    function uriDataProvider() {
        return [
            ['ws://127.0.0.1/bla', 'http://127.0.0.1/bla'],
            ['wss://127.0.0.1/bla', 'https://127.0.0.1/bla'],
            ['ws://127.0.0.1:1234/bla', 'http://127.0.0.1:1234/bla'],
            ['wss://127.0.0.1:4321/bla', 'https://127.0.0.1:4321/bla']
        ];
    }

    /**
     * @dataProvider uriDataProvider
     */
    function testGeneratedRequestUri($uri, $expectedRequestUri) {
        $loop = Factory::create();

        $connector = new Connector($loop);

        $generateRequest = self::getPrivateClassMethod('\Catenis\WP\Ratchet\Client\Connector', 'generateRequest');
        $request = $generateRequest->invokeArgs($connector, [$uri, [], []]);

        $this->assertEquals((string)$request->getUri(), $expectedRequestUri);
    }
}