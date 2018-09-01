<?php

$onsubmit = <<<'EOT'
return RUN.stream.location(
    'act=search&searchterm='+encodeURIComponent(this.searchterm.value),
    3
);
EOT;

$PAGE->append(
    'search',
    <<<EOT
<form autocomplete="off"
    onsubmit="${onsubmit}">
    <input type="text" name="searchterm" />
    <input type="submit" value="Search" />
</form>
EOT
);
