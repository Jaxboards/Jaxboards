<?php

$PAGE->append(
    'search',
    <<<EOT
<form autocomplete="off" data-ajax-form="true">
    <input type="text" name="searchterm" />
    <input type="submit" value="Search" />
</form>
EOT
);
