<?php

declare(strict_types=1);
final class Katamari
{
    public function route(): void
    {
        global $PAGE;

        $PAGE->JS('loadscript', './Script/katamari.js');
        $PAGE->JS('softurl');
    }
}
