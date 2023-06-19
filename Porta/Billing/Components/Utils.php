<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Components;

use Psr\Http\Message\ResponseInterface;

/**
 * Utility class
 *
 * @internal
 */
class Utils
{

    public static function makeApiJson(array $params)
    {
        $params = ([] == $params) ? new \stdClass() : $params;
        return json_encode(['params' => $params]);
    }

    public static function jsonResponse(ResponseInterface $response): array
    {
        if (0 == $response->getBody()->getSize()) {
            return [];
        }
        $result = json_decode($response->getBody(), true);
        if (is_null($result)) {
            throw new \Porta\Billing\Exceptions\PortaException("Can't decode returned JSON");
        }
        return $result;
    }
}
