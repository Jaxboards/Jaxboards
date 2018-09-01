<?php

$meta = array(
    'calendar' => <<<'EOT'
<table class="calendar">
    %s
</table>
EOT
    ,
    'calendar-heading' => <<<'EOT'
<tr>
    <th>
        <a href="?act=calendar&amp;month=%3$s">
            &lt;
        </a>
    </th>
    <th colspan="5" class="month">
        %1$s %2$s
    </th>
    <th>
        <a href="?act=calendar&amp;month=%4$s">
            &gt;
        </a>
    </th>
</tr>
EOT
    ,
    'calendar-padding' => <<<'EOT'
<td colspan="%s">
</td>
EOT
    ,
    'calendar-daynames' => <<<'EOT'
<tr>
    <th>
        Sun
    </th>
    <th>
        Mon
    </th>
    <th>
        Tue
    </th>
    <th>
        Wed
    </th>
    <th>
        Thu
    </th>
    <th>
        Fri
    </th>
    <th>
        Sat
    </th>
</tr>
EOT
    ,
    'calendar-week' => <<<'EOT'
<tr>
    %s
</tr>
EOT
    ,
    'calendar-day' => <<<'EOT'
<td class="%s">
    <div>
        %s %s
    </div>
</td>
EOT
    ,
    'calendar-birthdays' => <<<'EOT'
<div class="birthdays">
    %s
</div>
EOT
    ,
);
