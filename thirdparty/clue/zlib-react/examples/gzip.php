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

$compressor = new Catenis\WP\Clue\React\Zlib\Compressor(ZLIB_ENCODING_GZIP);
$in->pipe($compressor)->pipe($out);

$loop->run();
