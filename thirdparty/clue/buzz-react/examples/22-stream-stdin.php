<?php

use Catenis\WP\Clue\React\Buzz\Browser;
use Catenis\WP\Psr\Http\Message\ResponseInterface;
use Catenis\WP\React\Stream\ReadableResourceStream;
use Catenis\WP\RingCentral\Psr7;

$url = isset($argv[1]) ? $argv[1] : 'https://httpbin.org/post';

require __DIR__ . '/../vendor/autoload.php';

$loop = Catenis\WP\React\EventLoop\Factory::create();
$client = new Browser($loop);

$in = new ReadableResourceStream(STDIN, $loop);

echo 'Sending STDIN as POST to ' . $url . '…' . PHP_EOL;

$client->post($url, array(), $in)->then(function (ResponseInterface $response) {
    echo 'Received' . PHP_EOL . Psr7\str($response);
}, 'printf');

$loop->run();
