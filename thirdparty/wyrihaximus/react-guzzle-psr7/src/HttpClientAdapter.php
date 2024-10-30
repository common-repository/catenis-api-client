<?php

namespace Catenis\WP\WyriHaximus\React\GuzzlePsr7;

use Catenis\WP\Clue\React\Buzz\Message\ResponseException;
use Catenis\WP\GuzzleHttp\Promise\Promise;
use Catenis\WP\Psr\Http\Message\RequestInterface;
use Catenis\WP\Psr\Http\Message\ResponseInterface;
use Catenis\WP\React\Dns\Resolver\Factory as DnsFactory;
use Catenis\WP\React\Dns\Resolver\Resolver as DnsResolver;
use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\EventLoop\Timer\TimerInterface;
use Catenis\WP\React\HttpClient\Client as HttpClient;
use Catenis\WP\React\HttpClient\Client;
use Catenis\WP\React\HttpClient\Factory as HttpClientFactory;
use Catenis\WP\React\Socket\Connector;
use Catenis\WP\WyriHaximus\React\Guzzle\HttpClient\RequestFactory;

class HttpClientAdapter
{
    /**
     * @var numeric
     */
    const QUEUE_TIMER_INTERVAL = 0.01;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var DnsResolver
     */
    protected $dnsResolver;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @param LoopInterface $loop
     * @param HttpClient $httpClient
     * @param DnsResolver $dnsResolver
     * @param RequestFactory $requestFactory
     */
    public function __construct(
        LoopInterface $loop,
        HttpClient $httpClient = null,
        DnsResolver $dnsResolver = null,
        RequestFactory $requestFactory = null
    ) {
        $this->loop = $loop;

        $this->setDnsResolver($dnsResolver);
        $this->setHttpClient($httpClient);
        $this->setRequestFactory($requestFactory);
    }

    /**
     * @param HttpClient $httpClient
     */
    public function setHttpClient(HttpClient $httpClient = null)
    {
        if (!($httpClient instanceof HttpClient)) {
            $this->setDnsResolver($this->dnsResolver);

            if (class_exists('Catenis\WP\React\HttpClient\Factory')) {
                $factory = new HttpClientFactory();
                $httpClient = $factory->create($this->loop, $this->dnsResolver);
            } else {
                $httpClient = new Client(
                    $this->loop,
                    new Connector(
                        $this->loop,
                        [
                            'dns' => $this->dnsResolver,
                        ]
                    )
                );
            }
        }

        $this->httpClient = $httpClient;
    }

    /**
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @param DnsResolver $dnsResolver
     */
    public function setDnsResolver(DnsResolver $dnsResolver = null)
    {
        if (!($dnsResolver instanceof DnsResolver)) {
            $dnsResolverFactory = new DnsFactory();
            $dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);
        }

        $this->dnsResolver = $dnsResolver;
    }

    /**
     * @return DnsResolver
     */
    public function getDnsResolver()
    {
        return $this->dnsResolver;
    }

    /**
     * @param RequestFactory $requestFactory
     */
    public function setRequestFactory(RequestFactory $requestFactory = null)
    {
        if (!($requestFactory instanceof RequestFactory)) {
            $requestFactory = new RequestFactory();
        }

        $this->requestFactory = $requestFactory;
    }

    /**
     * @return RequestFactory
     */
    public function getRequestFactory()
    {
        return $this->requestFactory;
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @return Promise
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $ready = false;
        $promise = new Promise(function () use (&$ready) {
            trigger_error(
                'Using Promise::wait with the ReactPHP handler is deprecated due to incompatibilities in the latest ' .
                'event loop versions. See ' .
                'https://github.com/WyriHaximus/react-guzzle-psr7/wiki/Promise-wait-alternatives for alternatives.',
                E_USER_DEPRECATED
            );
            do {
                $this->loop->stop();
                $this->loop->futureTick(function () {
                    $this->loop->stop();
                });
                $this->loop->run();
            } while (!$ready);
            $this->loop->futureTick(function () {
                $this->loop->stop();
                $this->loop->run();
            });
        });

        $this->requestFactory->create($request, $options, $this->dnsResolver, $this->httpClient, $this->loop)->
            then(
                function (ResponseInterface $response) use (&$ready, $promise) {
                    $ready = true;
                    $promise->resolve($response);

                    $this->invokeQueue();
                },
                function ($error) use (&$ready, $promise, $options) {
                    $ready = true;
                    if (isset($options['http_errors']) &&
                        $options['http_errors'] === false &&
                        $error instanceof ResponseException
                    ) {
                        $promise->resolve($error->getResponse());
                    } else {
                        $promise->reject($error);
                    }

                    $this->invokeQueue();
                }
            )
        ;

        /** @var TimerInterface $timer */
        $timer = $this->loop->addPeriodicTimer(static::QUEUE_TIMER_INTERVAL, function () use (&$ready, &$timer) {
            $this->invokeQueue();
            if ($ready) {
                $this->loop->cancelTimer($timer);
                self::keepInvokingQueueUntillItsDone($this->loop);
            }
        });

        return $promise;
    }

    protected function invokeQueue()
    {
        $this->loop->futureTick(function () {
            \Catenis\WP\GuzzleHttp\Promise\queue()->run();
        });
    }

    protected static function keepInvokingQueueUntillItsDone(LoopInterface $loop)
    {
        $timer = $loop->addPeriodicTimer(static::QUEUE_TIMER_INTERVAL, function () use (&$timer, $loop) {
            \Catenis\WP\GuzzleHttp\Promise\queue()->run();
            if (\GuzzleHttp\Promise\queue()->isEmpty()) {
                $loop->cancelTimer($timer);
            }
        });
    }
}
