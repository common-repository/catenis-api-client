<?php

namespace Catenis\WP\React\Tests\Promise\Timer;

use Catenis\WP\React\Promise\Timer\TimeoutException;

class TimeoutExceptionTest extends TestCase
{
    public function testAccessTimeout()
    {
        $e = new TimeoutException(10);

        $this->assertEquals(10, $e->getTimeout());
    }
}
