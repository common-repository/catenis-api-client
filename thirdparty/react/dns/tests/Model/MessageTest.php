<?php

namespace Catenis\WP\React\Tests\Dns\Model;

use Catenis\WP\PHPUnit\Framework\TestCase;
use Catenis\WP\React\Dns\Query\Query;
use Catenis\WP\React\Dns\Model\Message;

class MessageTest extends TestCase
{
    public function testCreateRequestDesiresRecusion()
    {
        $query = new Query('igor.io', Message::TYPE_A, Message::CLASS_IN);
        $request = Message::createRequestForQuery($query);

        $this->assertFalse($request->qr);
        $this->assertTrue($request->rd);
    }

    public function testCreateResponseWithNoAnswers()
    {
        $query = new Query('igor.io', Message::TYPE_A, Message::CLASS_IN);
        $answers = array();
        $request = Message::createResponseWithAnswersForQuery($query, $answers);

        $this->assertTrue($request->qr);
        $this->assertEquals(Message::RCODE_OK, $request->rcode);
    }
}
