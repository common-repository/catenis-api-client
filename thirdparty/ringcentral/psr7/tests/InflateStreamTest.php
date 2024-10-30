<?php
namespace Catenis\WP\RingCentral\Tests\Psr7;

use Catenis\WP\RingCentral\Psr7;
use Catenis\WP\RingCentral\Psr7\InflateStream;

function php53_gzencode($data)
{
    return gzdeflate($data);
}

class InflateStreamtest extends \PHPUnit_Framework_TestCase
{
    public function testInflatesStreams()
    {
        $content = gzencode('test');
        $a = Psr7\stream_for($content);
        $b = new InflateStream($a);
        $this->assertEquals('test', (string) $b);
    }
}
