<?php

declare(strict_types=1);

namespace tools\migrations\V2;

use Jax\Database;

final class UnderScoreToCamelCase
{
    public function execute(Database $database): void
    {
        $this->fixForums($database);

        $this->fixMembers($database);

        $this->fixMemberGroups($database);

        $this->fixTopics($database);

        $this->fixSession($database);

        $database->special(
            'ALTER TABLE %t
                CHANGE `affected_uid` `affectedUser`
                    INT(10) UNSIGNED NULL DEFAULT NULL',
            ['activity'],
        );

        $database->special(
            "ALTER TABLE %t
                CHANGE `del_recipient` `deletedRecipient`
                    TINYINT(1) NOT NULL DEFAULT '0',
                CHANGE `del_sender` `deletedSender`
                    TINYINT(1) NOT NULL DEFAULT '0'",
            ['messages'],
        );

        $database->special(
            'ALTER TABLE %t
                CHANGE `auth_id` `author`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `edit_date` `editDate`
                    DATETIME NULL DEFAULT NULL',
            ['posts'],
        );
    }

    private function fixForums(Database $database): void
    {
        $database->special(
            "ALTER TABLE %t
                CHANGE `cat_id` `category`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `lp_uid` `lastPostUser`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `lp_date` `lastPostDate`
                    DATETIME NULL DEFAULT NULL,
                CHANGE `lp_tid` `lastPostTopic`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `lp_topic` `lastPostTopicTitle` VARCHAR(255)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `show_sub` `showSubForums`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `show_ledby` `showLedBy`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0'",
            ['forums'],
        );
    }

    private function fixMembers(Database $database): void
    {
        $database->special(
            "ALTER TABLE %t
                CHANGE `group_id` `groupID`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `join_date` `joinDate`
                    DATETIME NULL DEFAULT NULL,
                CHANGE `last_visit` `lastVisit`
                    DATETIME NULL DEFAULT NULL,
                CHANGE `contact_skype` `contactSkype` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `contact_yim` `contactYIM` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `contact_msn` `contactMSN` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `contact_gtalk` `contactGoogleChat` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `contact_aim` `contactAIM` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `display_name` `displayName` VARCHAR(30)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `contact_steam` `contactSteam` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `sound_shout` `soundShout`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '1',
                CHANGE `sound_im` `soundIM`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '1',
                CHANGE `sound_pm` `soundPM`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `sound_postinmytopic` `soundPostInMyTopic`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `sound_postinsubscribedtopic` `soundPostInSubscribedTopic`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `notify_pm` `notifyPM`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `notify_postinmytopic` `notifyPostInMyTopic`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `notify_postinsubscribedtopic` `notifyPostInSubscribedTopic`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `skin_id` `skinID`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `contact_twitter` `contactTwitter` VARCHAR(255)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `email_settings` `emailSettings`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `contact_discord` `contactDiscord` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `contact_youtube` `contactYoutube` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `contact_bluesky` `contactBlueSky` VARCHAR(50)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL",
            ['members'],
        );
    }

    private function fixMemberGroups(Database $database): void
    {
        $database->special(
            "ALTER TABLE %t
                CHANGE `can_post` `canPost`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_edit_posts` `canEditPosts`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_post_topics` `canCreateTopics`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_edit_topics` `canEditTopics`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_add_comments` `canAddComments`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_delete_comments` `canDeleteComments`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_view_board` `canViewBoard`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_view_offline_board` `canViewOfflineBoard`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `flood_control` `floodControl`
                    INT(10) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_override_locked_topics` `canOverrideLockedTopics`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_shout` `canShout`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_moderate` `canModerate`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_delete_shouts` `canDeleteShouts`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_delete_own_shouts` `canDeleteOwnShouts`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_karma` `canKarma`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_im` `canIM`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_pm` `canPM`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_lock_own_topics` `canLockOwnTopics`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_delete_own_topics` `canDeleteOwnTopics`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_use_sigs` `canUseSignatures`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_attach` `canAttach`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_delete_own_posts` `canDeleteOwnPosts`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_poll` `canPoll`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_access_acp` `canAccessACP`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_view_shoutbox` `canViewShoutbox`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_view_stats` `canViewStats`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `can_view_fullprofile` `canViewFullProfile`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '1'",
            ['member_groups'],
        );
    }

    private function fixTopics(Database $database): void
    {
        $database->special(
            "ALTER TABLE %t
                CHANGE `lp_uid` `lastPostUser`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `lp_date` `lastPostDate`
                    DATETIME NULL DEFAULT NULL,
                CHANGE `auth_id` `author`
                    INT(10) UNSIGNED NULL DEFAULT NULL,
                CHANGE `poll_choices` `pollChoices` MEDIUMTEXT
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `poll_results` `pollResults` MEDIUMTEXT
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `poll_q` `pollQuestion` VARCHAR(255)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `poll_type` `pollType` ENUM('','single','multi')
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `cal_event` `calendarEvent`
                    INT(10) UNSIGNED NOT NULL DEFAULT '0'",
            ['topics'],
        );
    }

    private function fixSession(Database $database): void
    {
        $database->special(
            "ALTER TABLE %t
                CHANGE `last_update` `lastUpdate`
                    DATETIME NULL DEFAULT NULL,
                CHANGE `last_action` `lastAction`
                    DATETIME NULL DEFAULT NULL,
                CHANGE `users_online_cache` `usersOnlineCache` TEXT
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `is_bot` `isBot`
                    TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                CHANGE `buddy_list_cache` `buddyListCache` TEXT
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `location_verbose` `locationVerbose` VARCHAR(128)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE `read_date` `readDate`
                    DATETIME NULL DEFAULT NULL",
            ['session'],
        );
    }
}
