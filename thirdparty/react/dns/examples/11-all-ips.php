<?php

use Catenis\WP\React\Dns\Config\Config;
use Catenis\WP\React\Dns\Resolver\Factory;
use Catenis\WP\React\Dns\Model\Message;

require __DIR__ . '/../vendor/autoload.php';

$loop = Catenis\WP\React\EventLoop\Factory::create();

$config = Config::loadSystemConfigBlocking();
$server = $config->nameservers ? reset($config->nameservers) : '8.8.8.8';

$factory = new Factory();
$resolver = $factory->create($server, $loop);

$name = isset($argv[1]) ? $argv[1] : 'www.google.com';

$resolver->resolveAll($name, Message::TYPE_A)->then(function (array $ips) use ($name) {
    echo 'IPv4 addresses for ' . $name . ': ' . implode(', ', $ips) . PHP_EOL;
}, function (Exception $e) use ($name) {
    echo 'No IPv4 addresses for ' . $name . ': ' . $e->getMessage() . PHP_EOL;
});

$resolver->resolveAll($name, Message::TYPE_AAAA)->then(function (array $ips) use ($name) {
    echo 'IPv6 addresses for ' . $name . ': ' . implode(', ', $ips) . PHP_EOL;
}, function (Exception $e) use ($name) {
    echo 'No IPv6 addresses for ' . $name . ': ' . $e->getMessage() . PHP_EOL;
});

$loop->run();
