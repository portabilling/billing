<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Cache;

use Porta\Billing\Cache\FileCache;

/**
 * tests for FileChache
 *
 */
class FileCacheTest extends \PHPUnit\Framework\TestCase
{

    const FILE = __DIR__ . '/../temp/file_cache_test';

    protected function tearDown(): void
    {
        if (file_exists(self::FILE)) {
            unlink(self::FILE);
        }
    }

    protected function hasFile()
    {
        return file_exists(self::FILE);
    }

    protected function readFile(): array
    {
        return unserialize(file_get_contents(self::FILE));
    }

    protected function writeFile(array $data): void
    {
        file_put_contents(self::FILE, serialize($data));
    }

    public function testEmpty()
    {
        $c = new FileCache(self::FILE, 0);
        $this->assertFalse($c->has('someKey'));
        $this->assertNull($c->get('someKey'));
        $this->assertEquals('default', $c->get('someKey', 'default'));
        $this->assertEquals(['someKey1' => null, 'someKey2' => null], $c->getMultiple(['someKey1', 'someKey2']));
        $this->assertEquals(['someKey1' => 'default', 'someKey2' => 'default'], $c->getMultiple(['someKey1', 'someKey2'], 'default'));
        $this->assertFalse($this->hasFile());
    }

    public function testMost()
    {
        $c = new FileCache(self::FILE, 0);
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
                $this->readFile()
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
                $this->readFile()
        );

        $this->assertTrue($c->clear());
        $this->assertEquals([], $this->readFile());
    }

    public function testSetMulti()
    {
        $c = new FileCache(self::FILE, 0);
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
                $this->readFile()
        );
    }

    public function testExpired()
    {
        $c = new FileCache(self::FILE, 0);
        $this->writeFile([
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
        ]);
        $this->assertFalse($c->has('KeyTttlNull'));
        $this->assertEquals([], $this->readFile());
    }

    public function testWrongTTLexception()
    {
        $c = new FileCache(self::FILE, 0);
        $this->expectException(\Psr\SimpleCache\CacheException::class);
        $c->set('Test', 'Test', []);
    }

    public function testCantWriteFile()
    {
        $c = new FileCache('/does-not-exists/file_cache_test', 0);
        $this->expectException(\Psr\SimpleCache\CacheException::class);
        $c->set('Test', 'Test', 100);
    }

    /**
     * It have t be tested with quite big in-memory storage time because of test
     * environment may crate quite big timing deviations.s
     *
     */
    public function testCaching()
    {
        $c = new FileCache(self::FILE, 200);
        $this->writeFile(['testKey' => ['expire' => time() + 100, 'data' => 'testValue']]);
        $t = hrtime(true);
        $read1 = $c->get('testKey');
        $this->writeFile(['testKey' => ['expire' => time() + 100, 'data' => 'testValueNew']]);
        usleep((180000000 + $t - hrtime(true)) / 1000);
        $read2 = $c->get('testKey');
        usleep(50000);
        $read3 = $c->get('testKey');
        $this->assertEquals('testValue', $read1);
        $this->assertEquals('testValue', $read2);
        $this->assertEquals('testValueNew', $read3);
    }
}
