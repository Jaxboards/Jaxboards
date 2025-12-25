<?php

declare(strict_types=1);

namespace Jax;

use GeoIp2\Database\Reader;
use GeoIp2\Model\City;

final readonly class GeoLocate
{
    private Reader $cityReader;

    public function __construct(FileSystem $fileSystem)
    {
        $this->cityReader = new Reader($fileSystem->pathFromRoot('Service\maxmind\GeoLite2-City_20251223\GeoLite2-City.mmdb'));
    }

    public function lookup(string $ip): City
    {
        return $this->cityReader->city($ip);
    }
}
