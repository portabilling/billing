<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Cache;

use Psr\SimpleCache\CacheInterface;
use Porta\Billing\Exceptions\CacheException;

/**
 * Dummy cache class, only holds data within class instance
 *
 * Means session need account data to setup and then it can be used until class
 * instance persists
 *
 * @package Cache
 * @api
 */
class InstanceCache implements CacheInterface
{

    protected array $data = [];

    public function clear(): bool
    {
        $this->data = [];
        return true;
    }

    public function delete($key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function get($key, $default = null)
    {
        return $this->has($key) //
                ? $this->data[$key]['data'] //
                : $default;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function has($key): bool
    {
        if (($this->data[$key]['expire'] ?? 0) > time()) {
            return true;
        }
        $this->delete($key);
        return false;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null): bool
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = date_create('@0')->add($ttl)->getTimestamp();
        } elseif (is_null($ttl)) {
            $ttl = 7200;
        } elseif (!is_numeric($ttl)) {
            throw new CacheException("Invalid TTL value");
        }
        $this->data[$key] = [
            'expire' => time() + $ttl,
            'data' => $value,
        ];
        return true;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null): bool
    {
        if (is_iterable($values)) {
            foreach ($values as $key => $value) {
                $this->set($key, $value, $ttl);
            }
            return true;
        }
        throw new CacheException("SetMultiple() argument must be iterable");
    }
}
