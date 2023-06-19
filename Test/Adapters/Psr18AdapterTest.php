<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Adapters;

/**
 * Tests for Psr18Adapter
 *
 */
class Psr18AdapterTest extends \PHPUnit\Framework\TestCase
{

    public function testClientExceptionInterface()
    {
        $client = $this->createMock(\Psr\Http\Client\ClientInterface::class);
        $adapter = new \Porta\Billing\Adapters\Psr18Adapter($client);
        $client->expects($this->once())
                ->method('sendRequest')
                ->willThrowException($this->createMock(\Psr\Http\Client\ClientExceptionInterface::class));
        $this->expectException(\Porta\Billing\Exceptions\PortaException::class);
        $adapter->send(new \GuzzleHttp\Psr7\Request('GET', '/'));
    }

    public function testAsyncExcepton()
    {
        $client = $this->createMock(\Psr\Http\Client\ClientInterface::class);
        $adapter = new \Porta\Billing\Adapters\Psr18Adapter($client);
        $this->expectException(\Porta\Billing\Exceptions\PortaException::class);
        $adapter->sendAsync(new \GuzzleHttp\Psr7\Request('GET', '/'));
    }
}
