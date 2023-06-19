<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Components;

use Porta\Billing\Components\BillingBase;
use Porta\Billing\ConfigBase;
use PortaApiTest\Tools\SessionPHPClassStorage;
use PortaApiTest\Tools\PortaToken;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for BillingBase
 */
class BillingBaseTest extends \PortaApiTest\Tools\RequestTestCase
{

    const HOST = 'testhost.dom';
    const ACCOUNT = ['login' => 'username', 'password' => 'password'];

    public function testSessionForwarded()
    {

        $conf = $this->prepareConfig(self::ACCOUNT,
                [
                    new Response(200),
                    new Response(200, [],
                            json_encode(PortaToken::createLoginData(7200))),
                    new Response(200, [], '{"user_id": 10}'),
                ],
                PortaToken::createLoginData(7200)
        );
        $b = $this->getMockForAbstractClass(
                BillingBase::class, [$conf]
        );
        $this->assertTrue($b->isSessionPresent());
        $this->assertEquals('userName', $b->getUsername());
        $b->logout();
        $this->assertFalse($b->isSessionPresent());
        $b->login(self::ACCOUNT);
        $this->assertTrue($b->isSessionPresent());
        $b->checkSession();
        $this->assertEquals(3, count($this->container));
    }
}
