<?php

declare(strict_types=1);
final class Asteroids
{
    public function route(): void
    {
        global $PAGE;
        $PAGE->JS('script', "var s=document.createElement('script');s.type='text/javascript';document.body.appendChild(s);s.src='./Script/asteroids.min.js';");
        $PAGE->JS('softurl');
    }
}
