<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest;

use Porta\Billing\BulkOperation;
use PortaApiTest\Tools\BulkOperationNull;

/**
 * Tests for BulkOperation
 *
 */
class BulkOperationTest extends \PHPUnit\Framework\TestCase
{

    public function testAsyncOperaton()
    {
        $ao = new BulkOperation('/test', ['paramsKey' => 'paramsValue']);
        $this->assertFalse($ao->executed());
        $this->assertFalse($ao->success());
        $this->assertEquals('/test', $ao->getCallEndpoint());
        $this->assertEquals(['paramsKey' => 'paramsValue'], $ao->getCallParams());
        $ao->processResponse(['responseKey' => 'responseValue']);
        $this->assertTrue($ao->executed());
        $this->assertTrue($ao->success());
        $this->assertEquals(['responseKey' => 'responseValue'], $ao->getResponse());

        $ao = new BulkOperation('/test', ['paramsKey' => 'paramsValue']);
        $ao->processException(new \Porta\Billing\Exceptions\PortaException('TestMessage'));
        $this->assertTrue($ao->executed());
        $this->assertFalse($ao->success());
        $this->assertInstanceOf(\Porta\Billing\Exceptions\PortaException::class, $ao->getException());
    }

    public function testBulkOperationNull()
    {
        $ao = new BulkOperationNull();
        $this->assertNull($ao->getCallEndpoint());
    }
}
