<?php

declare(strict_types=1);

namespace Tools;

/**
 * @internal
 */
interface CLIRoute
{
    /**
     * @param array<string,string> $params
     */
    public function route(array $params): void;
}
