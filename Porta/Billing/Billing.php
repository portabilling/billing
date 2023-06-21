<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing;

use Porta\Billing\Components\BillingBase;
use Porta\Billing\Interfaces\ConfigInterface;
use Porta\Billing\Interfaces\BulkOperationInterface;
use Porta\Billing\Exceptions\PortaException;
use Porta\Billing\Exceptions\PortaApiException;
use Porta\Billing\Exceptions\PortaAuthException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Porta\Billing\Components\Utils;

/**
 * Billing API wrapper
 *
 * The class is intended to provide interface to Portaone billing API. It handles
 * authorisation, access token management and API call handling.
 * It needs {@see ConfigInterface} object as source of every configuraton aspect
 *
 * See 'API documentation' section on <https://docs.portaone.com>
 * @api
 * @package Billing
 */
class Billing extends BillingBase
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
     * Creates PSR-7 Request object for API call
     *
     * Creates Request with all necessaly elements could be send to the billing
     * API: endpoint, data, auth headers and all other.
     *
     * This used for all other functions, but you may also got request, send it
     * with some specific client, async, multitread, wrap or whatever else, and
     * then got Respose object and process the response with static processResponse()
     * method below. all the processing sounds like:
     * ```
     * // Creating PSR-7 HTTP Request object
     * $request = $billing->makeRequest(
     *                       '/Section/get_something_info', // Endpoint
     *                       ['i_something' => 42]          // Params
     * );
     *
     * // Perform call with PSR-18 HTTP client
     * $response = $client->sendRequest($request);
     *
     * // Parse response, extract data or files, handle errors
     * $result = Billing::processResponse($response);
     * ```
     *
     * Reference to your billing system API docs, located
     * at **https://your-billing-sip-host/doc/api/** or API section of Portaone
     * docs site <https://docs.portaone.com/>. Mind your billing release version.
     *
     * @param string $endpoint billing API endpoint
     * @param array $params API request params to put into "params" section
     *
     * @return RequestInterface
     * @api
     */
    public function makeRequest(string $endpoint, array $params = []): RequestInterface
    {
        return $this->session->prepareBillingRequest($endpoint, $params);
    }

    /**
     * Perform billing API call.
     *
     * Reference to your billing system API docs, located
     * at **https://your-billing-sip-host/doc/api/** or API section of Portaone
     * docs site <https://docs.portaone.com/>. Mind your billing release version.
     *
     * @param string $endpoint API endpoint as per docs, exapmle: '/Customer/get_customer_info'
     * @param array $params API requst params to put into "params" section
     *
     * @return array Billing system answer, converted to associative array. If
     * billing retuns file, returns array with keys:
     * ```
     * $returned = [
     *     'filename' => 'Invoice1234.pdf', // string, returned file name,
     *     'mime' => 'application/pdf',     // string, MIME file type
     *     'stream' => Stream               // PSR-7 stream object with file
     * ];
     * ```
     * @throws PortaException on general errors
     * @throws PortaAuthException on auth-related errors
     * @throws PortaApiException on API returned an error
     * @api
     */
    public function call(string $endpoint, array $params = []): array
    {
        return static::processResponse(
                        $this->sendSafe(
                                $this->makeRequest($endpoint, $params)
                        )
        );
    }

    /**
     * Perform bulk billig call, running multiple concurrent requests at once.
     *
     * Method crawling recursively the traversable given to find all
     * {@see Interfaces\AsyncOperationInterface} objects and then
     * process it in parallel with given concurrency (default 20). After run, the
     * objects filled with answers or exceptions depending of each separate call
     * results. it is safe as if there no object, it just do nothing silently,
     * but still do a session check call.
     *
     * **Please, take care to ensure the session is up and warm.** If session fail for
     * any reason, this metod **will not relogin** even credentials set. Therefore,
     * all the bundle of async calls will faile and array be populateed with auth
     * exceptions. If you can't be sure on session state, please use
     * [checkSession()](classes/Porta-Billing-Billing.html#method_checkSession)
     * before call to ensure session is up and Ok. **Do not trust to
     * [isSessionPresent()](classes/Porta-Billing-Billing.html#method_isSessionPresent)**,
     * it only checks token state, it does not means the session OK on the server side.
     *
     * @param iterable $operations array or any other multi-level iterable, containing
     *        objects, implementing {@see AsyncOperationInterface}
     *
     * @param int $concurency how much calls to run in parallel. Default is 20.
     *
     * **WARNING: due of some reasons increasing of concurrency over some empiric
     * value does not decrease overall time to complete all the requests and even
     * make it longer. In fact, PHP does not support async operations, so all the
     * magic comes from cURL multi-call, so it could be combination of limitations:
     * cURL, PortaOne server and PHP itlself. As for me, 20 works fine.**
     *
     * @api
     */
    public function callConcurrent(iterable $operations, int $concurency = 20): void
    {
        /** @var BulkOperationInterface[] $bulk */
        $bulk = [];
        $requests = function () use (&$bulk, $operations) {
            /**  @var BulkOperationInterface $operation */
            foreach ($this->crawlForOperations($operations) as $key => $operation) {
                $bulk[$key] = $operation;
                yield $key => $this->makeRequest(
                                $operation->getCallEndpoint(),
                                $operation->getCallParams(),
                );
            }
        };
        $answers = $this->client->concurrent($requests(), $concurency);

        foreach ($bulk as $key => $operation) {
            if (!isset($answers[$key])) {
                throw new PortaException("No response found for a request in a bulk set");
            }
            $response = $answers[$key];
            if ($response instanceof ResponseInterface) {
                if (200 == $response->getStatusCode()) {
                    try {
                        $operation->processResponse(static::processResponse($response));
                    } catch (PortaException $ex) {
                        $operation->processException($ex);
                    }
                } else {
                    $operation->processException(PortaApiException::createFromResponse($response));
                }
            } elseif ($response instanceof PortaException) {
                $operation->processException($response);
            } else {
                throw new PortaException("Bulk request returned an object which neither ResponseInterface nor PortaException");
            }
        }
    }

    /**
     * Performs async call to the billing and returns promise
     *
     * This method is backend-dependent. In fact, it receives promise from client
     * adapter and wraps it with processResponse() to next promise and returns.
     *
     * As a result, returned promise will contain processed billing response (array)
     * on fulfilled or throw proper exceptions in failures, behave the same way as
     * {@see method::call()} method do.
     *
     * @param string $endpoint API endpoint as per docs, exapmle: '/Customer/get_customer_info'
     * @param array $params API requst params to put into "params" section
     * @return mixed backend-dependent 'thenable' promise
     *
     * On fulfill, promise contains Billing system answer, converted to associative array. If
     * billing retuns file, returns array with keys:
     * ```
     * $returned = [
     *     'filename' => 'Invoice1234.pdf', // string, returned file name,
     *     'mime' => 'application/pdf',     // string, MIME file type
     *     'stream' => Stream               // PSR-7 stream object with file
     * ];
     * ```
     *
     * On reject, it will contain (and throw as unwrapped):
     * - PortaException on general errors
     * - PortaAuthException on auth-related errors
     * - PortaApiException on API returned an error
     *
     * @api
     */
    public function callAsync(string $endpoint, array $params = [])
    {
        return $this->client->sendAsync($this->makeRequest($endpoint, $params))
                        ->then(
                                function (ResponseInterface $response) {
                                    return Billing::processResponse($response);
                                }
        );
    }

    protected function crawlForOperations(iterable $operations)
    {
        foreach ($operations as $item) {
            if (is_iterable($item)) {
                yield from $this->crawlForOperations($item);
            }
            if (($item instanceof BulkOperationInterface) && !is_null($item->getCallEndpoint())) {
                yield uniqid('', true) => $item;
            }
        }
    }

    /**
     * Process billing response, given as Response object
     *
     * The full response nadling be provided, includint throwing exceptions on
     * different errors, if any.
     *
     * Companion function to makeRequest(). All the processing sounds like:
     * ```
     * // Creating PSR-7 HTTP Request object
     * $request = $billing->makeRequest(
     *                       '/Section/get_something_info', // Endpoint
     *                       ['i_something' => 42]          // Params
     * );
     *
     * // Perform call with PSR-18 HTTP client
     * $response = $client->sendRequest($request);
     *
     * // Parse response, extract data or files, handle errors
     * $result = Billing::processResponse($response);
     * ```
     *
     * @param ResponseInterface $response object got from the biliing as answer
     * @return array billing answer, converted to associative array
     *
     * @throws PortaException on general errors
     * @throws PortaAuthException on auth-related errors
     * @throws PortaApiException on API returned an error
     * @api
     */
    public static function processResponse(ResponseInterface $response): array
    {
        switch (static::detectContentType($response)) {
            case 'application/json':
                return Utils::jsonResponse($response);
            case 'application/pdf':
            case 'application/csv':
                return static::extractFile($response);
            default:
                throw new PortaException("Missed or unknown content-type '" . static::detectContentType($response) . "'in the billing answer");
        }
    }

    protected static function detectContentType(ResponseInterface $response): string
    {
        $headers = $response->getHeader('content-type');
        if (([] == $headers) || (1 !== preg_match('/(application\/[a-zA-Z]+)/', $headers[0], $matches))) {
            return 'unknown';
        }
        return $matches[1];
    }

    protected static function extractFile(ResponseInterface $response): array
    {
        $headers = $response->getHeader('Content-Disposition');
        if (([] == $headers) || (1 !== preg_match('/attachment; filename="([^"]+)"/', $headers[0], $matches))) {
            throw new PortaException("Invalid file content-disposition header");
        }
        return [
            'filename' => $matches[1],
            'mime' => static::detectContentType($response),
            'stream' => $response->getBody(),
        ];
    }

    protected function isAuthError(ResponseInterface $response): bool
    {
        if (500 != $response->getStatusCode()) {
            return false;
        }
        $faultCode = json_decode((string) $response->getBody(), true)['faultcode'] ?? 'none';
        return in_array($faultCode,
                [
                    'Server.Session.check_auth.auth_failed',
                    'Client.check_auth.envelope_missed',
                    'Client.Session.check_auth.failed_to_process_jwt',
                    'Client.Session.check_auth.failed_to_process_access_token'
                ]
        );
    }

    protected function processPortaError(ResponseInterface $response): void
    {
        throw PortaApiException::createFromResponse($response);
    }
}
