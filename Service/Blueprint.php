<?php

namespace Service;

class Blueprint {
    /**
     * @return array<string> lines of the SQL schema file
     */
    public function getSchema(): array
    {
        return (array) file(__DIR__ . '/schema.sql');
    }

    public function getDirectory(): string
    {
        return __DIR__ . '/blueprint';
    }
}
