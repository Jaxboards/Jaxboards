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

    public function getFlagEmoji(string $isoCode): string
    {
        $asciiA = 65;
        $letterA = 127462;

        $letters = array_map(
            fn($letter) => mb_chr(ord($letter) - $asciiA + $letterA),
            [
                $isoCode[0],
                $isoCode[1],
            ]
        );

        return implode('', $letters);
    }
}
