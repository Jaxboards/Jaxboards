<?php

$meta = array(
    'search-form' => <<<'EOT'
<div class="box" id="searchform">
    <div class="title">
        Board Search
    </div>
    <div class="content_top">
    </div>
    <div class="content">
        <form method="post" onsubmit="return RUN.submitForm(this)">
            <input type="hidden" name="act" value="search" />
            <label for="searchterm">
                Search Terms:
            </label>
            <input type="text" id="searchterm" name="searchterm" value="%1$s" />
            <input type="submit" value="Search" />
            &nbsp; &nbsp;
            <a href="?" onclick="JAX.toggle(document.querySelector('#searchadvanced'));return false;">
                Advanced
            </a>
            <table id="searchadvanced" style="display:none">
                <tr>
                    <th>
                        In forum(s):
                    </th>
                    <th>
                        Date Range
                    </th>
                    <th>
                        Filters:
                    </th>
                </tr>
                <tr>
                    <td>
                        %2$s
                    </td>
                    <td>
                        <label>
                            From:
                        </label>
                        <input type="text" class="date" name="datestart" />
                        <br />
                        <label>
                            To:
                        </label>
                        <input type="text" class="date" name="dateend" />
                    </td>
                    <td>
                        Posted by:
                        <input type="text"
                            onkeyup="
                                return JAX.autoComplete(
                                    'act=searchmembers&amp;term='+this.value,
                                    this,
                                    document.querySelector('#mid'),
                                    event
                                );
                             " />
                        <input type="hidden" id="mid" name="mid" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
    <div class="content_bottom"></div>
</div>
<div id="searchresults">
    %3$s
</div>
EOT
    ,
    'search-result' => <<<'EOT'
<div class="searchresult">
    <a class="topic" href="?act=vt%1$s">
        %2$s
    </a>
    <a href="?act=vt%1$s&amp;findpost=%3$s">
        (Post)
    </a>
    <br />
    %4$s
</div>
EOT
    ,
    'search-highlight' => <<<'EOT'
<span class="highlight">
    %s
</span>
EOT
    ,
);
