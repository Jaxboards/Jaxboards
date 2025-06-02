<?php

declare(strict_types=1);

namespace tools\migrations\V3;

use Jax\Database;

final class CreateBadgeTables
{
    public function execute(Database $database): void
    {
        $database->special(
            "CREATE TABLE %t (
                `id` INT unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `imagePath` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `badgeTitle` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Badge Title',
                `displayOrder` INT NOT NULL,
                `description` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL
            )",
            ['badges'],
        );

        $database->special(
            "CREATE TABLE %t (
                `id` INT unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user` INT unsigned NOT NULL,
                `badge` INT unsigned NOT NULL,
                `badgeCount` SMALLINT NOT NULL DEFAULT '1',
                `reason` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL,
                `awardDate` DATETIME NULL,
                KEY `user` (`user`),
                KEY `badge` (`badge`),
                CONSTRAINT `badge_associations_ibfk_1` FOREIGN KEY (`user`) REFERENCES %t (`id`) ON DELETE CASCADE,
                CONSTRAINT `badge_associations_ibfk_2` FOREIGN KEY (`badge`) REFERENCES %t (`id`) ON DELETE CASCADE
            )",
            ['badge_assocations', 'members', 'badges'],
        );
    }
}
