<?php

declare(strict_types=1);
final class Katamari
{
    public function route(): void
    {
        global $PAGE;

        $PAGE->JS('script', "var i,s,ss=['./Script/katamari.js'];for(i=0;i!=ss.length;i++){s=document.createElement('script');s.src=ss[i];document.body.appendChild(s);}void(0);");
        $PAGE->JS('softurl');
    }
}
