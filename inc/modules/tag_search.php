<?php

$PAGE->append('search', '<form autocomplete="off" onsubmit="return RUN.stream.location(\'act=search&searchterm=\'+encodeURIComponent(this.searchterm.value),3)"><input type="text" name="searchterm" /><input type="submit" value="Search" /></form>');
