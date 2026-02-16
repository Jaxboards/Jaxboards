<?php

declare(strict_types=1);

namespace Tools\Migrations\V5;


use Override;
use Tools\Migrations\Migration;

final class AddOpenGraphFields extends Migration
{
    #[Override]
    public function execute(): void
    {
        $this->database->special("ALTER TABLE %t ADD `openGraphMetadata` JSON NOT NULL DEFAULT ('{}')", ['posts']);
    }
}
