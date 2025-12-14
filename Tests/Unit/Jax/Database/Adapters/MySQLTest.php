<?php

declare(strict_types=1);

namespace Tests\Unit\Jax\Database\Adapters;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Database\Adapters\MySQL;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Models\Member;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

/**
 * @internal
 */
#[CoversClass(MySQL::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(Database::class)]
#[CoversClass(Model::class)]
#[CoversClass(ServiceConfig::class)]
#[Small]
final class MySQLTest extends UnitTestCase
{
    private MySQL $mySQL;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mySQL = $this->container->get(MySQL::class);
    }

    public function testCreateTableQueryFromModel(): void
    {
        $createTable = $this->mySQL->createTableQueryFromModel(new Member());

        $this->assertEquals(<<<'SQL'
            CREATE TABLE `jaxboards_members` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(50) NOT NULL,
                `pass` varchar(255) NOT NULL DEFAULT '',
                `email` varchar(50) NOT NULL DEFAULT '',
                `sig` text NOT NULL DEFAULT '',
                `posts` int unsigned NOT NULL DEFAULT '0',
                `groupID` int unsigned,
                `avatar` varchar(255) NOT NULL DEFAULT '',
                `usertitle` varchar(255) NOT NULL DEFAULT '',
                `joinDate` datetime,
                `lastVisit` datetime,
                `contactSkype` varchar(50) NOT NULL DEFAULT '',
                `contactYIM` varchar(50) NOT NULL DEFAULT '',
                `contactMSN` varchar(50) NOT NULL DEFAULT '',
                `contactGoogleChat` varchar(50) NOT NULL DEFAULT '',
                `contactAIM` varchar(50) NOT NULL DEFAULT '',
                `website` varchar(255) NOT NULL DEFAULT '',
                `birthdate` date,
                `about` text NOT NULL DEFAULT '',
                `displayName` varchar(30) NOT NULL DEFAULT '',
                `full_name` varchar(50) NOT NULL DEFAULT '',
                `contactSteam` varchar(50) NOT NULL DEFAULT '',
                `location` varchar(100) NOT NULL DEFAULT '',
                `gender` varchar(10) NOT NULL DEFAULT '',
                `friends` text NOT NULL DEFAULT '',
                `enemies` text NOT NULL DEFAULT '',
                `soundShout` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `soundIM` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `soundPM` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `soundPostInMyTopic` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `soundPostInSubscribedTopic` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `notifyPM` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `notifyPostInMyTopic` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `notifyPostInSubscribedTopic` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `ucpnotepad` varchar(2000) NOT NULL DEFAULT '',
                `skinID` int unsigned,
                `contactTwitter` varchar(50) NOT NULL DEFAULT '',
                `contactDiscord` varchar(50) NOT NULL DEFAULT '',
                `contactYoutube` varchar(50) NOT NULL DEFAULT '',
                `contactBlueSky` varchar(50) NOT NULL DEFAULT '',
                `emailSettings` tinyint unsigned NOT NULL DEFAULT '0',
                `nowordfilter` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `ip` binary(16) NOT NULL DEFAULT '',
                `mod` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `wysiwyg` tinyint(1) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `groupID` (`groupID`),
                KEY `displayName` (`displayName`),
                CONSTRAINT `members_fk_groupID`
                    FOREIGN KEY (`groupID`)
                    REFERENCES `jaxboards_member_groups` (`id`)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
            , $createTable);
    }
}
