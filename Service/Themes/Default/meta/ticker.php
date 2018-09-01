<?php

$meta = array(
    'ticker' => <<<'EOT'
<div class="box">
    <div class="title">
        Ticker!
    </div>
    <div class="content_top">
    </div>
    <div class="content">
        <div id="ticker">
            %s
        </div>
    </div>
    <div class="content_bottom">
    </div>
</div>
EOT
    ,
    'ticker-tick' => <<<'EOT'
<div class="tick">
    <div class="date">
        %1$s
    </div>
    <div class="by">
        %2$s
    </div>
    <a href="?act=vt%3$s&amp;findpost=%4$s">
        %5$s
    </a>
</div>
EOT
    ,
);
