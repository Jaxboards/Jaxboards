<?php

declare(strict_types=1);

namespace Jax;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;

use function array_map;
use function implode;
use function mb_chr;
use function mb_ord;

final readonly class GeoLocate
{
    private Reader $cityReader;

    public function __construct(FileSystem $fileSystem)
    {
        $this->cityReader = new Reader($fileSystem->pathFromRoot('maxmind/GeoLite2-City_20251223/GeoLite2-City.mmdb'));
    }

    public function lookup(string $ip): ?City
    {
        try {
            return $this->cityReader->city($ip);
        } catch (AddressNotFoundException) {
            return null;
        }
    }

    public function getFlagEmoji(?string $isoCode): string
    {
        if (!$isoCode) {
            return '';
        }

        $asciiA = 65;
        $letterA = 127_462;

        $letters = array_map(
            static fn($letter): string => mb_chr(mb_ord($letter) - $asciiA + $letterA),
            [
                $isoCode[0],
                $isoCode[1],
            ],
        );

        return implode('', $letters);
    }
}
