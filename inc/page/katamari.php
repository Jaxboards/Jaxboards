<?php

declare(strict_types=1);

namespace Page;

final class Katamari
{
    public function route(): void
    {
        global $PAGE;

        $PAGE->JS('loadscript', './Script/katamari.js');
        $PAGE->JS('softurl');
    }
}
