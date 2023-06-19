<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Cache;

use Porta\Billing\Exceptions\CacheException;

/**
 * Simple PSR-16 cache implementation with file storage
 *
 * It's very simple implementation of PSR-16 to avoid unused dependencies in a case
 * you need just store persistent billing session in low load environment.
 *
 * It is quite specific to the library requirements and **not recommended** to be
 * used to store something else but session data.
 *
 * The library takes session data from cache on each request, and may generate series
 * of sequential operations like has() -> $get() so this class will cache read current
 * content for a small time, but will write immediately. This match the usage pattern
 * and then this cache implementation is specific.
 *
 * @package Cache
 * @api
 */
class FileCache implements \Psr\SimpleCache\CacheInterface
{

    const EXPIRE = 'expire';
    const DATA = 'data';

    protected string $filename;
    protected float $memoryCacheTime;
    protected array $data = [];
    protected float $memoryExpireAt = 0;

    /**
     * Setup the file-based cache object
     *
     * @param string $filename filename to be used for persistent storage
     * @param int $memoryCacheTime time in **milliseconds** (1/1M of second) to use data after read
     * from file before re-read it.
     *
     * Only purpose of this to save on file read at sequence call like ->has()
     * and then ->get(), therefore always set it very low.
     *
     * Setting to zero (default) force to read from file each call of has(), get(), e.t.c.
     *
     * @package Cache
     * @api
     */
    public function __construct(string $filename, int $memoryCacheTime = 0)
    {
        $this->filename = $filename;
        $this->memoryCacheTime = $memoryCacheTime * 1000000;
    }

    public function clear(): bool
    {
        $this->data = [];
        $this->flush();
        return true;
    }

    public function delete($key): bool
    {
        unset($this->data[$key]);
        $this->flush();
        return true;
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            unset($this->data[$key]);
        }
        $this->flush();
        return true;
    }

    public function get($key, $default = null)
    {
        $this->loadAndCleanup();
        return $this->data[$key][self::DATA] ?? $default;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $this->loadAndCleanup();
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->data[$key][self::DATA] ?? $default;
        }
        return $result;
    }

    public function has($key): bool
    {
        $this->loadAndCleanup();
        return isset($this->data[$key]);
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->setInMemory($key, $value, $ttl);
        $this->flush();
        return true;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $val) {
            $this->setInMemory($key, $val, $ttl);
        }
        $this->flush();
        return true;
    }

    protected function setInMemory($key, $value, $ttl): void
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = date_create('@0')->add($ttl)->getTimestamp();
        } elseif (is_null($ttl)) {
            $ttl = 7200;
        } elseif (!is_numeric($ttl)) {
            throw new CacheException("Invalid TTL value");
        }
        $this->data[$key] = [
            self::EXPIRE => time() + $ttl,
            self::DATA => $value,
        ];
    }

    protected function loadAndCleanup(): void
    {
        if (($this->memoryExpireAt) < hrtime(true)) {
            if ((false === ($content = @file_get_contents($this->filename))) ||
                    (false === ($this->data = @unserialize($content)))) {
                // Assume file does not exist or broken
                $this->resetMemory();
                return;
            }
            $this->memoryExpireAt = hrtime(true) + $this->memoryCacheTime;
        }
        foreach ($this->data as $key => $val) {
            if ($this->data[$key][self::EXPIRE] <= time()) {
                $this->delete($key);
            }
        }
    }

    protected function resetMemory()
    {
        $this->data = [];
        $this->memoryExpireAt = 0;
    }

    protected function flush(): void
    {
        if (false === @file_put_contents($this->filename, serialize($this->data), LOCK_EX)) {
            $this->resetMemory();
            throw new CacheException("Error writing sesson data to file {$this->filename}");
        }
        $this->memoryExpireAt = microtime(true) + $this->memoryCacheTime;
    }
}
