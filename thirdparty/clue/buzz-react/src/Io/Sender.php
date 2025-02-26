<?php

namespace Catenis\WP\Clue\React\Buzz\Io;

use Catenis\WP\Clue\React\Buzz\Message\MessageFactory;
use Catenis\WP\Psr\Http\Message\RequestInterface;
use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\HttpClient\Client as HttpClient;
use Catenis\WP\React\HttpClient\Response as ResponseStream;
use Catenis\WP\React\Promise\PromiseInterface;
use Catenis\WP\React\Promise\Deferred;
use Catenis\WP\React\Socket\ConnectorInterface;
use Catenis\WP\React\Stream\ReadableStreamInterface;

/**
 * [Internal] Sends requests and receives responses
 *
 * The `Sender` is responsible for passing the [`RequestInterface`](#requestinterface) objects to
 * the underlying [`HttpClient`](https://github.com/reactphp/http-client) library
 * and keeps track of its transmission and converts its reponses back to [`ResponseInterface`](#responseinterface) objects.
 *
 * It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage)
 * and the default [`Connector`](https://github.com/reactphp/socket-client) and [DNS `Resolver`](https://github.com/reactphp/dns).
 *
 * The `Sender` class mostly exists in order to abstract changes on the underlying
 * components away from this package in order to provide backwards and forwards
 * compatibility.
 *
 * @internal You SHOULD NOT rely on this API, it is subject to change without prior notice!
 * @see Browser
 */
class Sender
{
    /**
     * create a new default sender attached to the given event loop
     *
     * This method is used internally to create the "default sender".
     *
     * You may also use this method if you need custom DNS or connector
     * settings. You can use this method manually like this:
     *
     * ```php
     * $connector = new \Catenis\WP\React\Socket\Connector($loop);
     * $sender = \Catenis\WP\Clue\React\Buzz\Io\Sender::createFromLoop($loop, $connector);
     * ```
     *
     * @param LoopInterface $loop
     * @param ConnectorInterface|null $connector
     * @return self
     */
    public static function createFromLoop(LoopInterface $loop, ConnectorInterface $connector = null, MessageFactory $messageFactory)
    {
        return new self(new HttpClient($loop, $connector), $messageFactory);
    }

    private $http;
    private $messageFactory;

    /**
     * [internal] Instantiate Sender
     *
     * @param HttpClient $http
     * @internal
     */
    public function __construct(HttpClient $http, MessageFactory $messageFactory)
    {
        $this->http = $http;
        $this->messageFactory = $messageFactory;
    }

    /**
     *
     * @internal
     * @param RequestInterface $request
     * @param array $options Associative array containing the following options:
     *                       'decodeContent' => [bool]: whether response body
     *                       contents should be decoded (decompressed)
     * @return PromiseInterface Promise<ResponseInterface, Exception>
     */
    public function send(RequestInterface $request, array $options = array())
    {
        $body = $request->getBody();
        $size = $body->getSize();

        if ($size !== null && $size !== 0) {
            // automatically assign a "Content-Length" request header if the body size is known and non-empty
            $request = $request->withHeader('Content-Length', (string)$size);
        } elseif ($size === 0 && \in_array($request->getMethod(), array('POST', 'PUT', 'PATCH'))) {
            // only assign a "Content-Length: 0" request header if the body is expected for certain methods
            $request = $request->withHeader('Content-Length', '0');
        } elseif ($body instanceof ReadableStreamInterface && $body->isReadable() && !$request->hasHeader('Content-Length')) {
            // use "Transfer-Encoding: chunked" when this is a streaming body and body size is unknown
            $request = $request->withHeader('Transfer-Encoding', 'chunked');
        } else {
            // do not use chunked encoding if size is known or if this is an empty request body
            $size = 0;
        }

        $headers = array();
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $requestStream = $this->http->request($request->getMethod(), (string)$request->getUri(), $headers, $request->getProtocolVersion());

        $deferred = new Deferred(function ($_, $reject) use ($requestStream) {
            // close request stream if request is cancelled
            $reject(new \RuntimeException('Request cancelled'));
            $requestStream->close();
        });

        $requestStream->on('error', function($error) use ($deferred) {
            $deferred->reject($error);
        });

        $messageFactory = $this->messageFactory;
        $requestStream->on('response', function (ResponseStream $responseStream) use ($deferred, $messageFactory, &$options) {
            // apply response header values from response stream
            $deferred->resolve($messageFactory->response(
                $responseStream->getVersion(),
                $responseStream->getCode(),
                $responseStream->getReasonPhrase(),
                $responseStream->getHeaders(),
                $responseStream,
                $options
            ));
        });

        if ($body instanceof ReadableStreamInterface) {
            if ($body->isReadable()) {
                if ($size !== null) {
                    // length is known => just write to request
                    $body->pipe($requestStream);
                } else {
                    // length unknown => apply chunked transfer-encoding
                    // this should be moved somewhere else obviously
                    $body->on('data', function ($data) use ($requestStream) {
                        $requestStream->write(dechex(strlen($data)) . "\r\n" . $data . "\r\n");
                    });
                    $body->on('end', function() use ($requestStream) {
                        $requestStream->end("0\r\n\r\n");
                    });
                }
            } else {
                // stream is not readable => end request without body
                $requestStream->end();
            }
        } else {
            // body is fully buffered => write as one chunk
            $requestStream->end((string)$body);
        }

        return $deferred->promise();
    }
}
