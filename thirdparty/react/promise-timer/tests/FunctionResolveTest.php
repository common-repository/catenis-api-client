<?php

namespace Catenis\WP\React\Tests\Promise\Timer;

use Catenis\WP\React\Promise\Timer;

class FunctionResolveTest extends TestCase
{
    public function testPromiseIsPendingWithoutRunningLoop()
    {
        $promise = Timer\resolve(0.01, $this->loop);

        $this->expectPromisePending($promise);
    }

    public function testPromiseExpiredIsPendingWithoutRunningLoop()
    {
        $promise = Timer\resolve(-1, $this->loop);

        $this->expectPromisePending($promise);
    }

    public function testPromiseWillBeResolvedOnTimeout()
    {
        $promise = Timer\resolve(0.01, $this->loop);

        $this->loop->run();

        $this->expectPromiseResolved($promise);
    }

    public function testPromiseExpiredWillBeResolvedOnTimeout()
    {
        $promise = Timer\resolve(-1, $this->loop);

        $this->loop->run();

        $this->expectPromiseResolved($promise);
    }

    public function testWillStartLoopTimer()
    {
        $loop = $this->getMockBuilder('Catenis\WP\React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with($this->equalTo(0.01));

        Timer\resolve(0.01, $loop);
    }

    public function testCancellingPromiseWillCancelLoopTimer()
    {
        $loop = $this->getMockBuilder('Catenis\WP\React\EventLoop\LoopInterface')->getMock();

        $timer = $this->getMockBuilder(interface_exists('Catenis\WP\React\EventLoop\TimerInterface') ? 'Catenis\WP\React\EventLoop\TimerInterface' : 'Catenis\WP\React\EventLoop\Timer\TimerInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->will($this->returnValue($timer));

        $promise = Timer\resolve(0.01, $loop);

        $loop->expects($this->once())->method('cancelTimer')->with($this->equalTo($timer));

        $promise->cancel();
    }

    public function testCancellingPromiseWillRejectTimer()
    {
        $promise = Timer\resolve(0.01, $this->loop);

        $promise->cancel();

        $this->expectPromiseRejected($promise);
    }

    public function testWaitingForPromiseToResolveDoesNotLeaveGarbageCycles()
    {
        if (class_exists('Catenis\WP\React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = Timer\resolve(0.01, $this->loop);
        $this->loop->run();
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testCancellingPromiseDoesNotLeaveGarbageCycles()
    {
        if (class_exists('Catenis\WP\React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = Timer\resolve(0.01, $this->loop);
        $promise->cancel();
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
