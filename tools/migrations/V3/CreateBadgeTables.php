<?php

declare(strict_types=1);

namespace tools\migrations\V3;

use Jax\Database;

final class CreateBadgeTable
{
    public function execute(Database $database): void
    {
        $database->special(
            'CREATE TABLE %t
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `imagePath` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `badgeTitle` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Badge Title',
                `displayOrder` INT NOT NULL,
                `description` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL',
            ['badges'],
        );
	
        $database->special(
            'CREATE TABLE %t
                `id` SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user` INT NOT NULL,
                `badge` INT NOT NULL,
                `badgeCount` SMALLINT NOT NULL DEFAULT '1',
                `reason` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL,
                `awardDate` DATETIME NULL,
                KEY `user` (`user`),
                KEY `badge` (`badge`),
                CONSTRAINT `badge_associations_ibfk_1` FOREIGN KEY (`user`) REFERENCES `jaxboards_members` (`id`) ON DELETE SET NULL,
                CONSTRAINT `badge_associations_ibfk_2` FOREIGN KEY (`badge`) REFERENCES `jaxboards_badges` (`id`) ON DELETE SET NULL'
            ['badge_assocations'],
        );
    }
}