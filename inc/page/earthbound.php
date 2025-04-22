<?php

declare(strict_types=1);

namespace Page;

final class Earthbound
{
    public function route(): void
    {
        global $PAGE;
        $PAGE->JS('softurl');
        $PAGE->JS('loadscript', './Script/earthbound.js');
        $PAGE->JS('playsound', 'earthbound', './Sounds/earthboundbattle.mp3');
    }
}
