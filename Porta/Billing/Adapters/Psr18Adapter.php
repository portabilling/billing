<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Adapters;

use Porta\Billing\Interfaces\ClientAdapterInterface;
use Porta\Billing\Exceptions\PortaConnectException;
use Porta\Billing\Exceptions\PortaException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Generic adaptor allows to use any PSR-18 compatible HTTP client
 *
 * @package ClientAdaptors
 * @api
 */
class Psr18Adapter implements ClientAdapterInterface
{

    protected ClientInterface $client;

    /**
     * Setup adaptor with externally suppied PSR-18 client
     *
     * @param ClientInterface $client
     * @api
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Sending request usong PSR-18 compatible client
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws PortaConnectException
     * @throws PortaException
     * @api
     */
    public function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (NetworkExceptionInterface $exception) {
            throw new PortaConnectException($exception->getMessage(), $exception->getCode());
        } catch (ClientExceptionInterface $exception) {
            throw new PortaException($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Just sends the requests one by one, slow and sad
     *
     * PSR-18 only support single call, so for PSR-18 adapter we have to simulete
     * concurent calls by sending requests one by one
     *
     * @param iterable $requests
     * @param int $concurency
     * @return array
     * @api
     */
    public function concurrent(iterable $requests, int $concurency = 20): array
    {
        /** @var ClientAdapterInterface $this */
        $result = [];
        foreach ($requests as $key => $request) {
            try {
                $result[$key] = $this->send($request);
            } catch (PortaException $ex) {
                $result[$key] = $ex;
            }
        }
        return $result;
    }

    /**
     * Stub to throw PortaException in a case of it's called when not supported
     *
     * @param RequestInterface $request
     * @throws PortaException
     * @api
     */
    public function sendAsync(RequestInterface $request)
    {
        throw new PortaException("Backend HTTP client used does not support async requests");
    }
}
