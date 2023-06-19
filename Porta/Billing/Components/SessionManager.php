<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Components;

use Porta\Billing\Interfaces\ConfigInterface;
use Porta\Billing\Interfaces\ClientAdapterInterface;
use Porta\Billing\Exceptions\PortaException;
use Porta\Billing\Exceptions\PortaApiException;
use Porta\Billing\Exceptions\PortaAuthException;
use Porta\Billing\Components\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class to handle session
 *
 * @internal
 */
class SessionManager
{

    protected const TOKEN_GOOD = 'token_good';
    protected const TOKEN_IN_MARGIN = 'need_refresh';
    protected const TOKEN_EXPIRED = 'need_relogin';

    protected ClientAdapterInterface $client;
    protected ConfigInterface $config;
    protected SessionData $sessionData;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->client = $config->getClientAdaptor();
        $this->sessionData = new SessionData($config);
        $this->sessionLoad();
    }

    public function addAuth(RequestInterface $request): RequestInterface
    {
        if (null === ($token = $this->sessionData->getAccessToken())) {
            return $request;
        }
        return $request->withAddedHeader('Authorization', 'Bearer ' . $this->sessionData->getAccessToken());
    }

    public function login(array $account): void
    {
        $this->config->setAccount($account);
        while (!$this->sessionData->setLock()) {
            throw new PortaException("Unable to lock sesson storage during login attempt");
        }
        $this->processLogin();
    }

    public function getUsername(): ?string
    {
        return $this->sessionData->getTokenDecoder()->getLogin();
    }

    /**
     * Does active sesson check to billing server, relogin if required
     *
     * Completes 'Session/ping' call to check session state, then:
     *
     * - If session not recognised, and ccredentials present, trying to relogin
     * - If no credentials in config or login failure - throws auth exception
     *
     * @throws PortaAuthException
     */
    public function checkSession(): void
    {
        if ($this->isSessionPresent()) {
            $request = $this->prepareBillingRequest(
                    '/Session/ping',
                    [SessionData::ACCESS_TOKEN => $this->sessionData->getAccessToken()]
            );
            $response = $this->client->send($request);
            if (200 != $response->getStatusCode()) {
                throw PortaApiException::createFromResponse($response);
            }
            $answer = Utils::jsonResponse($response);
            if (0 < ($answer['user_id'] ?? 0)) {
                return;
            }
        }
        $this->relogin();
    }

    public function relogin(): void
    {
        if (!$this->config->hasAccount()) {
            throw new PortaAuthException("Have no credentials to restore session");
        }
        if (!$this->sessionData->setLock()) {
            return;
        }
        $this->processLogin();
    }

    /**
     * Closes the session explicitly
     */
    public function logout(): void
    {
        if (!$this->sessionData->isSet()) {
            return;
        }
        try {
            $request = $this->prepareBillingRequest(
                    '/Session/logout',
                    [SessionData::ACCESS_TOKEN => $this->sessionData->getAccessToken()]
            );
            $this->client->send($request);
        } catch (PortaException $ex) {
            if ($ex instanceof \Porta\Billing\Exceptions\PortaConnectException) {
                throw $ex;
            }
        }
        $this->sessionData->clear();
    }

    public function isSessionPresent(): bool
    {
        return $this->sessionData->isSet();
    }

    protected function processLogin(): void
    {
        $request = $this->prepareBillingRequest(
                '/Session/login',
                $this->config->getAccount()
        );
        $response = $this->client->send($request);

        if (200 == $response->getStatusCode()) {
            $this->sessionData->setData(Utils::jsonResponse($response));
            return;
        }
        $this->sessionData->clear();
        if (self::isLoginFailed($response)) {
            throw PortaAuthException::createWithAccount($this->config->getAccount());
        }
        throw PortaApiException::createFromResponse($response);
    }

    public function refreshToken(): bool
    {
        if (!$this->sessionData->setLock()) {
            return true;
        }
        $request = $this->prepareBillingRequest(
                '/Session/refresh_access_token',
                [$this->sessionData::REFRESH_TOKEN => $this->sessionData->getRefreshToken()]
        );
        $response = $this->client->send($request);

        if (200 == $response->getStatusCode()) {
            $this->sessionData->updateData(Utils::jsonResponse($response));
            return true;
        }
        $this->sessionData->clear();
        return false;
    }

    protected static function isLoginFailed(ResponseInterface $response): bool
    {
        return (500 == $response->getStatusCode()) &&
                ('Server.Session.auth_failed' == (Utils::jsonResponse($response)['faultcode'] ?? ''));
    }

    protected function sessionLoad(): void
    {
        $tokenDecoded = $this->sessionData->getTokenDecoder();
        if ($tokenDecoded->isSet()) {
            $dt = $tokenDecoded->getExpire()->getTimestamp() - (new \DateTime())->getTimestamp();
            if ($dt > $this->config->getSessionRefreshMargin()) {
                return;
            }
            if ($this->refreshToken()) {
                return;
            }
            $this->sessionData->clear();
        }
        if ($this->config->hasAccount()) {
            $this->relogin();
        }
    }

    public function prepareBillingRequest(string $endpoint, array $data = []): RequestInterface
    {
        return $this->addAuth(
                        $this->config->getBaseApiRequest($endpoint)
                                ->withAddedHeader('content-type', 'application/json')
                                ->withBody($this->config->getStream(Utils::makeApiJson($data)))
        );
    }
}
