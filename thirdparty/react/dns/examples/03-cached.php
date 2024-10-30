<?php

use Catenis\WP\React\Dns\Config\Config;
use Catenis\WP\React\Dns\Resolver\Factory;

require __DIR__ . '/../vendor/autoload.php';

$loop = Catenis\WP\React\EventLoop\Factory::create();

$config = Config::loadSystemConfigBlocking();
$server = $config->nameservers ? reset($config->nameservers) : '8.8.8.8';

$factory = new Factory();
$resolver = $factory->createCached($server, $loop);

$name = isset($argv[1]) ? $argv[1] : 'www.google.com';

$resolver->resolve($name)->then(function ($ip) use ($name) {
    echo 'IP for ' . $name . ': ' . $ip . PHP_EOL;
}, 'printf');

$loop->addTimer(1.0, function() use ($name, $resolver) {
    $resolver->resolve($name)->then(function ($ip) use ($name) {
        echo 'IP for ' . $name . ': ' . $ip . PHP_EOL;
    }, 'printf');
});

$loop->addTimer(2.0, function() use ($name, $resolver) {
    $resolver->resolve($name)->then(function ($ip) use ($name) {
        echo 'IP for ' . $name . ': ' . $ip . PHP_EOL;
    }, 'printf');
});

$loop->addTimer(3.0, function() use ($name, $resolver) {
    $resolver->resolve($name)->then(function ($ip) use ($name) {
        echo 'IP for ' . $name . ': ' . $ip . PHP_EOL;
    }, 'printf');
});

$loop->run();
