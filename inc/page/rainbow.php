<?php

declare(strict_types=1);
final class Rainbow
{
    public function route(): void
    {
        global $PAGE;
        $PAGE->JS('softurl');
        $PAGE->JS('script', "(function() {
            let i = 0;
            if (window.rainbow) {
                clearInterval(window.rainbow);
            } else {
                window.rainbow=setInterval(() => document.documentElement.style.filter = 'hue-rotate(' + (++i) + 'deg)', 1000/60);
            }
        })()");
    }
}
