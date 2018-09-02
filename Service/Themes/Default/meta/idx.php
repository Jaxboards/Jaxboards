<?php

$meta = array(
    'idx-table' => <<<'EOT'
<table class="boardindex">
    %s
</table>
EOT
    ,
    'idx-redirect-row' => <<<'EOT'
<tr>
    <td class="f_icon">
        %5$s
    </td>
    <td class="forum">
        <a href="?act=vf%1$s">
            %2$s
        </a>
        <div class="description">
            %3$s
        </div>
    </td>
    <td class="last_post" colspan="2">
        %4$s
    </td>
</tr>
EOT
    ,
    'idx-row' => <<<'EOT'
<tr id="fid_%1$s" class="%8$s">
    <td class="f_icon" rowspan="2">
        %9$s
    </td>
    <td class="forum" rowspan="2">
        <a href="?act=vf%1$s">
            %2$s
        </a>
        <div class="description">
            %3$s
        </div>
        %4$s%10$s
    </td>
    <td class="last_post" id="fid_%1$s_lastpost" colspan="2">
        %5$s
    </td>
</tr>
<tr>
    <td class="item_1" id="fid_%1$s_topics">
        %6$s
    </td>
    <td class="item_2" id="fid_%1$s_replies">
        %7$s
    </td>
</tr>
EOT
    ,
    'idx-topics-count' => 'Topics: %s',
    'idx-replies-count' => 'Replies: %s',
    'idx-subforum-link' => <<<'EOT'
<a href="?act=vf%1$s" title="%3$s" onmouseover="JAX.tooltip(this)">
    %2$s
</a>
EOT
    ,
    'idx-subforum-splitter' => ', ',
    'idx-subforum-wrapper' => <<<'EOT'
<div class="subforums">
    Subforums: %s
</div>
EOT
    ,
    'idx-ledby-wrapper' => <<<'EOT'
<div class="ledby">
    Led By: %s
</div>
EOT
    ,
    'idx-ledby-splitter' => ', ',
    'idx-row-lastpost' => <<<'EOT'
Last Post: <a href="?act=vt%s&amp;getlast=1">%s</a><br />
By %s, %s
EOT
    ,
    'idx-stats' => <<<'EOT'
<div class="box" id="stats">
    <div class="title">
        <div class="x" onclick="JAX.collapse(this.parentNode.nextSibling)">
            -/+
        </div>
        Statistics
    </div>
    <div class="collapse_content">
        <div class="content_top">
        </div>
        <div class="content">
            %1$s User{if %1$s!=1}s{/if} Online:
            <div id="statusers">
                %2$s{if %1$s!=0&&%3$s>0},
                {/if}{if %3$s!=0}%3$s guest{/if}{if %3$s!=1&&%3$s!=0}s{/if}
            </div>
            <div class="userstoday">
                %4$s User{if %4$s!=1}s{/if} Online Today: %5$s <br />
                %10$s
            </div>
            <div class="stats">
                Our <strong>%6$s</strong> users have made
                <strong>%7$s</strong> topics with <strong>%8$s</strong> posts.
                Newest Member: %9$s
            </div>
        </div>
        <div class="content_bottom">
        </div>
    </div>
</div>
EOT
    ,
    'idx-icon-unread' => '<img src="' . BOARDURL .
        'Service/Themes/Default/icons/unread.png" alt="unread" />',
    'idx-icon-read' => '<img src="' . BOARDURL .
        'Service/Themes/Default/icons/read.png" alt="read" />',
    'idx-icon-redirect' => '<img src="' . BOARDURL .
        'Service/Themes/Default/icons/redirect.png" alt="redirect" />',
    'idx-tools' => <<<'EOT'
<div class="idxtools">
    <a href="?act=idx&amp;markread=1">
        Mark Everything Read
    </a>
    |
    <a href="?act=members&amp;filter=staff&amp;sortby=g_title">
        View Staff
    </a>
</div>
EOT
    ,
);
