<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Porta\Billing\Billing;
use Porta\Billing\Config as C;
use Porta\Billing\Exceptions\PortaException;
use Porta\Billing\Exceptions\PortaApiException;
use Porta\Billing\Exceptions\PortaAuthException;
use PortaApiTest\Tools\PortaToken;
use Porta\Billing\BulkOperation;

/**
 * Tests for billing class
 *
 */
class BillingTest extends Tools\RequestTestCase
{

    const HOST = 'testhost.dom';
    const ACCOUNT = [
        C::LOGIN => 'testUser',
        C::PASSWORD => 'testPass',
    ];

    public function testCall()
    {
        $sessionData = PortaToken::createLoginData(7200);
        $testJsonData = ['testKey' => 'testValue'];
        $conf = $this->prepareConfig(
                self::ACCOUNT,
                [
                    new Response(200, [], json_encode($sessionData)),
                    new Response(200,
                            ['Content-Type' => 'application/json; charset=UTF-8'],
                            json_encode($testJsonData)),
                    new Response(200,
                            ['content-type' => 'application/pdf', 'content-disposition' => 'attachment; filename="testfile.pdf"'],
                            'TestFileBody'),
                    new Response(200,
                            ['content-type' => 'application/csv', 'content-disposition' => 'attachment; filename="testfile.csv"'],
                            'TestFileBody'),
                    new Response(404, [], ''),
                ], null
        );

        $b = new Billing($conf);
        $this->assertTrue($b->isSessionPresent());
        //$this->assertEquals($sessionData, $storage->load());

        $r = $b->call('/NoMatter', $testJsonData);
        $this->assertEquals($testJsonData, $r);

        $r = $b->call('/NoMatter', $testJsonData);
        $this->assertEquals('testfile.pdf', $r['filename']);
        $this->assertEquals('application/pdf', $r['mime']);
        $this->assertEquals('TestFileBody', (string) $r['stream']);

        $r = $b->call('/NoMatter', $testJsonData);
        $this->assertEquals('testfile.csv', $r['filename']);
        $this->assertEquals('application/csv', $r['mime']);
        $this->assertEquals('TestFileBody', (string) $r['stream']);

        $this->expectException(PortaException::class);
        $r = $b->call('/NoMatter', $testJsonData);
    }

    public function testAutoReloginAndBrokenJson()
    {
        $testJsonData = ['testKey' => 'testValue'];
        $conf = $this->prepareConfig(self::ACCOUNT,
                [
                    new Response(500, [],
                            json_encode(['faultcode' => 'Server.Session.check_auth.auth_failed'])),
                    new Response(200, [],
                            json_encode(PortaToken::createLoginData(7200))),
                    new Response(200,
                            ['content-type' => 'application/json; charset=UTF-8'],
                            json_encode($testJsonData)),
                    new Response(200,
                            ['content-type' => 'application/json; charset=UTF-8'],
                            'NotJson'),
                ],
                PortaToken::createLoginData(7200));

        $b = new Billing($conf);

        $this->assertEquals($testJsonData, $b->call('/NoMatter'));
        $request = $this->getRequst(1);
        $this->assertEquals(
                'https://testhost.dom/rest/Session/login',
                (string) $request->getUri()
        );
        $this->assertEquals(
                ['params' => self::ACCOUNT],
                json_decode($request->getBody(), true)
        );

        $this->expectException(PortaException::class);
        $b->call('/NoMatter');
    }

    public function testDetectContentFail()
    {
        $conf = $this->prepareConfig(null,
                [
                    new Response(200, ['content-type' => 'application/xls']),
                ],
                PortaToken::createLoginData(7200));

        $b = new Billing($conf);
        $this->expectException(PortaException::class);
        $r = $b->call('/NoMatter');
    }

    public function testExtractFileFail()
    {
        $conf = $this->prepareConfig(null,
                [
                    new Response(200,
                            ['content-type' => 'application/pdf', 'content-disposition' => 'attachment'],
                            'TestFileBody'),
                ], PortaToken::createLoginData(7200)
        );

        $b = new Billing($conf);
        $this->expectException(PortaException::class);
        $r = $b->call('/NoMatter');
    }

    public function testAPIException()
    {
        $conf = $this->prepareConfig(null,
                [
                    new Response(500,
                            ['content-type' => 'application/json'],
                            '{"faultcode": "WrongRequest", "faultstring": "WrongRequestMessage"}'
                    ),
                ],
                PortaToken::createLoginData(7200)
        );
        $b = new Billing($conf);
        $this->expectException(PortaApiException::class);
        $r = $b->call('/NoMatter');
    }

    public function testAsyncCall()
    {
        $list = [
            'key1' => new BulkOperation('/test1', []),
            'key2' => [
                new BulkOperation('/test2-0',
                        ['paramKey2-0' => 'paramValue2-0']),
                new BulkOperation('/test2-1',
                        ['paramKey2-1' => 'paramValue2-1'])
            ],
            'noop1' => 'scalar',
            'key3' => new BulkOperation('/test3',
                    ['paramKey3' => 'paramValue3']),
            'key4' => new BulkOperation('/test4',
                    ['paramKey4' => 'paramValue4']),
            'keyNull' => new Tools\BulkOperationNull(),
            'key5' => new BulkOperation('/test5',
                    ['paramKey5' => 'paramValue5']),
            'key6' => new BulkOperation('/test6', []),
            'noop2' => [
                new \stdClass(),
                'noop2-1' => [],
            ],
            'key7' => new BulkOperation('/test7',
                    ['paramKey7' => 'paramValue7']),
            'key8' => new BulkOperation('/test8',
                    ['paramKey8' => 'paramValue8']),
        ];
        $conf = $this->prepareConfig(
                self::ACCOUNT,
                [
                    new Response(200, ['content-type' => 'application/json'], '{}'),
                    new Response(200, ['content-type' => 'application/json'], '{"answerKey2-0":"answerData2-0"}'),
                    new Response(200, ['content-type' => 'application/json'], '{"answerKey2-1":"answerData2-1"}'),
                    new Response(200, ['content-type' => 'application/json'], '{"answerKey3":"answerData3"}'),
                    new Response(200, ['content-type' => 'application/json'], '{"answerKey4":"answerData4"}'),
                    new Response(500, ['content-type' => 'application/json'], '{"faultcode": "WrongRequest", "faultstring": "WrongRequestMessage"}'),
                    new \GuzzleHttp\Exception\ConnectException("Connection fail",
                            new \GuzzleHttp\Psr7\Request('GET', '/test')),
                    new Response(200, ['content-type' => 'application/json'], '{"answerKey7":"answerData7"}'),
                    new Response(200, [], '{"answerKey8":"answerData8"}'),
                ],
                PortaToken::createLoginData(7200));
//        $conf = (new C(self::HOST, self::ACCOUNT))->setOptions(
//                $this->prepareRequests([
//                    new Response(200, ['content-type' => 'application/json'],
//                            '{}'),
//                    new Response(200, ['content-type' => 'application/json'],
//                            '{"answerKey2-0":"answerData2-0"}'),
//                    new Response(200, ['content-type' => 'application/json'],
//                            '{"answerKey2-1":"answerData2-1"}'),
//                    new Response(200, ['content-type' => 'application/json'],
//                            '{"answerKey3":"answerData3"}'),
//                    new Response(200, ['content-type' => 'application/json'],
//                            '{"answerKey4":"answerData4"}'),
//                    new Response(500, ['content-type' => 'application/json'],
//                            '{"faultcode": "WrongRequest", "faultstring": "WrongRequestMessage"}'),
//                    new \GuzzleHttp\Exception\ConnectException("Connection fail",
//                            new \GuzzleHttp\Psr7\Request('GET', '/test')),
//                    new Response(200, ['content-type' => 'application/json'],
//                            '{"answerKey7":"answerData7"}'),
//                    new Response(200, [], '{"answerKey8":"answerData8"}'),
//                ])
//        );
//        $storage = new Tools\SessionPHPClassStorage(PortaToken::createLoginData(7200));

        $b = new Billing($conf);

        $this->assertFalse($list['key1']->executed());

        $b->callConcurrent($list);

        $this->assertFalse($list['keyNull']->executed());

        $this->assertTrue($list['key1']->executed());
        $this->assertTrue($list['key1']->success());
        $this->assertEquals([], $list['key1']->getResponse());

        $this->assertTrue($list['key2'][0]->success());
        $this->assertEquals(
                ['answerKey2-0' => 'answerData2-0'],
                $list['key2'][0]->getResponse()
        );
        $this->assertTrue($list['key2'][1]->success());
        $this->assertEquals(
                ['answerKey2-1' => 'answerData2-1'],
                $list['key2'][1]->getResponse()
        );

        $this->assertTrue($list['key3']->success());
        $this->assertEquals(
                ['answerKey3' => 'answerData3'], $list['key3']->getResponse()
        );

        $this->assertTrue($list['key4']->success());
        $this->assertEquals(
                ['answerKey4' => 'answerData4'], $list['key4']->getResponse()
        );

        $this->assertFalse($list['key5']->success());
        $this->assertInstanceOf(
                PortaApiException::class, $list['key5']->getException()
        );

        $this->assertFalse($list['key6']->success());
        $this->assertInstanceOf(
                \Porta\Billing\Exceptions\PortaConnectException::class,
                $list['key6']->getException()
        );

        $this->assertTrue($list['key7']->success());
        $this->assertEquals(
                ['answerKey7' => 'answerData7'], $list['key7']->getResponse()
        );

        $this->assertFalse($list['key8']->success());
        $this->assertInstanceOf(
                \Porta\Billing\Exceptions\PortaException::class,
                $list['key8']->getException()
        );

        $b->callConcurrent([]);
    }

    const ZONE = 'Pacific/Palau';
    const DATETIME = '2023-03-20 07:38:17';
    const DATE = '2023-03-20';
    const LOCAL_DATETIME = '2023-03-20 16:38:17';

    public function testDateTimeConvert()
    {
        $local = new \DateTime(self::LOCAL_DATETIME,
                new \DateTimeZone(self::ZONE));
        $this->assertEquals($local, Billing::timeToLocal(self::DATETIME));
        $this->assertEquals(self::DATETIME, Billing::timeToBilling($local));
    }
}
