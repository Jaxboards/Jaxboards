
<?php

final class Tardis
{
    public function route(): void
    {
        global $PAGE;

        $PAGE->JS('softurl');
        $PAGE->JS('script', '(function() {
            if (window.tardis) {
                return;
            }
            window.tardis = function() {
                var i = document.getElementById("tardis"),
                    l, t, sw = document.documentElement.clientWidth,
                    sh = document.documentElement.clientHeight;
                if (!i) {
                    i = new Image;
                    i.src = "tardis.gif";
                    i.id = "tardis";
                    document.body.appendChild(i);
                    i.style.position = "fixed";
                    i.style.top = "0px";
                    i.style.left = "0px";
                }
                if (!i.vy) {
                    i.vy = i.vx = 1;
                    i.style.position = "fixed";
                    var c = i.getBoundingClientRect();
                    i.style.left = c.x + "px";
                    i.style.top = c.y + "px";
                }
                t = parseInt(i.style.top) + i.vy;
                l = parseInt(i.style.left) + i.vx;
                if ((t + i.clientHeight) > sh || t < 0) i.vy *= -1;
                if ((l + i.clientWidth) > sw || l < 0) i.vx *= -1;
                i.style.top = t + "px";
                i.style.left = l + "px";
            }
            setInterval(window.tardis, 10);
        })()');
        $PAGE->JS('playsound', 'drwho', './Sounds/doctorwhotheme.mp3');
    }
}
