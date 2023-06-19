<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing;

use Porta\Billing\Interfaces\ConfigInterface;
use Porta\Billing\Exceptions\PortaESPFException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Porta\Billing\Components\Utils;

/**
 * Wrapper for ESPF API
 *
 * The class is intended to provide interface to Portaone ESPF API. It handles
 * authorisation, access token management and ESPF call handling.
 * It needs: {@see ConfigInterface} object as billing server host, account and
 * dependencies source.
 *
 * The difference ESPF to API call is that ESPF returns HTTP 40x codes in a case
 * of request failure. These codes will throw {@see PortaESPFException}. Meaning
 * of each code depends of endpoint called.
 *
 * See 'API documentation' section on <https://docs.portaone.com>
 *
 * @api
 * @package Billing
 */
class ESPF extends Components\BillingBase
{

    /**
     * @inherit
     * @package Billing
     */
    public function __construct(ConfigInterface $config)
    {
        parent::__construct($config);
    }

    /**
     * GET ESPF request
     *
     * Params will be encoded and send as query string
     *
     * @param string $endpoint endpoint to call
     * @param array $params associative array of params, may omit
     * @return array associative array for returned JSON
     *
     * @throws PortaESPFException on ESPF api error, check error coode with API docs
     * @api
     */
    public function get(string $endpoint, array $params = []): array
    {
        $request = $this->makeEspfRequest('GET', $endpoint);
        $response = $this->sendSafe(
                $request->withUri(
                        $request->getUri()
                                ->withQuery(http_build_query($params))
                )
        );
        return Utils::jsonResponse($response);
    }

    /**
     * POST ESPF request
     *
     * Params will be encoded and sent as JSON body
     *
     * @param string $endpoint endpoint to call
     * @param array $params associative array of params, may omit
     * @return array associative array for returned JSON or empty array on empty billing answer
     *
     * @throws PortaESPFException on ESPF api error, check error coode with API docs
     * @api
     */
    public function post(string $endpoint, array $params = []): array
    {
        $response = $this->sendSafe(
                $this->makeEspfRequest('POST', $endpoint)
                        ->withAddedHeader('content-type', 'application/json')
                        ->withBody($this->prepareJsonBody($params))
        );
        return Utils::jsonResponse($response);
    }

    /**
     * PUT ESPF request
     *
     * Params are mandatory and will be encoded and sent as JSON body
     *
     * @param string $endpoint endpoint to call
     * @param array $params associative array of params, mandatory
     * @return array associative array for returned JSON.
     *
     * @throws PortaESPFException on ESPF api error, check error coode with API docs
     * @api
     */
    public function put(string $endpoint, array $params): array
    {
        $response = $this->sendSafe(
                $this->makeEspfRequest('PUT', $endpoint)
                        ->withAddedHeader('content-type', 'application/json')
                        ->withBody($this->prepareJsonBody($params))
        );
        return Utils::jsonResponse($response);
    }

    /**
     * DELETE ESPF request
     *
     * DELETE has no params, only endpoint. It returns HTTP 200 on success
     * and 40x on failure, so the methid wil return nothing on success and throw
     * PortaESPFException on error.
     *
     * @param string $endpoint endpoint to call
     * @return void
     *
     * @throws PortaESPFException on ESPF api error, check error coode with API docs
     * @api
     */
    public function delete(string $endpoint): void
    {
        $this->sendSafe(
                $this->makeEspfRequest('DELETE', $endpoint)
        );
    }

    protected function isAuthError(ResponseInterface $response): bool
    {
        return 401 == $response->getStatusCode();
    }

    protected function processPortaError(ResponseInterface $response): void
    {
        throw new PortaESPFException("ESPF API error", $response->getStatusCode());
    }

    protected function makeEspfRequest(string $method, string $endpoint): RequestInterface
    {
        return $this->session->addAuth(
                        $this->config->getBaseEspfRequest($method, $endpoint)
        );
    }

    protected function prepareJsonBody(array $data): StreamInterface
    {
        return $this->config->getStream(
                        json_encode(
                                [] == $data //
                                ? new \stdClass() //
                                : $data
                        )
        );
    }
}
