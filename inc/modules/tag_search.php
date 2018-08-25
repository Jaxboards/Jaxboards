<?php

$onsubmit = 'return RUN.stream.location('.
'\'act=search&searchterm=\'+encodeURIComponent(this.searchterm.value),3)';
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
