<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace Porta\Billing\Exceptions;

/**
 * Implementation of FileCacheException to throw in a case of cache problems
 *
 * @package Cache
 * @api
 */
class CacheException extends \Exception implements \Psr\SimpleCache\CacheException
{

}
