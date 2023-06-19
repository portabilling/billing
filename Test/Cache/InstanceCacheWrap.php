<?php

/*
 * PortaOne Billing JSON API wrapper
 * API docs: https://docs.portaone.com
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace PortaApiTest\Cache;

/**
 * Description of InstanceCacheWrap
 *
 */
class InstanceCacheWrap extends \Porta\Billing\Cache\InstanceCache
{

    public array $data = [];
}
