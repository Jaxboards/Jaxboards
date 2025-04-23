<?php

declare(strict_types=1);

namespace Jax\Page;

final class Asteroids
{
    public function route(): void
    {
        global $PAGE;
        $PAGE->JS('loadscript', './Script/asteroids.min.js');
        $PAGE->JS('softurl');
    }
}
