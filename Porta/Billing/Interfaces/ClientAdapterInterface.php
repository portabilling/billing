<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Porta\Billing\Exceptions\PortaConnectException;

/**
 * Interface for HTTP client adapters
 *
 * @package ClientAdaptors
 * @api
 */
interface ClientAdapterInterface
{

    /**
     * Methd to perform HTTP call
     *
     * This method should accept request, complete the call and return response.
     *
     * Any client-specific exceptions must be catched and converted to
     * {@see PortaConnectException} to throw.
     *
     * The client **must** return Response objects with non-200 HTTP return codes
     * instead of throw an error.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws PortaConnectException
     * @api
     */
    public function send(RequestInterface $request): ResponseInterface;

    /**
     * Backs bulk async billig call, running multiple concurrent requests at once.
     *
     * Takes array/iterable of requests and returns array of results, either good
     * (ResponceInterface object) or bad (PortaException exception)
     *
     * **Array keys must be preserved!**
     *
     * The adaptor **must** return Response objects with non-200 HTTP return codes
     * instead of excepton of HTTP errors
     *
     * @param iterable $requests keyed iterable of requests to render in parallel
     *
     * @param int $concurency how much calls to run in parallel. Default is 20.
     *
     * @api
     */
    public function concurrent(iterable $requests, int $concurency = 20): array;

    /**
     * Performs Client-specific async request and return promise
     *
     * As promise have no standard yet, rely in most adopted Promise/A which seems
     * to be about the same for the most popular implementation like React and Guzzle,
     * so future processing rely on presence of then() method regardles of implementation.
     *
     * Future processing rely on promise:
     * - Fulfilled with ResponseInterface, got from billing sserver on success
     * - Rejected with **one of PortaException childs** on fail
     * Also, the adapter class have to wrap the promise it got form client to
     * another promise which will rework client-dependent exceptions into PortaException
     * class exceptions.
     *
     * The promise **must** fulfil Response objects with non-200 HTTP return codes
     * instead of reject with exceptions on HTTP errors. Some clients may return
     * custom exceptions on HTTP erors, containing request object, so this excepton
     * must be reworked to promise fulfill with Respose object.
     *
     * @param RequestInterface $request request to send in async mode
     * @return mixed Client-dependent promise object.
     *
     * It must fulfill with ResponseInterface or reject with PortaException or
     * it's child.
     *
     * @api
     */
    public function sendAsync(RequestInterface $request);
}
