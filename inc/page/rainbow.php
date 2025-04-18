<?php
class Rainbow {
    public function route() {
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
?>
