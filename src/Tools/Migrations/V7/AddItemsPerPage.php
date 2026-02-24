<?php

declare(strict_types=1);

namespace Tools\Migrations\V7;

use Override;
use Tools\Migrations\Migration;

final class AddItemsPerPage extends Migration
{
    #[Override]
    public function execute(): void
    {
        $this->database->special(<<<'SQL'
            ALTER TABLE %t ADD `itemsPerPage` INT NULL DEFAULT NULL
            SQL, ['members']);

        // Fix this naming while we're here
        $this->database->special(<<<'SQL'
            ALTER TABLE %t CHANGE `full_name` `fullName` varchar(50) NOT NULL DEFAULT ''
            SQL, ['members']);
    }
}
