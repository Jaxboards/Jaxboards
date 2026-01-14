<?php

declare(strict_types=1);

namespace Tools\Migrations\V6;

use Jax\Database\Database;

final class CreateReportsTable
{
    public function execute(Database $database): void
    {
        $database->special(
            <<<'SQL'
                CREATE TABLE %t (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `pid` int unsigned,
                    `reason` varchar(25) DEFAULT 'other',
                    `note` varchar(100) DEFAULT '',
                    `reporter` int unsigned NOT NULL,
                    `reportDate` datetime,
                    `acknowledger` int unsigned,
                    `acknowledgedDate` datetime,
                    PRIMARY KEY (`id`),
                    KEY `pid` (`pid`),
                    KEY `reporter` (`reporter`),
                    KEY `reportDate` (`reportDate`),
                    KEY `acknowledger` (`acknowledger`),
                    CONSTRAINT `reports_fk_pid`
                        FOREIGN KEY (`pid`)
                        REFERENCES %t (`id`)
                        ON DELETE CASCADE,
                    CONSTRAINT `reports_fk_reporter`
                        FOREIGN KEY (`reporter`)
                        REFERENCES %t (`id`)
                        ON DELETE CASCADE,
                    CONSTRAINT `reports_fk_acknowledger`
                        FOREIGN KEY (`acknowledger`)
                        REFERENCES %t (`id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
            ['reports', 'posts', 'members', 'members']
        );
    }
}
