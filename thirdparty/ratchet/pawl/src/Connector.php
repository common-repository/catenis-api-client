<?php
namespace Catenis\WP\Ratchet\Client;
use Catenis\WP\Ratchet\RFC6455\Handshake\ClientNegotiator;
use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\Socket\ConnectionInterface;
use Catenis\WP\React\Socket\ConnectorInterface;
use Catenis\WP\React\Promise\Deferred;
use Catenis\WP\React\Promise\RejectedPromise;
use Catenis\WP\Psr\Http\Message\RequestInterface;
use Catenis\WP\GuzzleHttp\Psr7 as gPsr;

class Connector {
    protected $_loop;
    protected $_connector;
    protected $_secureConnector;
    protected $_negotiator;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null) {
        if (null === $connector) {
            $connector = new \Catenis\WP\React\Socket\Connector($loop, [
                'timeout' => 20
            ]);
        }

        $this->_loop       = $loop;
        $this->_connector  = $connector;
        $this->_negotiator = new ClientNegotiator;
    }

    /**
     * @param string $url
     * @param array  $subProtocols
     * @param array  $headers
     * @return \Catenis\WP\React\Promise\PromiseInterface
     */
    public function __invoke($url, array $subProtocols = [], array $headers = []) {
        try {
            $request = $this->generateRequest($url, $subProtocols, $headers);
            $uri = $request->getUri();
        } catch (\Exception $e) {
            return new RejectedPromise($e);
        }
        $secure = 'wss' === substr($url, 0, 3);
        $connector = $this->_connector;

        $port = $uri->getPort() ?: ($secure ? 443 : 80);

        $scheme = $secure ? 'tls' : 'tcp';

        $uriString = $scheme . '://' . $uri->getHost() . ':' . $port;

        $connecting = $connector->connect($uriString);

        $futureWsConn = new Deferred(function ($_, $reject) use ($url, $connecting) {
            $reject(new \RuntimeException(
                'Connection to ' . $url . ' cancelled during handshake'
            ));

            // either close active connection or cancel pending connection attempt
            $connecting->then(function (ConnectionInterface $connection) {
                $connection->close();
            });
            $connecting->cancel();
        });

        $connecting->then(function(ConnectionInterface $conn) use ($request, $subProtocols, $futureWsConn) {
            $earlyClose = function() use ($futureWsConn) {
                $futureWsConn->reject(new \RuntimeException('Connection closed before handshake'));
            };

            $stream = $conn;

            $stream->on('close', $earlyClose);
            $futureWsConn->promise()->then(function() use ($stream, $earlyClose) {
                $stream->removeListener('close', $earlyClose);
            });

            $buffer = '';
            $headerParser = function($data) use ($stream, &$headerParser, &$buffer, $futureWsConn, $request, $subProtocols) {
                $buffer .= $data;
                if (false == strpos($buffer, "\r\n\r\n")) {
                    return;
                }

                $stream->removeListener('data', $headerParser);

                $response = gPsr\parse_response($buffer);

                if (!$this->_negotiator->validateResponse($request, $response)) {
                    $futureWsConn->reject(new \DomainException(gPsr\str($response)));
                    $stream->close();

                    return;
                }

                $acceptedProtocol = $response->getHeader('Sec-WebSocket-Protocol');
                if ((count($subProtocols) > 0) && 1 !== count(array_intersect($subProtocols, $acceptedProtocol))) {
                    $futureWsConn->reject(new \DomainException('Server did not respond with an expected Sec-WebSocket-Protocol'));
                    $stream->close();

                    return;
                }

                $futureWsConn->resolve(new WebSocket($stream, $response, $request));

                $futureWsConn->promise()->then(function(WebSocket $conn) use ($stream) {
                    $stream->emit('data', [$conn->response->getBody(), $stream]);
                });
            };

            $stream->on('data', $headerParser);
            $stream->write(gPsr\str($request));
        }, array($futureWsConn, 'reject'));

        return $futureWsConn->promise();
    }

    /**
     * @param string $url
     * @param array  $subProtocols
     * @param array  $headers
     * @throws \InvalidArgumentException
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function generateRequest($url, array $subProtocols, array $headers) {
        $uri = gPsr\uri_for($url);

        $scheme = $uri->getScheme();

        if (!in_array($scheme, ['ws', 'wss'])) {
            throw new \InvalidArgumentException(sprintf('Cannot connect to invalid URL (%s)', $url));
        }

        $uri = $uri->withScheme('wss' === $scheme ? 'HTTPS' : 'HTTP');

        $headers += ['User-Agent' => 'Ratchet-Pawl/0.3'];

        $request = array_reduce(array_keys($headers), function(RequestInterface $request, $header) use ($headers) {
            return $request->withHeader($header, $headers[$header]);
        }, $this->_negotiator->generateRequest($uri));

        if (!$request->getHeader('Origin')) {
            $request = $request->withHeader('Origin', str_replace('ws', 'http', $scheme) . '://' . $uri->getHost());
        }

        if (count($subProtocols) > 0) {
            $protocols = implode(',', $subProtocols);
            if ($protocols != "") {
                $request = $request->withHeader('Sec-WebSocket-Protocol', $protocols);
            }
        }

        return $request;
    }
}
