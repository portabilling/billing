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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base abstract class implementing shared functions of API and ESPF
 *
 * @abstract
 */
abstract class BillingBase
{

    /**
     * Billing API datetime format
     * @api
     */
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected ConfigInterface $config;
    protected ClientAdapterInterface $client;
    protected SessionManager $session;

    /**
     * Setup the class, load (if any) saved session state and get it ready to use
     *
     * On construct:
     * - Check the cached session token expire time within margin or left
     * - If token expire soon (within configured margin), try to refresh token
     * - If token expired or refresh failed - try to relogin if account data present,
     * throwing exceptions on failures.
     * - If no accoount data present, just left the class un-logged-in, then you
     * need login() to get it connected
     *
     * @param ConfigInterface $config Configuration object to run
     *
     * @api
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->client = $config->getClientAdaptor();
        $this->session = new SessionManager($config);
    }

    /**
     * Do login and setup the session.
     *
     * The session data will be stored if session storage class is supplied
     *
     * @param array $account Account record to login to the billing. Combination
     * of login+password or login+token required
     * ```
     * $account = [
     *     'login' => 'myUserName',    // Mandatory username
     *     'password' => 'myPassword', // When login with password
     *     'token' => 'myToken'        // When login with API token
     * ```
     * @throws \Porta\Billing\Exceptions\PortaAuthException
     * @api
     */
    public function login(array $account): void
    {
        $this->session->login($account);
    }

    /**
     * Closes the session explicitly.
     *
     * Will call '/Session/logout' api method. Due of some problems with Portaone
     * resposes, session will considered closed whatever server respond. No server
     * error will be respected, but conenction errors will thrown.
     *
     * @throws \Porta\Billing\Exceptions\PortaConnectException
     * @api
     */
    public function logout(): void
    {
        $this->session->logout();
    }

    /**
     * Returns true if session data exit and not expired
     *
     * Returns true if session data (token) exists and not expired, false if
     * not logged in for any reason.
     *
     * Does not complete active sesion check to the server. Due of serveer configuratioin
     * issues token may be invalidated for inactivity before it's expire time really
     * has come. So, positive isSessionUp() only means persistent session data loaded,
     *
     * @return bool
     * @api
     */
    public function isSessionPresent(): bool
    {
        return $this->session->isSessionPresent();
    }

    /**
     * Does active sesson check to billing server, relogin if required
     *
     * Completes 'Session/ping' call to check session state, then:
     * - If session not recognised, and credentials present, trying to relogin
     * - If no credentials in config or login failure - throws auth exception
     *
     * @throws \Porta\Billing\Exceptions\PortaAuthException on relogin failed or
     * no credentilas
     * @api
     */
    public function checkSession(): void
    {
        $this->session->checkSession();
    }

    /**
     * Return current user login name
     *
     * Returns username taken from access token or null if no session is up
     *
     * @return string|null
     * @api
     */
    public function getUsername(): ?string
    {
        return $this->session->getUsername();
    }

    protected function sendSafe(RequestInterface $request): ResponseInterface
    {
        if ($this->isSessionPresent()) {
            $response = $this->client->send($request);
            if (200 == $response->getStatusCode()) {
                return $response;
            }
            if (!$this->isAuthError($response)) {
                $this->processPortaError($response);
            }
        }
        $this->session->relogin();
        $response = $this->client->send($request);
        if (200 != $response->getStatusCode()) {
            $this->processPortaError($response);
        }
        return $response;
    }

    /**
     * Called to process errors with application-specific manner
     *
     * @abstract
     */
    abstract protected function processPortaError(ResponseInterface $response): void;

    /**
     * Answers if errored (non-200) response is and authentification error or not.
     *
     * @abstract
     */
    abstract protected function isAuthError(ResponseInterface $response): bool;

    /**
     * Convert billing-supplied UTC time string to DateTime object with target
     * timezone
     *
     * @param string $billingTime - datetime string as billing returns
     * @param string $timezone - timezone string like 'Europe/London" or '+3000',
     *                         as defined at https://www.php.net/manual/en/datetimezone.construct.php
     * @return \DateTime
     * @api
     */
    public static function timeToLocal(string $billingTime, string $timezone = 'UTC'): \DateTime
    {
        return (new \DateTime($billingTime, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($timezone));
    }

    /**
     * Return billing API string at UTC fro given Datetime object
     *
     * The DateTime obhject given will not change.
     *
     * @param \DateTimeInterface $time Datetime object to take time from
     * @return string Billing API-type datetime string, shifted to UTC
     * @api
     */
    public static function timeToBilling(\DateTimeInterface $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format(self::DATETIME_FORMAT);
    }
}
