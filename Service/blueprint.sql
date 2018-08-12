SET NAMES 'utf8mb4';
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `blueprint_activity`;
CREATE TABLE `blueprint_activity` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('new_post','new_topic','buddy_add',
    'buddy_block','buddy_status',
    'profile_name_change','profile_comment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `arg1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uid` int(11) unsigned NOT NULL,
  `date` int(11) unsigned NOT NULL DEFAULT 0,
  `affected_uid` int(11) unsigned DEFAULT NULL,
  `tid` int(11) unsigned DEFAULT NULL,
  `pid` int(11) unsigned DEFAULT NULL,
  `arg2` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `affected_uid` (`affected_uid`),
  KEY `tid` (`tid`),
  KEY `pid` (`pid`),
  CONSTRAINT `blueprint_activity_ibfk_1` FOREIGN KEY (`uid`) REFERENCES
    `blueprint_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blueprint_activity_ibfk_2` FOREIGN KEY (`affected_uid`) REFERENCES
    `blueprint_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blueprint_activity_ibfk_3` FOREIGN KEY (`tid`) REFERENCES
    `blueprint_topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blueprint_activity_ibfk_4` FOREIGN KEY (`pid`) REFERENCES
    `blueprint_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_activity`;

DROP TABLE IF EXISTS `blueprint_categories`;
CREATE TABLE `blueprint_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_categories`;
INSERT INTO `blueprint_categories` (`id`, `title`, `order`) VALUES
(1,	'Category',	1);

DROP TABLE IF EXISTS `blueprint_chatrooms`;
CREATE TABLE `blueprint_chatrooms` (
  `id` varchar(32) CHARACTER SET utf8 NOT NULL,
  `userdata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_chatrooms`;

DROP TABLE IF EXISTS `blueprint_files`;
CREATE TABLE `blueprint_files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` int(11) unsigned DEFAULT NULL,
  `size` int(11) unsigned NOT NULL DEFAULT 0,
  `downloads` int(11) unsigned NOT NULL DEFAULT 0,
  `ip` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `ip` (`ip`),
  CONSTRAINT `blueprint_files_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_files`;

DROP TABLE IF EXISTS `blueprint_forums`;
CREATE TABLE `blueprint_forums` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cat_id` int(11) unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lp_uid` int(11) unsigned DEFAULT NULL,
  `lp_date` int(11) unsigned NOT NULL DEFAULT 0,
  `lp_tid` int(11) unsigned DEFAULT NULL,
  `lp_topic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `path` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `show_sub` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `redirect` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `topics` int(11) unsigned NOT NULL DEFAULT 0,
  `posts` int(11) unsigned NOT NULL DEFAULT 0,
  `order` int(11) unsigned NOT NULL DEFAULT 0,
  `perms` varbinary(48) NOT NULL DEFAULT '',
  `orderby` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `nocount` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `redirects` int(10) unsigned NOT NULL DEFAULT 0,
  `trashcan` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `mods` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `show_ledby` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cat_id` (`cat_id`),
  KEY `lp_uid` (`lp_uid`),
  KEY `lp_tid` (`lp_tid`),
  CONSTRAINT `blueprint_forums_ibfk_1` FOREIGN KEY (`cat_id`) REFERENCES `blueprint_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_forums_ibfk_2` FOREIGN KEY (`lp_uid`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_forums_ibfk_3` FOREIGN KEY (`lp_tid`) REFERENCES `blueprint_topics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_forums`;
INSERT INTO `blueprint_forums` (`id`, `cat_id`, `title`, `subtitle`, `lp_uid`, `lp_date`, `lp_tid`, `lp_topic`, `path`, `show_sub`, `redirect`, `topics`, `posts`, `order`, `perms`, `orderby`, `nocount`, `redirects`, `trashcan`, `mods`, `show_ledby`) VALUES
(1,	1,	'Forum',	'Your very first forum!',	1,	1286422846,	1,	'Welcome to jaxboards!',	'',	0,	'',	1,	1,	0,	UNHEX(''),	0,	0,	0,	0,	'',	0);

DROP TABLE IF EXISTS `blueprint_logs`;
CREATE TABLE `blueprint_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `ip` int(11) unsigned NOT NULL DEFAULT 0,
  `uid` int(11) unsigned DEFAULT NULL,
  `time` int(11) unsigned NOT NULL DEFAULT 0,
  `action` int(11) unsigned NOT NULL DEFAULT 0,
  `data` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `code` (`code`),
  KEY `uid` (`uid`),
  CONSTRAINT `blueprint_logs_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_logs`;

DROP TABLE IF EXISTS `blueprint_members`;
CREATE TABLE `blueprint_members` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pass` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sig` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `posts` int(11) unsigned NOT NULL DEFAULT 0,
  `group_id` int(11) unsigned DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usertitle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `join_date` int(11) unsigned NOT NULL DEFAULT 0,
  `last_visit` int(11) unsigned NOT NULL DEFAULT 0,
  `contact_skype` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact_yim` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact_msn` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact_gtalk` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact_aim` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `website` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dob_day` tinyint(4) unsigned NOT NULL DEFAULT 0,
  `dob_month` tinyint(4) unsigned NOT NULL DEFAULT 0,
  `dob_year` int(11) unsigned NOT NULL DEFAULT 0,
  `about` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `display_name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `full_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact_steam` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `location` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `gender` enum('','male','female','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `friends` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `enemies` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sound_shout` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `sound_im` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `sound_pm` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `sound_postinmytopic` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `sound_postinsubscribedtopic` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `notify_pm` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `notify_postinmytopic` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `notify_postinsubscribedtopic` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `ucpnotepad` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `skin_id` int(11) unsigned DEFAULT NULL,
  `contact_twitter` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email_settings` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `nowordfilter` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `ip` int(10) unsigned DEFAULT NULL,
  `mod` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `wysiwyg` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `display_name` (`display_name`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `blueprint_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `blueprint_member_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_members`;

DROP TABLE IF EXISTS `blueprint_member_groups`;
CREATE TABLE `blueprint_member_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_post` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_edit_posts` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_post_topics` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_edit_topics` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_add_comments` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_delete_comments` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_view_board` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_view_offline_board` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `flood_control` int(11) unsigned NOT NULL DEFAULT 0,
  `can_override_locked_topics` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `can_shout` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_moderate` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_delete_shouts` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_delete_own_shouts` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_karma` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_im` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_pm` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_lock_own_topics` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_delete_own_topics` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_use_sigs` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_attach` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_delete_own_posts` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_poll` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_access_acp` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_view_shoutbox` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `can_view_stats` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `legend` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_member_groups`;
INSERT INTO `blueprint_member_groups` (`id`, `title`, `can_post`, `can_edit_posts`, `can_post_topics`, `can_edit_topics`, `can_add_comments`, `can_delete_comments`, `can_view_board`, `can_view_offline_board`, `flood_control`, `can_override_locked_topics`, `icon`, `can_shout`, `can_moderate`, `can_delete_shouts`, `can_delete_own_shouts`, `can_karma`, `can_im`, `can_pm`, `can_lock_own_topics`, `can_delete_own_topics`, `can_use_sigs`, `can_attach`, `can_delete_own_posts`, `can_poll`, `can_access_acp`, `can_view_shoutbox`, `can_view_stats`, `legend`) VALUES
(1,	'Member',	1,	1,	1,	1,	0,	0,	1,	0,	0,	0,	'',	1,	0,	0,	0,	1,	1,	1,	0,	0,	1,	0,	0,	0,	0,	1,	1,	0),
(2,	'Admin',	1,	1,	1,	1,	1,	1,	1,	1,	0,	1,	'',	1,	1,	1,	1,	1,	1,	1,	1,	1,	1,	0,	0,	0,	1,	1,	1,	0),
(3,	'Guest',	0,	0,	0,	0,	0,	0,	1,	0,	0,	0,	'',	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	1,	1,	0),
(4,	'Banned',	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	'',	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0),
(5,	'Validating',	0,	0,	0,	0,	0,	0,	1,	0,	0,	0,	'',	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	1,	1,	0);

DROP TABLE IF EXISTS `blueprint_messages`;
CREATE TABLE `blueprint_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `to` int(11) unsigned DEFAULT NULL,
  `from` int(11) unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `date` int(11) unsigned NOT NULL DEFAULT 0,
  `del_recipient` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `del_sender` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `flag` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `to` (`to`),
  KEY `from` (`from`),
  CONSTRAINT `blueprint_messages_ibfk_1` FOREIGN KEY (`to`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_messages_ibfk_2` FOREIGN KEY (`from`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_messages`;

DROP TABLE IF EXISTS `blueprint_pages`;
CREATE TABLE `blueprint_pages` (
  `act` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`act`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_pages`;

DROP TABLE IF EXISTS `blueprint_posts`;
CREATE TABLE `blueprint_posts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `auth_id` int(11) unsigned DEFAULT NULL,
  `post` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` int(11) unsigned NOT NULL DEFAULT 0,
  `showsig` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `showemotes` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `tid` int(11) unsigned NOT NULL,
  `newtopic` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `ip` int(11) unsigned NOT NULL DEFAULT 0,
  `editdate` int(11) unsigned NOT NULL DEFAULT 0,
  `editby` int(11) unsigned DEFAULT NULL,
  `rating` text CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `tid` (`tid`),
  KEY `auth_id` (`auth_id`),
  KEY `ip` (`ip`),
  FULLTEXT KEY `post` (`post`),
  CONSTRAINT `blueprint_posts_ibfk_1` FOREIGN KEY (`auth_id`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_posts_ibfk_2` FOREIGN KEY (`tid`) REFERENCES `blueprint_topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_posts`;
INSERT INTO `blueprint_posts` (`id`, `auth_id`, `post`, `date`, `showsig`, `showemotes`, `tid`, `newtopic`, `ip`, `editdate`, `editby`, `rating`) VALUES
(1,	1,	'Now, it\'s only a matter of time before you have everything set up. You\'ll find everything you need to get started in the ACP (link at the top).\n\n\n\nEnjoy your forum!',	1286422846,	0,	0,	1,	1,	0,	0,	NULL,	'');

DROP TABLE IF EXISTS `blueprint_profile_comments`;
CREATE TABLE `blueprint_profile_comments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `to` int(11) unsigned NOT NULL,
  `from` int(11) unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `to` (`to`),
  KEY `from` (`from`),
  CONSTRAINT `blueprint_profile_comments_ibfk_1` FOREIGN KEY (`to`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blueprint_profile_comments_ibfk_2` FOREIGN KEY (`from`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_profile_comments`;

DROP TABLE IF EXISTS `blueprint_ratingniblets`;
CREATE TABLE `blueprint_ratingniblets` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `img` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_ratingniblets`;

DROP TABLE IF EXISTS `blueprint_reports`;
CREATE TABLE `blueprint_reports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `reporter` int(11) unsigned DEFAULT NULL,
  `status` tinyint(4) unsigned NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `reporter` (`reporter`),
  CONSTRAINT `blueprint_reports_ibfk_1` FOREIGN KEY (`reporter`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_reports`;

DROP TABLE IF EXISTS `blueprint_session`;
CREATE TABLE `blueprint_session` (
  `id` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` int(11) unsigned DEFAULT NULL,
  `ip` int(11) unsigned NOT NULL DEFAULT 0,
  `vars` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `last_update` int(11) unsigned NOT NULL DEFAULT 0,
  `last_action` int(11) unsigned NOT NULL DEFAULT 0,
  `runonce` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `location` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `users_online_cache` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_bot` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `buddy_list_cache` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `location_verbose` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `useragent` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `forumsread` blob NOT NULL DEFAULT '',
  `topicsread` blob NOT NULL DEFAULT '',
  `readtime` int(11) unsigned NOT NULL DEFAULT 0,
  `hide` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  CONSTRAINT `blueprint_session_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_session`;
INSERT INTO `blueprint_session` (`id`, `uid`, `ip`, `vars`, `last_update`, `last_action`, `runonce`, `location`, `users_online_cache`, `is_bot`, `buddy_list_cache`, `location_verbose`, `useragent`, `forumsread`, `topicsread`, `readtime`, `hide`) VALUES
('878ac804199f4aa95c06d727632ec869',	1,	0,	'a:2:{s:14:\"topic_lastpage\";b:1;s:13:\"topic_lastpid\";i:4;}',	1533353814,	1533353193,	'',	'vt2',	'1',	0,	'',	'In topic \'Yo it&#039;s me, It&#039;s me, It&#039;s Mario\'',	'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:61.0) Gecko/20100101 Firefox/61.0',	'Û¸[',	'Û¹i',	0,	0);

DROP TABLE IF EXISTS `blueprint_shouts`;
CREATE TABLE `blueprint_shouts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned DEFAULT NULL,
  `shout` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` int(11) unsigned NOT NULL DEFAULT 0,
  `ip` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`),
  KEY `uid` (`uid`),
  CONSTRAINT `blueprint_shouts_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_shouts`;
INSERT INTO `blueprint_shouts` (`id`, `uid`, `shout`, `timestamp`, `ip`) VALUES
(1,	NULL,	'Welcome to jaxboards!',	0,	0);

DROP TABLE IF EXISTS `blueprint_skins`;
CREATE TABLE `blueprint_skins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `using` int(11) unsigned NOT NULL DEFAULT 0,
  `title` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `wrapper` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `hidden` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_skins`;
INSERT INTO `blueprint_skins` (`id`, `using`, `title`, `custom`, `wrapper`, `default`, `hidden`) VALUES
(1,	1,	'Default',	0,	'Default',	1,	0),
(2,	0,	'Blue Default',	0,	'Default',	0,	0);

DROP TABLE IF EXISTS `blueprint_stats`;
CREATE TABLE `blueprint_stats` (
  `posts` int(11) unsigned NOT NULL DEFAULT 0,
  `topics` int(11) unsigned NOT NULL DEFAULT 0,
  `members` int(11) unsigned NOT NULL DEFAULT 0,
  `most_members` int(11) unsigned NOT NULL DEFAULT 0,
  `most_members_day` int(11) unsigned NOT NULL DEFAULT 0,
  `last_register` int(11) unsigned DEFAULT NULL,
  KEY `last_register` (`last_register`),
  CONSTRAINT `blueprint_stats_ibfk_1` FOREIGN KEY (`last_register`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_stats`;
INSERT INTO `blueprint_stats` (`posts`, `topics`, `members`, `most_members`, `most_members_day`, `last_register`) VALUES
(4,	2,	1,	0,	0,	NULL);

DROP TABLE IF EXISTS `blueprint_textrules`;
CREATE TABLE `blueprint_textrules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('bbcode','emote','badword') COLLATE utf8mb4_unicode_ci NOT NULL,
  `needle` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `replacement` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_textrules`;

DROP TABLE IF EXISTS `blueprint_topics`;
CREATE TABLE `blueprint_topics` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lp_uid` int(11) unsigned DEFAULT NULL,
  `lp_date` int(11) unsigned NOT NULL DEFAULT 0,
  `fid` int(11) unsigned DEFAULT NULL,
  `auth_id` int(11) unsigned DEFAULT NULL,
  `replies` int(11) unsigned NOT NULL DEFAULT 0,
  `views` int(11) unsigned NOT NULL DEFAULT 0,
  `pinned` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `poll_choices` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `poll_results` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `poll_q` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `poll_type` enum('','single','multi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `summary` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `locked` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `date` int(11) unsigned DEFAULT 0,
  `op` int(11) unsigned DEFAULT NULL,
  `cal_event` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `auth_id` (`auth_id`),
  KEY `lp_date` (`lp_date`),
  KEY `cal_event` (`cal_event`),
  KEY `lp_uid` (`lp_uid`),
  KEY `fid` (`fid`),
  KEY `op` (`op`),
  FULLTEXT KEY `title` (`title`),
  CONSTRAINT `blueprint_topics_ibfk_1` FOREIGN KEY (`lp_uid`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_topics_ibfk_2` FOREIGN KEY (`fid`) REFERENCES `blueprint_forums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blueprint_topics_ibfk_3` FOREIGN KEY (`auth_id`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_topics_ibfk_4` FOREIGN KEY (`op`) REFERENCES `blueprint_posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE `blueprint_topics`;
INSERT INTO `blueprint_topics` (`id`, `title`, `subtitle`, `lp_uid`, `lp_date`, `fid`, `auth_id`, `replies`, `views`, `pinned`, `poll_choices`, `poll_results`, `poll_q`, `poll_type`, `summary`, `locked`, `date`, `op`, `cal_event`) VALUES
(1,	'Welcome to jaxboards!',	'Support appreciated.',	1,	1286422846,	1,	1,	0,	0,	0,	'',	'',	'',	'',	'Now, it\'s only a matter of time before you have',	0,	0,	1,	0);

SET foreign_key_checks = 1;

