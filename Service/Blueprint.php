<?php

declare(strict_types=1);

namespace Service;

final class Blueprint
{
    public function getDirectory(): string
    {
        return __DIR__ . '/blueprint';
    }
}
