<?php

declare(strict_types=1);

namespace Jax\Page;

final class Katamari
{
    public function route(): void
    {
        global $PAGE;

        $PAGE->JS('loadscript', './Script/katamari.js');
        $PAGE->JS('softurl');
    }
}
