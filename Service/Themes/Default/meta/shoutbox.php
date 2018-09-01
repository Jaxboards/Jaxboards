<?php

$meta = array(
    'shoutbox' => <<<'EOT'
<form method="post" action="" onsubmit="return RUN.submitForm(this,1)">
    <table>
        <tr>
            <td class="shouts">
                %s
            </td>
            <td class="shoutform">
                <input type="text" name="shoutbox_shout"/>
                <input type="submit" value="Shout" />
            </td>
        </tr>
    </table>
</form>
EOT
    ,
    'shoutbox-title' => <<<'EOT'
Shoutbox -
<a href="?module=shoutbox">
    History
</a>
EOT
    ,
    'shout' => <<<'EOT'
<div class="shout" title="%1$s">
    %5$s %2$s : %3$s%4$s
</div>
EOT
    ,
    'shout-delete' => <<<'EOT'
<a href="?module=shoutbox&amp;shoutbox_delete=%s" class="delete"
    onclick="this.parentNode.style.display='none'" title="delete">
    [X]
</a>
EOT
    ,
    'shout-action' => <<<'EOT'
<div class="shout action" title="%1$s">
    ***%2$s %3$s%4$s
</div>
EOT
    ,
);
