<?php

use Catenis\WP\Clue\React\Buzz\Browser;
use Catenis\WP\Clue\React\SshProxy\SshSocksConnector;
use Catenis\WP\Psr\Http\Message\ResponseInterface;
use Catenis\WP\React\EventLoop\Factory as LoopFactory;
use Catenis\WP\React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

$loop = LoopFactory::create();

// create a new SSH proxy client which connects to a SSH server listening on localhost:22
// You can pass any SSH server address as first argument, e.g. user@example.com
$proxy = new SshSocksConnector(isset($argv[1]) ? $argv[1] : 'localhost:22', $loop);

// create a Browser object that uses the SSH proxy client for connections
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
