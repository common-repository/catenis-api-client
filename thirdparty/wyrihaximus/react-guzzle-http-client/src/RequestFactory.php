<?php

/**
 * This file is part of ReactGuzzleRing.
 *
 ** (c) 2014 Cees-Jan Kiewiet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Catenis\WP\WyriHaximus\React\Guzzle\HttpClient;

use Catenis\WP\Clue\React\Buzz\Browser;
use Catenis\WP\Clue\React\Buzz\Io\Sender;
use Catenis\WP\Clue\React\HttpProxy\ProxyConnector as HttpProxyClient;
use Catenis\WP\Clue\React\Socks\Client as SocksProxyClient;
use Catenis\WP\Psr\Http\Message\RequestInterface;
use Catenis\WP\React\Dns\Resolver\Resolver;
use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\HttpClient\Client as HttpClient;
use Catenis\WP\React\Promise\Deferred;
use Catenis\WP\React\Socket\Connector;
use Catenis\WP\React\Socket\TimeoutConnector;
use Catenis\WP\React\Stream\ReadableStreamInterface;
use Catenis\WP\React\Stream\WritableResourceStream;
use ReflectionObject;

/**
 * Class RequestFactory
 *
 * @package Catenis\WP\WyriHaximus\React\Guzzle\HttpClient
 */
class RequestFactory
{
    /**
     *
     * @param RequestInterface $request
     * @param array $options
     * @param $resolver Resolver
     * @param HttpClient $httpClient
     * @param LoopInterface $loop
     * @return \Catenis\WP\React\Promise\Promise
     */
    public function create(
        RequestInterface $request,
        array $options,
        Resolver $resolver,
        HttpClient $httpClient,
        LoopInterface $loop
    ) {
        $options = $this->convertOptions($options);

        if (isset($options['delay'])) {
            $promise = \Catenis\WP\WyriHaximus\React\timedPromise($loop, $options['delay']);
        }
        if (!isset($promise)) {
            $promise = \Catenis\WP\WyriHaximus\React\futurePromise($loop);
        }
        
        return $promise->then(function () use (
            $request,
            $options,
            $httpClient,
            $loop
        ) {
            $sender = $this->createSender($options, $httpClient, $loop);
            return (new Browser($loop, $sender))
                ->withOptions($options)
                ->send($request)->then(function ($response) use ($loop, $options) {
                    if (!isset($options['sink'])) {
                        return \Catenis\WP\React\Promise\resolve($response);
                    }

                    return \Catenis\WP\React\Promise\resolve($this->sink($loop, $response, $options['sink']));
                });
        });
    }

    protected function sink($loop, $response, $target)
    {
        $deferred = new Deferred();
        $writeStream = fopen($target, 'w');
        $saveToStream = new WritableResourceStream($writeStream, $loop);

        $saveToStream->on(
            'end',
            function () use ($deferred, $response) {
                $deferred->resolve($response);
            }
        );

        $body = $response->getBody();
        if ($body instanceof ReadableStreamInterface) {
            $body->pipe($saveToStream);
        } else {
            $saveToStream->end($body->getContents());
        }

        return $deferred->promise();
    }

    /**
     * @param array $options
     * @param HttpClient $httpClient
     * @param LoopInterface $loop
     * @return Sender
     */
    protected function createSender(array $options, HttpClient $httpClient, LoopInterface $loop)
    {
        $connector = $this->getProperty($httpClient, 'connector');

        if (isset($options['proxy'])) {
            switch (parse_url($options['proxy'], PHP_URL_SCHEME)) {
                case 'http':
                    $connector = new Connector(
                        $loop,
                        [
                            'tcp' => new HttpProxyClient(
                                $options['proxy'],
                                $connector
                            ),
                        ]
                    );
                    break;
                case 'socks':
                case 'socks4':
                case 'socks4a':
                case 'socks5':
                    $connector = new Connector(
                        $loop,
                        [
                            'tcp' => new SocksProxyClient(
                                $options['proxy'],
                                $connector
                            ),
                        ]
                    );
                    break;
            }
        }

        if (isset($options['connect_timeout'])) {
            $connector = new TimeoutConnector($connector, $options['connect_timeout'], $loop);
        }

        return $connector;
    }

    /**
     * @param array $options
     * @return array
     */
    protected function convertOptions(array $options)
    {
        // provides backwards compatibility for Guzzle 3-5.
        if (isset($options['client'])) {
            $options = array_merge($options, $options['client']);
            unset($options['client']);
        }

        // provides for backwards compatibility for Guzzle 3-5
        if (isset($options['save_to'])) {
            $options['sink'] = $options['save_to'];
            unset($options['save_to']);
        }

        if (isset($options['delay'])) {
            $options['delay'] = $options['delay']/1000;
        }
        
        if (isset($options['allow_redirects'])) {
            $this->convertRedirectOption($options);
        }

        if (isset($options['decode_content'])) {
            $options['decodeContent'] = (bool)$options['decode_content'];
            unset($options['decode_content']);
        }

        return $options;
    }

    protected function convertRedirectOption(&$options)
    {
        $option = $options['allow_redirects'];
        unset($options['allow_redirects']);

        if (is_bool($option)) {
            $options['followRedirects'] = $option;
            return;
        }

        if (is_array($option)) {
            if (isset($option['max'])) {
                $options['maxRedirects'] = $option['max'];
            }
            $options['followRedirects'] = true;
            return;
        }
    }

    /**
     * @param object $object
     * @param string $desiredProperty
     * @return mixed
     */
    protected function getProperty($object, $desiredProperty)
    {
        $reflection = new ReflectionObject($object);
        $property = $reflection->getProperty($desiredProperty);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
