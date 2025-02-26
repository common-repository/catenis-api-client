<?php

namespace Catenis\WP\React\Tests\Dns\Query;

use Catenis\WP\React\Tests\Dns\TestCase;
use Catenis\WP\React\Dns\Query\HostsFileExecutor;
use Catenis\WP\React\Dns\Query\Query;
use Catenis\WP\React\Dns\Model\Message;

class HostsFileExecutorTest extends TestCase
{
    private $hosts;
    private $fallback;
    private $executor;

    public function setUp()
    {
        $this->hosts = $this->getMockBuilder('Catenis\WP\React\Dns\Config\HostsFile')->disableOriginalConstructor()->getMock();
        $this->fallback = $this->getMockBuilder('Catenis\WP\React\Dns\Query\ExecutorInterface')->getMock();
        $this->executor = new HostsFileExecutor($this->hosts, $this->fallback);
    }

    public function testDoesNotTryToGetIpsForMxQuery()
    {
        $this->hosts->expects($this->never())->method('getIpsForHost');
        $this->fallback->expects($this->once())->method('query');

        $this->executor->query(new Query('google.com', Message::TYPE_MX, Message::CLASS_IN));
    }

    public function testFallsBackIfNoIpsWereFound()
    {
        $this->hosts->expects($this->once())->method('getIpsForHost')->willReturn(array());
        $this->fallback->expects($this->once())->method('query');

        $this->executor->query(new Query('google.com', Message::TYPE_A, Message::CLASS_IN));
    }

    public function testReturnsResponseMessageIfIpsWereFound()
    {
        $this->hosts->expects($this->once())->method('getIpsForHost')->willReturn(array('127.0.0.1'));
        $this->fallback->expects($this->never())->method('query');

        $ret = $this->executor->query(new Query('google.com', Message::TYPE_A, Message::CLASS_IN));
    }

    public function testFallsBackIfNoIpv4Matches()
    {
        $this->hosts->expects($this->once())->method('getIpsForHost')->willReturn(array('::1'));
        $this->fallback->expects($this->once())->method('query');

        $ret = $this->executor->query(new Query('google.com', Message::TYPE_A, Message::CLASS_IN));
    }

    public function testReturnsResponseMessageIfIpv6AddressesWereFound()
    {
        $this->hosts->expects($this->once())->method('getIpsForHost')->willReturn(array('::1'));
        $this->fallback->expects($this->never())->method('query');

        $ret = $this->executor->query(new Query('google.com', Message::TYPE_AAAA, Message::CLASS_IN));
    }

    public function testFallsBackIfNoIpv6Matches()
    {
        $this->hosts->expects($this->once())->method('getIpsForHost')->willReturn(array('127.0.0.1'));
        $this->fallback->expects($this->once())->method('query');

        $ret = $this->executor->query(new Query('google.com', Message::TYPE_AAAA, Message::CLASS_IN));
    }

    public function testDoesReturnReverseIpv4Lookup()
    {
        $this->hosts->expects($this->once())->method('getHostsForIp')->with('127.0.0.1')->willReturn(array('localhost'));
        $this->fallback->expects($this->never())->method('query');

        $this->executor->query(new Query('1.0.0.127.in-addr.arpa', Message::TYPE_PTR, Message::CLASS_IN));
    }

    public function testFallsBackIfNoReverseIpv4Matches()
    {
        $this->hosts->expects($this->once())->method('getHostsForIp')->with('127.0.0.1')->willReturn(array());
        $this->fallback->expects($this->once())->method('query');

        $this->executor->query(new Query('1.0.0.127.in-addr.arpa', Message::TYPE_PTR, Message::CLASS_IN));
    }

    public function testDoesReturnReverseIpv6Lookup()
    {
        $this->hosts->expects($this->once())->method('getHostsForIp')->with('2a02:2e0:3fe:100::6')->willReturn(array('ip6-localhost'));
        $this->fallback->expects($this->never())->method('query');

        $this->executor->query(new Query('6.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.1.0.e.f.3.0.0.e.2.0.2.0.a.2.ip6.arpa', Message::TYPE_PTR, Message::CLASS_IN));
    }

    public function testFallsBackForInvalidAddress()
    {
        $this->hosts->expects($this->never())->method('getHostsForIp');
        $this->fallback->expects($this->once())->method('query');

        $this->executor->query(new Query('example.com', Message::TYPE_PTR, Message::CLASS_IN));
    }

    public function testReverseFallsBackForInvalidIpv4Address()
    {
        $this->hosts->expects($this->never())->method('getHostsForIp');
        $this->fallback->expects($this->once())->method('query');

        $this->executor->query(new Query('::1.in-addr.arpa', Message::TYPE_PTR, Message::CLASS_IN));
    }

    public function testReverseFallsBackForInvalidLengthIpv6Address()
    {
        $this->hosts->expects($this->never())->method('getHostsForIp');
        $this->fallback->expects($this->once())->method('query');

        $this->executor->query(new Query('abcd.ip6.arpa', Message::TYPE_PTR, Message::CLASS_IN));
    }

    public function testReverseFallsBackForInvalidHexIpv6Address()
    {
        $this->hosts->expects($this->never())->method('getHostsForIp');
        $this->fallback->expects($this->once())->method('query');

        $this->executor->query(new Query('zZz.ip6.arpa', Message::TYPE_PTR, Message::CLASS_IN));
    }
}
