<?php

use Catenis\WP\Clue\React\Buzz\Browser;
use Catenis\WP\Clue\React\Socks\Client as SocksClient;
use Catenis\WP\Psr\Http\Message\ResponseInterface;
use Catenis\WP\React\EventLoop\Factory as LoopFactory;
use Catenis\WP\React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

$loop = LoopFactory::create();

// create a new SOCKS proxy client which connects to a SOCKS proxy server listening on localhost:1080
// not already running a SOCKS proxy server? Try LeProxy.org or this: `ssh -D 1080 localhost`
$proxy = new SocksClient('127.0.0.1:1080', new Connector($loop));

// create a Browser object that uses the SOCKS proxy client for connections
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
