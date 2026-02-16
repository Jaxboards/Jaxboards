<?php

declare(strict_types=1);

namespace Tools\Migrations\V1;

use Override;
use Tools\Migrations\Migration;

final class AddVersionField extends Migration
{
    #[Override]
    public function execute(): void
    {
        $this->database->special('ALTER TABLE %t ADD COLUMN dbVersion int', ['stats']);
    }
}
