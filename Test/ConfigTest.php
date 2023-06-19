<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest;

use Porta\Billing\Config;
use Porta\Billing\Exceptions\PortaAuthException;

/**
 * Test class for PortaConfig
 *
 */
class ConfigTest extends \PHPUnit\Framework\TestCase
{

    const HOST = 'testhost.dom';
    const ACCOUNT = [
        Config::LOGIN => 'testUser',
        Config::PASSWORD => 'testPass',
    ];

    public function testSimple()
    {
        $client = $this->createMock(\Porta\Billing\Interfaces\ClientAdapterInterface:: class);
        $requestFactory = $this->createMock(\Psr\Http\Message\RequestFactoryInterface::class);
        $streamFactory = $this->createMock(\Psr\Http\Message\StreamFactoryInterface::class);
        $adaptor = $this->createMock(\Porta\Billing\Interfaces\ClientAdapterInterface::class);
        $cache = new \Porta\Billing\Cache\InstanceCache();
        $conf = new Config(static::HOST, $requestFactory, $streamFactory,
                $adaptor, $cache, self::ACCOUNT);

        $this->assertTrue($conf->hasAccount());
        $this->assertEquals(self::ACCOUNT, $conf->getAccount());
        $this->assertEquals($client, $conf->getClientAdaptor());
        $this->assertEquals($cache, $conf->getCache());
        $this->assertEquals('cache.billing.sesson', $conf->getCacheTag());
        $this->assertEquals($conf, $conf->setCacheTag('NewCacheTag'));
        $this->assertEquals('NewCacheTag', $conf->getCacheTag());
        $this->assertEquals(3600, $conf->getSessionRefreshMargin());
        $this->assertEquals($conf, $conf->setSessionRefreshMargin(999));
        $this->assertEquals(999, $conf->getSessionRefreshMargin());

        $conf = new Config(static::HOST, $requestFactory, $streamFactory,
                $adaptor, $cache);
        $this->assertFalse($conf->hasAccount());
        $this->expectException(PortaAuthException::class);
        $conf->getAccount();
    }

    public function accountData()
    {
        return [
            [[], false],
            [['login' => 'mylogin'], false],
            [['login' => 'mylogin', 'password' => 'mypass'], true],
            [['login' => 'mylogin', 'token' => 'mytoken'], true],
            [['password' => 'mypass'], false],
            [['token' => 'mytoken'], false],
            [['password' => 'mypass', 'token' => 'mytoken'], false],
        ];
    }

    /**
     * @dataProvider accountData
     */
    public function testSetAccount($account, $good)
    {
        $client = $this->createMock(\Porta\Billing\Interfaces\ClientAdapterInterface:: class);
        $requestFactory = $this->createMock(\Psr\Http\Message\RequestFactoryInterface::class);
        $streamFactory = $this->createMock(\Psr\Http\Message\StreamFactoryInterface::class);
        $adaptor = $this->createMock(\Porta\Billing\Interfaces\ClientAdapterInterface::class);
        $cache = new \Porta\Billing\Cache\InstanceCache();
        $conf = new Config(static::HOST, $requestFactory, $streamFactory,
                $adaptor, $cache);

        if (!$good) {
            $this->expectException(PortaAuthException::class);
        }
        $conf->setAccount($account);
        $this->assertEquals($account, $conf->getAccount());
    }

    public function testObjectCreatin()
    {
        $client = $this->createMock(\Porta\Billing\Interfaces\ClientAdapterInterface:: class);
        $factory = new \GuzzleHttp\Psr7\HttpFactory();
        $adaptor = $this->createMock(\Porta\Billing\Interfaces\ClientAdapterInterface::class);
        $cache = new \Porta\Billing\Cache\InstanceCache();
        $conf = new Config(static::HOST, $factory, $factory, $adaptor, $cache);

        $this->assertEquals(
                'https://testhost.dom/rest/Test/path',
                (string) $conf->getBaseApiRequest('Test/path')->getUri()
        );
        $this->assertEquals(
                'https://testhost.dom/rest/Test/path',
                (string) $conf->getBaseApiRequest('/Test/path')->getUri()
        );
        $this->assertEquals(
                'https://testhost.dom/rest',
                (string) $conf->getBaseApiRequest()->getUri()
        );
        $request = $conf->getBaseEspfRequest('TEST', 'Test/path');
        $this->assertEquals('TEST', $request->getMethod());
        $this->assertEquals(
                'https://testhost.dom/espf/v1/Test/path',
                (string) $request->getUri()
        );
        $this->assertEquals(
                'https://testhost.dom/espf/v1',
                (string) $conf->getBaseEspfRequest('TEST')->getUri()
        );
        $stream = $conf->getStream('TestStreamContent');
        $this->assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $stream);
        $this->assertEquals('TestStreamContent', (string) $stream);
        $stream = $conf->getStream();
        $this->assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $stream);
        $this->assertEquals('', (string) $stream);
    }
}
