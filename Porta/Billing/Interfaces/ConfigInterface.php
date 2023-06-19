<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\SimpleCache\CacheInterface;
use Porta\Billing\Interfaces\ClientAdapterInterface;

/**
 * Interface to manage billing server API cinfiguration
 *
 * Billing use this interface to know where to connect, store account, set HTTP(s) call options, e.t.c.
 *
 * @package Configuration
 * @api
 */
interface ConfigInterface
{

    /**
     * Array key for login/username of account array
     * @api
     */
    public const LOGIN = 'login';

    /**
     * Array key for password of account array. Sould be used either password or
     * token
     * @api
     */
    public const PASSWORD = 'password';

    /**
     * Array key for token of account array. Sould be used either password or
     * token
     * @api
     */
    public const TOKEN = 'token';

    /**
     * override in a case of need to use other scheme, example 'http'
     * @api
     */
    public const SCHEME = 'https';

    /**
     * override to use other API base on the same host
     * @api
     */
    public const API_BASE = '/rest';

    /**
     * override to use other ESPF base on the same host
     * @api
     */
    public const ESPF_BASE = '/espf/v1';

    /**
     * Returns ClientAdaptor for currently configured client
     *
     * @return ClientAdapterInterface
     * @api
     */
    public function getClientAdaptor(): ClientAdapterInterface;

    /**
     * Returns PSR-16 caching object to store persistent session data
     *
     * @return CacheInterface
     * @api
     */
    public function getCache(): CacheInterface;

    /**
     * Return cache tag to use for session storage object in the cache
     * @return string
     * @api
     */
    public function getCacheTag(): string;

    /**
     * Sets cache tag to use for session storage object in the cache
     *
     * @param string $tag
     * @return ClientAdapterInterface self mutable object for chaining
     * @api
     */
    public function setCacheTag(string $tag): self;

    /**
     * Returns base API psr-7 request object with base path + given path
     *
     * Uses known factory to create base POST request with API base path and
     * given extra path.
     *
     * @param string $path extra path to append after API prefix path
     *
     * @return RequestInterface base Request object, filled with URI
     * @api
     */
    public function getBaseApiRequest(string $path = ''): RequestInterface;

    /**
     * Returns base ESPF psr-7 request of given type with base path + given path
     *
     * @param string $method request method to use
     * @param string $path extra path to append after API prefix path
     * @return RequestInterface Base Request object of given type, filled with URI
     * @api
     */
    public function getBaseEspfRequest(string $method, string $path = ''): RequestInterface;

    /**
     * Returns psr-7 Stream object created with given string
     *
     * @param string $content conten of the Stream
     * @return StreamInterface Stream object, filled with content
     * @api
     */
    public function getStream(string $content = ''): StreamInterface;

    /**
     * Returns true if accound record present in the config and it's correct.
     *
     * Billing classes rely on account data is checked for consistency in the
     * ConfigInterface class. Consistency mean that a pair of login+password or
     * login+token present.
     *
     * Billing class will not check it and send as is, generating API failure if
     * the data is wrong.
     *
     * @return bool
     * @api
     */
    public function hasAccount(): bool;

    /**
     * Provides account record or throw PortaAuthException if there no record inside.
     *
     * @return array must have a pair of keys: `account`+`password` or `account`+`token`
     * @throws PortaAuthException
     * @api
     */
    public function getAccount(): array;

    /**
     * Sets account record. Exception if the record is inconsistent
     *
     * @param array|null $account must have a pair of keys: 'account'+'password'
     * or 'account'+'token', null to clear account record out.
     *
     * @return self for chaining
     * @throws PortaAuthException
     * @api
     */
    public function setAccount(?array $account = null): self;

    /**
     * Returns margin to token expire time triggering token refresh procedure.
     *
     * Default token expire is +48h from issue time, default margin is 3600 (1h)
     * and a good for an app where you have more then one call in each hour.
     *
     * Please mind that billing also has inactivity timer, 24h by default,
     * which invalidates tocken even it is not yet expired.
     *
     * @return int seconds before token expire time to trigger refresh
     * @api
     */
    public function getSessionRefreshMargin(): int;

    /**
     * Set margin to token expire time triggering token refresh procedure.
     *
     * Default token expire is +48h from issue time, default margin is 3600 (1h)
     * and a good for an app where you have more then one call in each hour.
     *
     * Please mind that billing also has inactivity timer, 24h by default,
     * which invalidates tocken even it is not yet expired.
     *
     * @param int $margin token refresh margin in seconds
     *
     * @return self for chaining
     * @api
     */
    public function setSessionRefreshMargin(int $margin): self;
}
