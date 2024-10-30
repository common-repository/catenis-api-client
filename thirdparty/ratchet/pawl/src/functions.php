<?php
namespace Catenis\WP\Ratchet\Client;
use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\EventLoop\Factory as ReactFactory;
use Catenis\WP\React\EventLoop\Timer\Timer;

/**
 * @param string             $url
 * @param array              $subProtocols
 * @param array              $headers
 * @param LoopInterface|null $loop
 * @return \Catenis\WP\React\Promise\PromiseInterface<\Ratchet\Client\WebSocket>
 */
function connect($url, array $subProtocols = [], $headers = [], LoopInterface $loop = null) {
    $loop = $loop ?: ReactFactory::create();

    $connector = new Connector($loop);
    $connection = $connector($url, $subProtocols, $headers);

    $runHasBeenCalled = false;

    $loop->addTimer(Timer::MIN_INTERVAL, function () use (&$runHasBeenCalled) {
        $runHasBeenCalled = true;
    });

    register_shutdown_function(function() use ($loop, &$runHasBeenCalled) {
        if (!$runHasBeenCalled) {
            $loop->run();
        }
    });

    return $connection;
}
