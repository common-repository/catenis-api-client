<?php

use Catenis\WP\Clue\React\Buzz\Browser;
use Catenis\WP\Clue\React\HttpProxy\ProxyConnector as HttpConnectClient;
use Catenis\WP\Psr\Http\Message\ResponseInterface;
use Catenis\WP\React\EventLoop\Factory as LoopFactory;
use Catenis\WP\React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

$loop = LoopFactory::create();

// create a new HTTP CONNECT proxy client which connects to a HTTP CONNECT proxy server listening on localhost:8080
// not already running a HTTP CONNECT proxy server? Try LeProxy.org!
$proxy = new HttpConnectClient('127.0.0.1:8080', new Connector($loop));

// create a Browser object that uses the HTTP CONNECT proxy client for connections
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));
$browser = new Browser($loop, $connector);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('https://www.google.com/')->then(function (ResponseInterface $response) {
    echo Catenis\WP\RingCentral\Psr7\str($response);
}, 'printf');

$loop->run();
