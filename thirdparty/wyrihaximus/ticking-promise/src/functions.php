<?php

namespace Catenis\WP\WyriHaximus\React;

use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\Promise\Deferred;

/**
 * Promise that resolves once future tick is called.
 *
 * @param LoopInterface $loop  ReactPHP event loop.
 * @param mixed         $value Value to return on resolve.
 *
 * @return \Catenis\WP\React\Promise\Promise
 */
function futurePromise(LoopInterface $loop, $value = null)
{
    $deferred = new Deferred();
    $loop->futureTick(function () use ($deferred, $value) {
        $deferred->resolve($value);
    });
    return $deferred->promise();
}

/**
 * Promise that resolves once next tick is called.
 *
 * @param LoopInterface $loop  ReactPHP event loop.
 * @param mixed         $value Value to return on resolve.
 *
 * @return \Catenis\WP\React\Promise\Promise
 *
 * @deprecated use futurePromise instead
 */
function nextPromise(LoopInterface $loop, $value = null)
{
    return futurePromise($loop, $value);
}

/**
 * Promise that resolves after $interval has passed.
 *
 * @param LoopInterface $loop     ReactPHP event loop.
 * @param integer       $interval The number of seconds to wait before execution.
 * @param mixed         $value    Value to return on resolve.
 *
 * @return \Catenis\WP\React\Promise\Promise
 */
function timedPromise(LoopInterface $loop, $interval, $value = null)
{
    $deferred = new Deferred();
    $loop->addTimer($interval, function () use ($deferred, $value) {
        $deferred->resolve($value);
    });
    return $deferred->promise();
}

/**
 * Promise that resolves once $check returns something other then false. Runs at periodic $interval.
 *
 * @param LoopInterface $loop     ReactPHP event loop.
 * @param integer       $interval The number of seconds between each interval to run $check.
 * @param callable      $check    Callable to run at the specified $interval.
 * @param mixed         $value    Value to pass into $check on tick.
 *
 * @return \Catenis\WP\React\Promise\Promise
 */
function tickingPromise(LoopInterface $loop, $interval, callable $check, $value = null)
{
    $deferred = new Deferred();
    $loop->addPeriodicTimer($interval, function ($timer) use ($deferred, $check, $value, $loop) {
        $deferred->progress(time());
        $result = $check($value);
        if ($result !== false) {
            $loop->cancelTimer($timer);
            $deferred->resolve($result);
        }
    });
    return $deferred->promise();
}

/**
 * Promise that resolves once $check returns something other then false. Runs at future tick interval.
 *
 * @param LoopInterface $loop       ReactPHP event loop.
 * @param callable      $check      Callable to run at tick.
 * @param mixed         $value      Value to pass into $check on tick.
 * @param integer       $iterations Number of iterations to call $check in one tick.
 *
 * @return \Catenis\WP\React\Promise\Promise
 */
function tickingFuturePromise(LoopInterface $loop, callable $check, $value = null, $iterations = 1)
{
    return TickingFuturePromise::create($loop, $check, $value, $iterations);
}

/**
 * Sandwich a $function call within two futureTicks.
 *
 * @param LoopInterface $loop     ReactPHP event loop.
 * @param mixed         $value    Value to pass into $function.
 * @param callable      $function Function to wrap.
 *
 * @return \Catenis\WP\React\Promise\Promise
 */
function futureFunctionPromise(LoopInterface $loop, $value, callable $function)
{
    return futurePromise($loop, $value)->then(function ($value) use ($loop, $function) {
        return futurePromise($loop, $function($value));
    });
}
