<?php

namespace Catenis\WP\React\Dns\Query;

use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\Promise\Deferred;
use Catenis\WP\React\Promise\CancellablePromiseInterface;
use Catenis\WP\React\Promise\Timer;

final class TimeoutExecutor implements ExecutorInterface
{
    private $executor;
    private $loop;
    private $timeout;

    public function __construct(ExecutorInterface $executor, $timeout, LoopInterface $loop)
    {
        $this->executor = $executor;
        $this->loop = $loop;
        $this->timeout = $timeout;
    }

    public function query(Query $query)
    {
        return Timer\timeout($this->executor->query($query), $this->timeout, $this->loop)->then(null, function ($e) use ($query) {
            if ($e instanceof Timer\TimeoutException) {
                $e = new TimeoutException(sprintf("DNS query for %s timed out", $query->name), 0, $e);
            }
            throw $e;
        });
    }
}
