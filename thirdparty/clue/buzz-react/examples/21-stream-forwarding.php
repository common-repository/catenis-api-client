<?php

use Catenis\WP\Clue\React\Buzz\Browser;
use Catenis\WP\Psr\Http\Message\ResponseInterface;
use Catenis\WP\React\Stream\WritableResourceStream;
use Catenis\WP\RingCentral\Psr7;

$url = isset($argv[1]) ? $argv[1] : 'http://google.com/';

require __DIR__ . '/../vendor/autoload.php';

$loop = Catenis\WP\React\EventLoop\Factory::create();
$client = new Browser($loop);

$out = new WritableResourceStream(STDOUT, $loop);
$info = new WritableResourceStream(STDERR, $loop);

$info->write('Requesting ' . $url . '…' . PHP_EOL);

$client->withOptions(array('streaming' => true))->get($url)->then(function (ResponseInterface $response) use ($info, $out) {
    $info->write('Received' . PHP_EOL . Psr7\str($response));

    $response->getBody()->pipe($out);
}, 'printf');

$loop->run();
