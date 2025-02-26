<?php

use Catenis\WP\Clue\React\Block;
use Catenis\WP\React\Promise;
use Catenis\WP\React\Promise\Timer\TimeoutException;

class FunctionAwaitTest extends TestCase
{
    /**
     * @expectedException Exception
     * @expectedExceptionMessage test
     */
    public function testAwaitOneRejected()
    {
        $promise = $this->createPromiseRejected(new Exception('test'));

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Promise rejected with unexpected value of type bool
     */
    public function testAwaitOneRejectedWithFalseWillWrapInUnexpectedValueException()
    {
        $promise = Promise\reject(false);

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Promise rejected with unexpected value of type NULL
     */
    public function testAwaitOneRejectedWithNullWillWrapInUnexpectedValueException()
    {
        $promise = Promise\reject(null);

        Block\await($promise, $this->loop);
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitOneRejectedWithPhp7ErrorWillWrapInUnexpectedValueExceptionWithPrevious()
    {
        $promise = Promise\reject(new Error('Test'));

        try {
            Block\await($promise, $this->loop);
            $this->fail();
        } catch (UnexpectedValueException $e) {
            $this->assertEquals('Promise rejected with unexpected value of type Error', $e->getMessage());
            $this->assertInstanceOf('Throwable', $e->getPrevious());
            $this->assertEquals('Test', $e->getPrevious()->getMessage());
        }
    }

    public function testAwaitOneResolved()
    {
        $promise = $this->createPromiseResolved(2);

        $this->assertEquals(2, Block\await($promise, $this->loop));
    }

    public function testAwaitOneInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, Block\await($promise, $this->loop));
    }

    /**
     * @expectedException Catenis\WP\React\Promise\Timer\TimeoutException
     */
    public function testAwaitOncePendingWillThrowOnTimeout()
    {
        $promise = new Promise\Promise(function () { });

        Block\await($promise, $this->loop, 0.001);
    }

    public function testAwaitOncePendingWillThrowAndCallCancellerOnTimeout()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        try {
            Block\await($promise, $this->loop, 0.001);
        } catch (TimeoutException $expected) {
            $this->assertTrue($cancelled);
        }
    }

    public function testAwaitOnceWithTimeoutWillResolvemmediatelyAndCleanUpTimeout()
    {
        $promise = Promise\resolve(true);

        $time = microtime(true);
        Block\await($promise, $this->loop, 5.0);
        $this->loop->run();
        $time = microtime(true) - $time;

        $this->assertLessThan(0.1, $time);
    }

    public function testAwaitOneResolvesShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('Catenis\WP\React\Promise\When') && PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API with PHP 5.4+');
        }

        gc_collect_cycles();

        $promise = Promise\resolve(1);
        Block\await($promise, $this->loop);
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testAwaitOneRejectedShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('Catenis\WP\React\Promise\When') && PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API with PHP 5.4+');
        }

        gc_collect_cycles();

        $promise = Promise\reject(new RuntimeException());
        try {
            Block\await($promise, $this->loop);
        } catch (Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testAwaitOneRejectedWithTimeoutShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('Catenis\WP\React\Promise\When') && PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API with PHP 5.4+');
        }

        gc_collect_cycles();

        $promise = Promise\reject(new RuntimeException());
        try {
            Block\await($promise, $this->loop, 0.001);
        } catch (Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testAwaitNullValueShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('Catenis\WP\React\Promise\When') && PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API with PHP 5.4+');
        }

        gc_collect_cycles();

        $promise = Promise\reject(null);
        try {
            Block\await($promise, $this->loop);
        } catch (Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitPendingPromiseWithTimeoutAndCancellerShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('Catenis\WP\React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new \Catenis\WP\React\Promise\Promise(function () { }, function () {
            throw new RuntimeException();
        });
        try {
            Block\await($promise, $this->loop, 0.001);
        } catch (Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitPendingPromiseWithTimeoutAndWithoutCancellerShouldNotCreateAnyGarbageReferences()
    {
        gc_collect_cycles();

        $promise = new \Catenis\WP\React\Promise\Promise(function () { });
        try {
            Block\await($promise, $this->loop, 0.001);
        } catch (Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitPendingPromiseWithTimeoutAndNoOpCancellerShouldNotCreateAnyGarbageReferences()
    {
        gc_collect_cycles();

        $promise = new \Catenis\WP\React\Promise\Promise(function () { }, function () {
            // no-op
        });
        try {
            Block\await($promise, $this->loop, 0.001);
        } catch (Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
