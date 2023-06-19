<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest;

use Porta\Billing\Interfaces\ConfigInterface as C;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Porta\Billing\ESPF;
use Porta\Billing\Exceptions\PortaESPFException;
use PortaApiTest\Tools\PortaToken;

/**
 * DTests for ESPF class
 *
 */
class ESPFTest extends Tools\RequestTestCase
{

    const HOST = 'testhost.dom';
    const ACCOUNT = [
        C::LOGIN => 'testLogin',
        C::PASSWORD => 'testPass',
        C::TOKEN => 'testToken',
    ];
    const PARAMS = ['param1' => 'value1', 'param2' => 'value2'];
    const ANSWER = ['key1' => 'data1', 'key2' => 'data2',];

    public function testGet()
    {
        $config = $this->prepareConfig(
                self::ACCOUNT,
                [
                    new Response(200, [], json_encode(self::ANSWER)),
                    new Response(200, []),
                    new Response(403, []),
                ],
                PortaToken::createLoginData(10000)
        );
        $espf = new ESPF($config);

        $this->assertEquals(self::ANSWER, $espf->get('/test', self::PARAMS));
        $request = $this->getRequst(0);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('param1=value1&param2=value2', $request->getUri()->getQuery());
        $this->assertEquals(C::ESPF_BASE . '/test', $request->getUri()->getPath());

        $this->assertEquals([], $espf->get('/test'));
        $request = $this->getRequst(1);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('', $request->getUri()->getQuery());
        $this->assertEquals(C::ESPF_BASE . '/test', $request->getUri()->getPath());
        $this->expectException(PortaESPFException::class);
        $espf->get('/test');
    }

    public function testPost()
    {
        $config = $this->prepareConfig(
                self::ACCOUNT,
                [
                    new Response(200, [], json_encode(self::ANSWER)),
                    new Response(200, []),
                    new Response(403, []),
                ],
                PortaToken::createLoginData(10000)
        );
        $espf = new ESPF($config);

        $this->assertEquals(self::ANSWER, $espf->post('/test', self::PARAMS));
        $request = $this->getRequst(0);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals(json_encode(self::PARAMS), (string) $request->getBody());
        $this->assertEquals(C::ESPF_BASE . '/test', $request->getUri()->getPath());

        $this->assertEquals([], $espf->post('/test'));
        $request = $this->getRequst(1);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('{}', (string) $request->getBody());
        $this->assertEquals(C::ESPF_BASE . '/test', $request->getUri()->getPath());
        $this->expectException(PortaESPFException::class);
        $espf->post('/test');
    }

    public function testPut()
    {
        $config = $this->prepareConfig(
                self::ACCOUNT,
                [
                    new Response(200, [], json_encode(self::ANSWER)),
                ],
                PortaToken::createLoginData(10000)
        );
        $espf = new ESPF($config);

        $this->assertEquals(self::ANSWER, $espf->put('/test', self::PARAMS));
        $request = $this->getRequst(0);
        $this->assertEquals('PUT', $request->getMethod());
        $this->assertEquals(json_encode(self::PARAMS), (string) $request->getBody());
        $this->assertEquals(C::ESPF_BASE . '/test', $request->getUri()->getPath());
    }

    public function testDelete()
    {
        $config = $this->prepareConfig(
                self::ACCOUNT,
                [
                    new Response(200, []),
                    new Response(401, []),
                    new Response(200, [], json_encode(Tools\PortaToken::createLoginData(10000))),
                    new Response(400, []),
                ],
                PortaToken::createLoginData(10000)
        );
        $espf = new ESPF($config);

        $this->assertNull($espf->delete('/test'));
        $request = $this->getRequst(0);
        $this->assertEquals('DELETE', $request->getMethod());
        $this->assertEquals(0, $request->getBody()->getSize());
        $this->assertEquals(C::ESPF_BASE . '/test', $request->getUri()->getPath());

        $this->expectException(PortaESPFException::class);
        $espf->delete('/test');
    }
}
