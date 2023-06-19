<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing;

use Porta\Billing\Interfaces\ConfigInterface;
use Porta\Billing\Exceptions\PortaAuthException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Porta\Billing\Interfaces\ClientAdapterInterface;

/**
 * Porta-API general configuration class which may extend for extra features
 *
 * It works as lib-specific container, providing every part of the
 * library with required data and environment bindings.
 *
 * Should be a good for container autowiring. All dependencies are bound to interfaces,
 * allowing to use implementatnon of your choice, but you need to hint $host and
 * $account
 *
 * Require:
 * - [PSR-17 RequestFactoryInterface](https://www.php-fig.org/psr/psr-17/#21-requestfactoryinterface)
 * to created PSR-7 requests
 * - [PSR-17 StreamFactoryInterface](https://www.php-fig.org/psr/psr-17/#24-streamfactoryinterface)
 * to create PSR-7 streams
 * - [PSR-16 CacheInterface](https://www.php-fig.org/psr/psr-16/#21-cacheinterface)
 * (simple cache) to allow session data (tocken) persist across script calls. Simple
 * implementation of file cache {@see \Porta\Billing\Cache\FileCache}  and one-time
 * memory only stub {@see \Porta\Billing\Cache\InstanceCache} packaged.
 * - (@see ClientAdapterInterface} - propertary interface, this allow to use almost any
 * HTTP client library or build your own HTTP client backend. PSR-18 client adapter
 * packaged {@see \Porta\Billing\Adapters\Psr18Adapter}.
 *
 * @api
 * @package Configuration
 */
class Config implements ConfigInterface
{

    protected string $host;
    protected ?array $account = null;
    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;
    protected ClientAdapterInterface $clientAdaptor;
    protected CacheInterface $cache;
    protected string $cachehTag = 'cache.billing.sesson';
    protected int $refreshMargin = 3600;

    /**
     * Setup configuration object
     *
     * @param string $host Hostname/IP address of the server, no slashes, no schema,
     * but port if required. Example: `bill-sip.mycompany.com`
     *
     * @param RequestFactoryInterface $requestFactory PSR-17 Factory to create requests
     *
     * @param StreamFactoryInterface $streamFactory PSR-17 Factory to create steams
     *
     * @param ClientAdapterInterface $clientAdaptor Client adaptor to use.
     *
     * @param CacheInterface $cache PSR-16 Simple cache object to persist session data
     *
     * @param array|null $account Account record to login to the billing. Combination
     * of login+password or login+token required
     * ```
     * $account = [
     *     'login' => 'myUserName',    // Mandatory username
     *     'password' => 'myPassword', // When login with password
     *     'token' => 'myToken'        // When login with API token
     * ```
     * @throws Porta\Billing\Exceptions\PortaAuthException
     * @api
     */
    public function __construct(
            string $host,
            RequestFactoryInterface $requestFactory,
            StreamFactoryInterface $streamFactory,
            ClientAdapterInterface $clientAdaptor,
            CacheInterface $cache,
            ?array $account = null)
    {

        $this->host = $host;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->clientAdaptor = $clientAdaptor;
        $this->cache = $cache;
        $this->setAccount($account);
    }

    /**
     * @inherit
     * @api
     */
    public function getBaseApiRequest(string $path = ''): RequestInterface
    {
        return $this->buildRequest('POST', static::API_BASE, $path);
    }

    /**
     * @inherit
     * @api
     */
    public function getBaseEspfRequest(string $method, string $path = ''): RequestInterface
    {
        return $this->buildRequest($method, static::ESPF_BASE, $path)
                        ->withAddedHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    /**
     * @inherit
     * @api
     */
    public function getCache(): \Psr\SimpleCache\CacheInterface
    {
        return $this->cache;
    }

    /**
     * @inherit
     * @api
     */
    public function getCacheTag(): string
    {
        return $this->cachehTag;
    }

    /**
     * @inherit
     * @api
     */
    public function setCacheTag(string $tag): self
    {
        $this->cachehTag = $tag;
        return $this;
    }

    /**
     * @inherit
     * @api
     */
    public function getClientAdaptor(): \Porta\Billing\Interfaces\ClientAdapterInterface
    {
        return $this->clientAdaptor;
    }

    /**
     * @inherit
     * @api
     */
    public function getStream(string $content = ''): StreamInterface
    {
        return $this->streamFactory->createStream($content);
    }

    /**
     * @inherit
     * @api
     */
    public function getAccount(): array
    {
        if (is_null($this->account)) {
            throw new PortaAuthException("Account data required, but not exists");
        }
        return $this->account;
    }

    /**
     * @inherit
     * @api
     */
    public function getSessionRefreshMargin(): int
    {
        return $this->refreshMargin;
    }

    /**
     * @inherit
     * @api
     */
    public function setSessionRefreshMargin(int $margin): self
    {
        $this->refreshMargin = $margin;
        return $this;
    }

    /**
     * @inherit
     * @api
     */
    public function hasAccount(): bool
    {
        return !is_null($this->account);
    }

    /**
     * @inherit
     * @api
     */
    public function setAccount(?array $account = null): self
    {
        $account = ([] == $account) ? null : $account;
        if (!is_null($account) &&
                (!isset($account[ConfigInterface::LOGIN]) ||
                !(
                isset($account[ConfigInterface::PASSWORD]) ||
                isset($account[ConfigInterface::TOKEN])))) {
            throw new PortaAuthException("Invalid account record provided, need login+pass or login+token");
        }
        $this->account = $account;
        return $this;
    }

    protected function buildRequest(string $method, string $prefix, string $path): RequestInterface
    {
        return $this->requestFactory->createRequest($method,
                        static::SCHEME . '://' . $this->host
                        . $prefix
                        . ((('' == $path) || ('/' == substr($path, 0, 1))) ? '' : '/')
                        . $path);
    }
}
