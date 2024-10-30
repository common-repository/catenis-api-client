<?php

require __DIR__ . '/../vendor/autoload.php';

if (DIRECTORY_SEPARATOR === '\\') {
    fwrite(STDERR, 'Non-blocking console I/O not supported on Windows' . PHP_EOL);
    exit(1);
}

if (!defined('ZLIB_ENCODING_GZIP')) {
    fwrite(STDERR, 'Requires PHP 5.4+ with ext-zlib enabled' . PHP_EOL);
    exit(1);
}

$loop = Catenis\WP\React\EventLoop\Factory::create();

$in = new Catenis\WP\React\Stream\ReadableResourceStream(STDIN, $loop);
$out = new Catenis\WP\React\Stream\WritableResourceStream(STDOUT, $loop);

$decompressor = new Catenis\WP\Clue\React\Zlib\Decompressor(ZLIB_ENCODING_GZIP);
$in->pipe($decompressor)->pipe($out);

$decompressor->on('error', function ($e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
});

$loop->run();
