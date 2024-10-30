<?php

namespace Catenis\WP\React\HttpClient;

use Catenis\WP\React\EventLoop\LoopInterface;
use Catenis\WP\React\Socket\ConnectorInterface;
use Catenis\WP\React\Socket\Connector;

class Client
{
    private $connector;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if ($connector === null) {
            $connector = new Connector($loop);
        }

        $this->connector = $connector;
    }

    public function request($method, $url, array $headers = array(), $protocolVersion = '1.0')
    {
        $requestData = new RequestData($method, $url, $headers, $protocolVersion);

        return new Request($this->connector, $requestData);
    }
}
