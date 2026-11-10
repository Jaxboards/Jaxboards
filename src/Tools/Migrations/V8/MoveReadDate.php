<?php

declare(strict_types=1);

namespace Tools\Migrations\V8;

use Override;
use Tools\Migrations\Migration;

final class MoveReadDate extends Migration
{
    #[Override]
    public function execute(): void
    {
        $this->database->special(<<<'SQL'
            ALTER TABLE %t ADD `readDate` datetime NULL DEFAULT NULL
            SQL, ['members']);

        // Fix this naming while we're here
        $this->database->special(<<<'SQL'
            ALTER TABLE %t DROP `readDate`
            SQL, ['session']);
    }
}
