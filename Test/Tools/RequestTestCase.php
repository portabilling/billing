<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Tools;

use Psr\SimpleCache\CacheInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * Class for testing HTTT requests
 */
class RequestTestCase extends \PHPUnit\Framework\TestCase
{

    protected $container = [];

    protected function prepareRequests(array $answers)
    {
        $mock = new MockHandler($answers);
        $handlerStack = HandlerStack::create($mock);
        $this->container = [];
        $handlerStack->push(Middleware::history($this->container));

        return ['handler' => $handlerStack];
    }

    protected function prepareCache($data): CacheInterface
    {
        $cache = new \Porta\Billing\Cache\InstanceCache();
        $mock = $this->createMock(\Porta\Billing\Config::class);
        $mock->expects($this->once())->method('getCache')->willReturn($cache);
        $mock->expects($this->once())->method('getCacheTag')->willReturn('cache.billing.sesson');
        $sessionData = new \Porta\Billing\Components\SessionData($mock);
        $sessionData->setData($data);
        return $cache;
    }

    protected function prepareConfig(
            ?array $account,
            array $answers,
            array $cacheData = null
    ): \Porta\Billing\Interfaces\ConfigInterface
    {
        $guzzle = new \GuzzleHttp\Client($this->prepareRequests($answers));
        $factory = new \GuzzleHttp\Psr7\HttpFactory;
        $adaptor = new \Porta\Billing\Adapters\Psr18Adapter($guzzle);
        $cache = is_null($cacheData) ? new \Porta\Billing\Cache\InstanceCache() : $this->prepareCache($cacheData);
        return new \Porta\Billing\Config(static::HOST, $factory, $factory,
                $adaptor, $cache, $account);
    }

    protected function getRequst($index): ?Request
    {
        return $this->container[$index]['request'] ?? null;
    }

    protected function getOptions($index): ?array
    {
        return $this->container[$index]['options'] ?? null;
    }
}
