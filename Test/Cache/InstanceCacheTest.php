<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Cache;

/**
 * Description of InstanceCacheTest
 *
 */
class InstanceCacheTest extends \PHPUnit\Framework\TestCase
{

    public function testEmpty()
    {
        $c = new InstanceCacheWrap();
        $this->assertFalse($c->has('someKey'));
        $this->assertNull($c->get('someKey'));
        $this->assertEquals('default', $c->get('someKey', 'default'));
        $this->assertEquals(['someKey1' => null, 'someKey2' => null], $c->getMultiple(['someKey1', 'someKey2']));
        $this->assertEquals(['someKey1' => 'default', 'someKey2' => 'default'], $c->getMultiple(['someKey1', 'someKey2'], 'default'));
    }

    public function testMost()
    {
        $c = new InstanceCacheWrap();
        $this->assertTrue($c->set('KeyTttlNull', 'ValTttNull'));
        $this->assertTrue($c->set('KeyTttlInt', 'ValTttInt', 1000));
        $this->assertTrue($c->set('KeyTttlDTI', 'ValTttlDTI', new \DateInterval('P1D')));
        $this->assertEquals(
                [
                    'KeyTttlNull' => [
                        'expire' => time() + 7200,
                        'data' => 'ValTttNull'
                    ],
                    'KeyTttlInt' => [
                        'expire' => time() + 1000,
                        'data' => 'ValTttInt'
                    ],
                    'KeyTttlDTI' => [
                        'expire' => time() + 60 * 60 * 24,
                        'data' => 'ValTttlDTI'
                    ],
                ],
                $c->data
        );
        $this->assertEquals('ValTttNull', $c->get('KeyTttlNull'));
        $this->assertEquals(
                ['KeyTttlNull' => 'ValTttNull', 'KeyTttlDTI' => 'ValTttlDTI'],
                $c->getMultiple(['KeyTttlNull', 'KeyTttlDTI']));
        $this->assertTrue($c->deleteMultiple(['KeyTttlNull', 'KeyTttlDTI']));
        $this->assertEquals(
                [
                    'KeyTttlInt' => [
                        'expire' => time() + 1000,
                        'data' => 'ValTttInt'
                    ],
                ],
                $c->data
        );

        $this->assertTrue($c->clear());
        $this->assertEquals([], $c->data);
    }

    public function testSetMulti()
    {
        $c = new InstanceCacheWrap();
        $this->assertTrue(
                $c->setMultiple(
                        [
                            'KeyTttlNull' => 'ValTttNull',
                            'KeyTttlInt' => 'ValTttInt',
                            'KeyTttlDTI' => 'ValTttlDTI',
                        ],
                        100
                )
        );
        $this->assertEquals(
                [
                    'KeyTttlNull' => [
                        'expire' => time() + 100,
                        'data' => 'ValTttNull'
                    ],
                    'KeyTttlInt' => [
                        'expire' => time() + 100,
                        'data' => 'ValTttInt'
                    ],
                    'KeyTttlDTI' => [
                        'expire' => time() + 100,
                        'data' => 'ValTttlDTI'
                    ],
                ],
                $c->data
        );
    }

    public function testExpired()
    {
        $c = new InstanceCacheWrap();
        $c->data = [
            'KeyTttlNull' => [
                'expire' => time() - 1,
                'data' => 'ValTttNull'
            ],
            'KeyTttlInt' => [
                'expire' => time() - 1,
                'data' => 'ValTttInt'
            ],
            'KeyTttlDTI' => [
                'expire' => time() - 1,
                'data' => 'ValTttlDTI'
            ],
        ];
        $this->assertFalse($c->has('KeyTttlNull'));
        $this->assertEquals(
                [
                    'KeyTttlInt' => [
                        'expire' => time() - 1,
                        'data' => 'ValTttInt'
                    ],
                    'KeyTttlDTI' => [
                        'expire' => time() - 1,
                        'data' => 'ValTttlDTI'
                    ]
                ],
                $c->data
        );
    }

    public function testWrongTTLexception()
    {
        $c = new InstanceCacheWrap();
        $this->expectException(\Psr\SimpleCache\CacheException::class);
        $c->set('Test', 'Test', []);
    }

    public function testWrongMultipleArgException()
    {
        $c = new InstanceCacheWrap();
        $this->expectException(\Psr\SimpleCache\CacheException::class);
        $c->setMultiple('NotAnArray');
    }
}
