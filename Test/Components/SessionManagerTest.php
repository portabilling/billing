<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Components;

use Porta\Billing\ConfigBase;
use Porta\Billing\Components\SessionClient;
use Porta\Billing\Components\SessionManager;
use Porta\Billing\Components\SessionData;
use Porta\Billing\Interfaces\SessionStorageInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PortaApiTest\Tools\PortaToken;
use PortaApiTest\Tools\SessionPHPClassStorage;

/**
 * Test for SessonManager
 */
class SessionManagerTest extends \PortaApiTest\Tools\RequestTestCase
{

    const ACCOUNT = ['login' => 'username', 'password' => 'password'];
    const HOST = 'testhost.dom';

    public function testLoadAndLogout()
    {
        $sessionData = PortaToken::createLoginData(7200);
        $conf = $this->prepareConfig(null, [new Response(200, [], '{"success": 1}')], $sessionData);

        $s = new SessionManager($conf);

        $this->assertTrue($s->isSessionPresent());
        $this->assertEquals(
                'Bearer ' . $sessionData['access_token'],
                $s->prepareBillingRequest('')
                        ->getHeaderLine('Authorization')
        );
        $this->assertEquals('userName', $s->getUsername());

        //Logout
        $s->logout();
        $this->assertFalse($s->isSessionPresent());
        $this->assertEquals([], $s->prepareBillingRequest('', [])->getHeader('Authorization'));
        $this->assertNull($s->getUsername());
        $request = $this->getRequst(0);
        $this->assertEquals('https://testhost.dom/rest/Session/logout', (string) $request->getUri());
        $this->assertEquals([], $s->prepareBillingRequest('', [])->getHeader('Authorization'));
        $this->assertFalse($this->getOptions(0)['http_errors']);
        $this->assertEquals(['params' => ['access_token' => $sessionData['access_token']]], json_decode($request->getBody(), true));

        //Repeated Logout do nothing
        $s->logout();
    }

    public function testEmpty()
    {
        $conf = $this->prepareConfig(null, []);

        $s = new SessionManager($conf);

        $this->assertFalse($s->isSessionPresent());
    }

    public function testLogin()
    {
        $conf = $this->prepareConfig(null, [
            new Response(200, [], json_encode(PortaToken::createLoginData(7600))),
        ]);
        $s = new SessionManager($conf);

        $s->login(self::ACCOUNT);
        $this->assertTrue($s->isSessionPresent());
        $request = $this->getRequst(0);
        $this->assertEquals('https://testhost.dom/rest/Session/login', (string) $request->getUri());
        $this->assertEquals([], $request->getHeader('Authorization'));
        $this->assertFalse($this->getOptions(0)['http_errors']);
        $this->assertEquals(['params' => self::ACCOUNT], json_decode($request->getBody(), true));

        //Test exception by lock
//        $conf = new ConfigBase('host.dom');
//        $storage = new SessionPHPClassStorage([], false);
//        $s = new SessionManager($conf, $client, $storage);
//        $this->expectException(\Porta\Billing\Exceptions\PortaException::class);
//        $s->login(self::ACCOUNT);
    }

    public function testAuthFailed()
    {
        $conf = $this->prepareConfig(null, [
            new Response(500, [], '{"faultcode": "Server.Session.auth_failed",'
                    . '"faultstring": "The login or password is incorrect. '
                    . 'Please note: your password is case sensitive."}'),
        ]);
        $s = new SessionManager($conf);
        $this->expectException(\Porta\Billing\Exceptions\PortaAuthException::class);
        $s->login(self::ACCOUNT);
    }

    public function testLoginOtherFailed()
    {
        $conf = $this->prepareConfig(null, [new Response(501),]);

        $s = new SessionManager($conf);
        $this->expectException(\Porta\Billing\Exceptions\PortaException::class);
        $s->login(self::ACCOUNT);
    }

    public function testLogoutConnectException()
    {
        $conf = $this->prepareConfig(null,
                [
                    new \GuzzleHttp\Exception\ConnectException("Connect problems", new \GuzzleHttp\Psr7\Request('GET', '')),
                ],
                PortaToken::createLoginData(7200)
        );
        $s = new SessionManager($conf);
        $this->expectException(\Porta\Billing\Exceptions\PortaConnectException::class);
        $s->logout();
    }

//    public function testTokenRefreshByLock()
//    {
//        $conf = new ConfigBase('host.dom');
//        $storage = new SessionPHPClassStorage(PortaToken::createLoginData(100), false);
//        $client = new SessionClient($conf);
//        $s = new SessionManager($conf, $client, $storage);
//        $this->assertTrue($s->isSessionPresent());
//    }

    public function testTokenRefreshSuccess()
    {
        //Nothing happen if token expire time not within margin
        $conf = $this->prepareConfig(null, [], PortaToken::createLoginData(3601));
        $s = new SessionManager($conf);
        $this->assertTrue($s->isSessionPresent());

        // Then should refresh ft it is within margin
        $sessionData = PortaToken::createLoginData(3599);
        $sessionNewData = PortaToken::createLoginData(7600);
        $conf = $this->prepareConfig(null,
                [
                    new Response(200, [], json_encode($sessionNewData)),
                ],
                $sessionData
        );

        $s = new SessionManager($conf);

        $request = $this->getRequst(0);
        $this->assertEquals('https://testhost.dom/rest/Session/refresh_access_token', (string) $request->getUri());
        $this->assertFalse($this->getOptions(0)['http_errors']);
        $this->assertEquals(
                ['params' => [SessionData::REFRESH_TOKEN => $sessionData['refresh_token']]],
                json_decode($request->getBody(), true));
        $this->assertTrue($s->isSessionPresent());
        $this->assertEquals('Bearer ' . $sessionNewData['access_token'],
                $s->prepareBillingRequest('')->getHeaderLine('Authorization')
        );
    }

    public function testTockenRefreshFailed()
    {
        $conf = $this->prepareConfig(null, [new Response(500),], PortaToken::createLoginData(3599));
        $s = new SessionManager($conf);
        $this->assertFalse($s->isSessionPresent());
    }

    public function testTockenRefreshFailedRelogin()
    {
        $conf = $this->prepareConfig(self::ACCOUNT,
                [
                    new Response(500),
                    new Response(200, [], json_encode(PortaToken::createLoginData(7600))),
                ],
                PortaToken::createLoginData(3599));
        $s = new SessionManager($conf);
        $this->assertTrue($s->isSessionPresent());
    }

    public function testChecksession()
    {
        $conf = $this->prepareConfig(self::ACCOUNT,
                [
                    new Response(200, [], '{"user_id": 10}'),
                    new Response(200, [], '{"user_id": 0}'),
                    new Response(200, [], json_encode(PortaToken::createLoginData(7600))),
                    new Response(500, [], '{"faultcode": "Server.failed", "faultstring": "Somenting goes wrong"}'),
                ],
                PortaToken::createLoginData(7200)
        );
        $s = new SessionManager($conf);
        // Successfull check
        $s->checkSession();
        // Succesfull relogin
        $s->checkSession();
        // Server fail on relogin
        $this->expectException(\Porta\Billing\Exceptions\PortaApiException::class);
        $s->checkSession();
        $this->assertEquals(4, count($this->container));
    }

    public function testReloginNoCreds()
    {
        $conf = $this->prepareConfig(null, []);
        $s = new SessionManager($conf);
        $this->expectException(\Porta\Billing\Exceptions\PortaAuthException::class);
        $s->relogin();
    }

    public function testLocks()
    {
        $guzzle = new \GuzzleHttp\Client();
        $factory = new \GuzzleHttp\Psr7\HttpFactory;
        $adaptor = new \Porta\Billing\Adapters\Psr18Adapter($guzzle);
        $cache = new \Porta\Billing\Cache\InstanceCache();
        $conf = new \Porta\Billing\Config(static::HOST, $factory, $factory,
                $adaptor, $cache, self::ACCOUNT);
        $cache->set('cache.billing.sesson.lock', microtime(true) + 1, 5);
        $s = new SessionManager($conf);
        // relogin must do nothing
        $s->relogin();
        // Tokern refresh must do nothing
        $s->refreshToken();
        // Login must throw exception
        $this->expectException(\Porta\Billing\Exceptions\PortaException::class);
        $s->login(self::ACCOUNT);
    }
}
