<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Components;

use Porta\Billing\Interfaces\ConfigInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Session data object
 *
 * @internal
 */
class SessionData
{

    const SESSION_ID = 'session_id';
    const REFRESH_TOKEN = 'refresh_token';
    const EXPIRES_AT = 'expires_at';
    const ACCESS_TOKEN = 'access_token';
    const LOCK_TIME = 0.3;

    protected CacheInterface $cache;
    protected string $cacheTag;
    protected bool $myLock = false;

    public function __construct(ConfigInterface $config)
    {
        $this->cache = $config->getCache();
        $this->cacheTag = $config->getCacheTag();
    }

    public function __destruct()
    {
        if ($this->myLock) {
            $this->cache->delete($this->cacheTag . '.lock');
        }
    }

    public function setLock(): bool
    {
        if ($this->hasLock()) {
            return $this->myLock;
        }
        $this->cache->set($this->cacheTag . '.lock', microtime(true) + self::LOCK_TIME, 3);
        $this->myLock = true;
        return true;
    }

    protected function hasLock(): bool
    {
        return $this->cache->has($this->cacheTag . '.lock') &&
                ($this->cache->get($this->cacheTag . '.lock') > microtime(true));
    }

    protected function unlock(): void
    {
        $this->cache->delete($this->cacheTag . '.lock');
        $this->myLock = false;
    }

    protected function waitForUnlock()
    {
        while ($this->hasLock()) {
            usleep(10000);
        }
    }

    /**
     * Clears session data
     */
    public function clear()
    {
        $this->cache->delete($this->cacheTag);
        $this->unlock();
    }

    /**
     * Put data to the cache
     *
     * Checks if token may be detected and decoded, if not - do nothing.
     *
     * Calculates TTL based on token exp field and put data to the cache
     *
     * @param array $data
     * @return self
     */
    public function setData(array $data): self
    {
        $token = new PortaTokenDecoder($data[self::ACCESS_TOKEN] ?? null);
        if (!$token->isSet()) {
            return $this;
        }
        $this->cache->set(
                $this->cacheTag,
                $data,
                $token->getExpire()->diff(new \DateTime(), true)
        );
        $this->unlock();
        return $this;
    }

    /**
     * lock-safel reads data from the cache.
     *
     * @return array
     */
    public function getData(): ?array
    {
        $this->waitForUnlock();
        return $this->cache->get($this->cacheTag, null);
    }

    public function updateData(array $data): self
    {
        $this->setData(array_merge($this->cache->get($this->cacheTag, []), $data));
        return $this;
    }

    public function isSet(): bool
    {
        return $this->cache->has($this->cacheTag);
    }

    public function getAccessToken(): ?string
    {
        return $this->getData()[self::ACCESS_TOKEN] ?? null;
    }

    public function getRefreshToken()
    {
        return $this->getData()[self::REFRESH_TOKEN] ?? null;
    }

    public function getTokenDecoder(): PortaTokenDecoder
    {
        return new PortaTokenDecoder($this->cache->get($this->cacheTag)[self::ACCESS_TOKEN] ?? null);
    }
}
