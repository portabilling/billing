<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Components;

use Porta\Billing\Components\SessionData;
use PortaApiTest\Tools\PortaToken;

/**
 * Tests for SessionData
 *
 */
class SessionDataTest extends \PortaApiTest\Tools\RequestTestCase
{

    const HOST = 'testhost.dom';

    public function testCreate()
    {
        $data = PortaToken::createLoginData(7200);
        $conf = $this->prepareConfig(null, [], $data);
        $d = new SessionData($conf);
        $this->assertTrue($d->isSet());
        $this->assertEquals($data, $d->getData());
        $this->assertEquals($data[SessionData::ACCESS_TOKEN], $d->getAccessToken());
        $this->assertEquals($data[SessionData::REFRESH_TOKEN], $d->getRefreshToken());
        $this->assertInstanceOf(\Porta\Billing\Components\PortaTokenDecoder::class, $d->getTokenDecoder());
        return $d;
    }

    /**
     *
     * @depends testCreate
     */
    public function testUpdate(SessionData $d)
    {
        $data = PortaToken::createRefreshData(7200);
        $d->updateData($data);
        $this->assertEquals($data[SessionData::ACCESS_TOKEN], $d->getAccessToken());
        // Set broken token
        $data['access_token'] = 'BrokenToken';
        $d->setData($data); // Nothing should happen
        // And finally check destructor
        $this->assertTrue($d->setLock());
        unset($d);
    }

    public function testEmpty()
    {
        $conf = $this->prepareConfig(null, []);
        $d = new SessionData($conf);
        $this->assertFalse($d->isSet());
        $this->assertNull($d->getData());
        $this->assertNull($d->getAccessToken());
        $this->assertNull($d->getRefreshToken());
        $this->assertInstanceOf(\Porta\Billing\Components\PortaTokenDecoder::class, $decoder
                = $d->getTokenDecoder());
        $this->assertFalse($decoder->isSet());
    }

    public function testDestruct()
    {
        $conf = $this->prepareConfig(null, []);
        $d = new SessionData($conf);
        // And finally check destructor
        $this->assertTrue($d->setLock());
        unset($d);
    }
}
