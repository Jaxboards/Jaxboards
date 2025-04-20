<?php

declare(strict_types=1);

final class Earthbound
{
    public function route() {
        global $PAGE;
        $PAGE->JS('softurl');
        $PAGE->JS('script', "var s=document.createElement('script');s.src='./Script/earthbound.js';document.body.appendChild(s);");
        $PAGE->JS('playsound', 'earthbound', './Sounds/earthboundbattle.mp3');

    }
}

?>
