<?php

namespace tools\migrations\V1;

use Jax\Database;

class AddVersionField {
    public function execute(Database $database) {
        $database->special(
            "ALTER TABLE %t ADD COLUMN dbVersion int",
            ['stats']
        );
    }
}
