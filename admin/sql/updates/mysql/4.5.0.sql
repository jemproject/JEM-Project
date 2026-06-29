-- delete values

-- update values
UPDATE `#__jem_events` SET `publish_up` = `created` WHERE `publish_up` IS NULL AND `created` IS NOT NULL;

-- new values
CREATE TABLE IF NOT EXISTS `#__jem_types` (`id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(100) NOT NULL, `type` TINYINT(1) NOT NULL COMMENT '1 = Event, 2 = Category, 3 = Venue', `icon` VARCHAR(255) DEFAULT NULL, PRIMARY KEY (`id`));

INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_venue', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_registration', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_registration_counters', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('attachments_layout', 'column');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('attachments_icon_size', 'normal');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('fancy_select_threshold', '10');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('storeipmode', 'full');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_custom_fields_position', 'details');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('global_venue_custom_fields_position', 'details');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('globalattribs', '{"loglevel":"2"}');
UPDATE `#__jem_config` SET `value` = '{"loglevel":"2"}' WHERE `keyname` = 'globalattribs' AND (`value` = '' OR `value` IS NULL);
UPDATE `#__jem_config` SET `value` = JSON_INSERT(`value`, '$.event_show_online_meeting', '1', '$.event_online_meeting_ics', '1', '$.event_online_meeting_ics_description', '1', '$.event_online_meeting_default_label', '') WHERE `keyname` = 'globalattribs';

CREATE TABLE IF NOT EXISTS `#__jem_links` (`id` INT(11) NOT NULL AUTO_INCREMENT,`event_id` INT(11) NOT NULL,`type` VARCHAR(50) NOT NULL,`title` VARCHAR(255) NOT NULL,`description` VARCHAR(255)  NULL,`url` TEXT NOT NULL,`params` TEXT DEFAULT NULL,`ordering` INT(11) DEFAULT 0,`state` TINYINT(1) DEFAULT 1,`created` DATETIME DEFAULT CURRENT_TIMESTAMP,`created_by` INT(11) NOT NULL,`modified` DATETIME DEFAULT NULL,`modified_by` INT(11) DEFAULT NULL,PRIMARY KEY (`id`),INDEX `idx_event_id` (`event_id`),INDEX `idx_state` (`state`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__jem_import_profiles` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`title` varchar(255) NOT NULL DEFAULT '',`context` varchar(50) NOT NULL DEFAULT 'events',`source_format` varchar(20) NOT NULL DEFAULT 'csv',`source_signature` varchar(64) DEFAULT NULL,`mapping` mediumtext NOT NULL,`options` mediumtext DEFAULT NULL,`published` tinyint(1) NOT NULL DEFAULT 1,`access` int(10) unsigned NOT NULL DEFAULT 1,`ordering` int(11) NOT NULL DEFAULT 0,`created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,`created_by` int(11) unsigned NOT NULL DEFAULT 0,`modified` datetime NULL DEFAULT NULL,`modified_by` int(11) unsigned NOT NULL DEFAULT 0,PRIMARY KEY (`id`),KEY `idx_context_format` (`context`, `source_format`),KEY `idx_published` (`published`),KEY `idx_access` (`access`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__jem_special_days` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`title` varchar(255) NOT NULL DEFAULT '',`alias` varchar(255) NOT NULL DEFAULT '',`day_type` varchar(100) NOT NULL DEFAULT '',`start_date` date DEFAULT NULL,`end_date` date DEFAULT NULL,`weekdays` varchar(30) NOT NULL DEFAULT '',`country` varchar(255) NOT NULL DEFAULT '',`region` varchar(100) NOT NULL DEFAULT '',`city` varchar(100) NOT NULL DEFAULT '',`description` text DEFAULT NULL,`show_dates` tinyint(1) NOT NULL DEFAULT 1,`published` tinyint(1) NOT NULL DEFAULT 1,`ordering` int(11) NOT NULL DEFAULT 0,`created` datetime DEFAULT NULL,`created_by` int(11) unsigned NOT NULL DEFAULT 0,`modified` datetime DEFAULT NULL,`modified_by` int(11) unsigned NOT NULL DEFAULT 0,`checked_out` int(11) unsigned DEFAULT NULL,`checked_out_time` datetime DEFAULT NULL,PRIMARY KEY (`id`),KEY `idx_type` (`day_type`),KEY `idx_dates` (`start_date`,`end_date`),KEY `idx_weekdays` (`weekdays`),KEY `idx_location` (`country`,`region`,`city`),KEY `idx_published` (`published`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `#__jem_special_days` (`id`, `title`, `alias`, `day_type`, `weekdays`, `description`, `show_dates`, `published`, `ordering`, `created`) VALUES (1, 'Saturday and Sunday', 'weekend', 'Weekend', '0,6', 'Regular weekend days', 0, 1, 1, CURRENT_TIMESTAMP);
UPDATE `#__jem_special_days` SET `title` = 'Saturday and Sunday', `description` = 'Regular weekend days' WHERE `title` = 'Weekend' AND `day_type` = 'Weekend' AND (`description` IS NULL OR `description` = '');
UPDATE `#__jem_special_days` SET `show_dates` = 0 WHERE `alias` = 'weekend' AND `day_type` = 'Weekend' AND `weekdays` IN ('0,6', '6,0');

-- change values
ALTER TABLE `#__jem_special_days` ADD COLUMN `access` INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `published`;
ALTER TABLE `#__jem_special_days` ADD KEY `idx_access` (`access`);
ALTER TABLE `#__jem_events` MODIFY `contactid` VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE `#__jem_events` MODIFY `author_ip` varchar(80) DEFAULT NULL;
UPDATE `#__jem_events` SET `language` = '*' WHERE `language` = '' OR `language` IS NULL;
ALTER TABLE `#__jem_events` MODIFY `language` char(7) NOT NULL DEFAULT '*';
ALTER TABLE `#__jem_events` MODIFY `recurrence_number` int(11) NOT NULL DEFAULT '0';
ALTER TABLE `#__jem_events` ADD COLUMN `recurrence_bylastday` varchar(20) NULL DEFAULT NULL AFTER `recurrence_byday`;
ALTER TABLE `#__jem_events` CHANGE `fulltext` `fulltext` MEDIUMTEXT NOT NULL AFTER `introtext`;
ALTER TABLE `#__jem_events` ADD COLUMN `article_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `fulltext`;
ALTER TABLE `#__jem_events` ADD COLUMN `online_meeting_url` VARCHAR(2048) NOT NULL DEFAULT '' AFTER `article_id`;
ALTER TABLE `#__jem_events` ADD COLUMN `online_meeting_label` VARCHAR(255) NOT NULL DEFAULT '' AFTER `online_meeting_url`;
ALTER TABLE `#__jem_events` ADD COLUMN `event_status` VARCHAR(30) NOT NULL DEFAULT 'scheduled' AFTER `language`;
ALTER TABLE `#__jem_events` ADD COLUMN `ticket_availability` VARCHAR(30) NOT NULL DEFAULT 'instock' AFTER `event_status`;
ALTER TABLE `#__jem_events` ADD COLUMN `type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `ticket_availability`;
ALTER TABLE `#__jem_events` ADD KEY `idx_article` (`article_id`);
ALTER TABLE `#__jem_events` ADD KEY `idx_type` (`type_id`);
ALTER TABLE `#__jem_venues` ADD COLUMN `type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `language`;
ALTER TABLE `#__jem_venues` MODIFY `author_ip` varchar(80) NOT NULL DEFAULT '';
UPDATE `#__jem_venues` SET `language` = '*' WHERE `language` = '' OR `language` IS NULL;
ALTER TABLE `#__jem_venues` MODIFY `language` char(7) NOT NULL DEFAULT '*';
ALTER TABLE `#__jem_venues` MODIFY `latitude` decimal(10,6) DEFAULT NULL;
ALTER TABLE `#__jem_venues` MODIFY `longitude` decimal(10,6) DEFAULT NULL;
ALTER TABLE `#__jem_venues` ADD KEY `idx_type` (`type_id`);
ALTER TABLE `#__jem_categories` ADD COLUMN `type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `modified_user_id`;
ALTER TABLE `#__jem_categories` ADD COLUMN `article_category_id` int(10) unsigned NOT NULL DEFAULT '0' AFTER `type_id`;
ALTER TABLE `#__jem_categories` ADD COLUMN `article_create_mode` tinyint(1) NOT NULL DEFAULT '0' AFTER `article_category_id`;
UPDATE `#__jem_categories` SET `language` = '*' WHERE `language` = '' OR `language` IS NULL;
ALTER TABLE `#__jem_categories` MODIFY `language` varchar(7) NOT NULL DEFAULT '*';
ALTER TABLE `#__jem_categories` ADD KEY `idx_type` (`type_id`);
ALTER TABLE `#__jem_categories` ADD KEY `idx_article_category` (`article_category_id`);
ALTER TABLE `#__jem_categories` ADD KEY `idx_parent` (`parent_id`);
ALTER TABLE `#__jem_countries` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT '1' AFTER `name`;
ALTER TABLE `#__jem_countries` ADD KEY `idx_continent` (`continent`);
ALTER TABLE `#__jem_countries` ADD KEY `idx_published` (`published`);
ALTER TABLE `#__jem_groups` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT 1 AFTER `description`;
ALTER TABLE `#__jem_attachments` CHANGE `added` `created` DATETIME NULL DEFAULT NULL;
ALTER TABLE `#__jem_attachments` CHANGE `added_by` `created_by` INT(11) NOT NULL DEFAULT 0;

-- update values
UPDATE `#__jem_events` SET `contactid` = '' WHERE `contactid` = 0 OR `contactid` IS NULL;
UPDATE `#__menu` SET `params` = REPLACE(`params`, '"tablefiltereventuntil":"0"', '"tablefiltereventuntil":""') WHERE `link` LIKE '%com_jem&view=eventslist%' AND `params` LIKE '%"tablefiltereventuntil":"0"%';
UPDATE `#__jem_config` SET `value` = 'txt,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,ics,jpg,jpeg,gif,png,webp,zip,tar.gz' WHERE `keyname` =  'attachments_types'  AND `value` = 'txt,pdf,jpg,jpeg,gif,png,zip,tar.gz';

ALTER TABLE `#__jem_types` CHANGE `type` `entity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Event, 2=Category, 3=Venue', ADD COLUMN `alias` VARCHAR(100) NOT NULL DEFAULT '' AFTER `name`, ADD COLUMN `description` TEXT DEFAULT NULL AFTER `alias`, ADD COLUMN `base_language` CHAR(7) NOT NULL DEFAULT '' AFTER `description`, ADD COLUMN `translation_languages` VARCHAR(255) DEFAULT NULL AFTER `base_language`, ADD COLUMN `translations` MEDIUMTEXT DEFAULT NULL AFTER `translation_languages`, ADD COLUMN `color` VARCHAR(7) DEFAULT NULL AFTER `icon`, ADD COLUMN `published` TINYINT(1) NOT NULL DEFAULT 1 AFTER `color`, ADD COLUMN `ordering` INT(11) NOT NULL DEFAULT 0 AFTER `published`, ADD COLUMN `access` INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `ordering`, ADD COLUMN `language` CHAR(7) NOT NULL DEFAULT '*' AFTER `access`, ADD COLUMN `checked_out` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `language`, ADD COLUMN `checked_out_time` DATETIME NULL DEFAULT NULL AFTER `checked_out`, ADD COLUMN `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `checked_out_time`, ADD COLUMN `created_by` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `created`, ADD COLUMN `modified` DATETIME NULL DEFAULT NULL AFTER `created_by`, ADD COLUMN `modified_by` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `modified`, ADD COLUMN `attribs` TEXT DEFAULT NULL AFTER `modified_by`, ADD KEY `idx_entity` (`entity`), ADD KEY `idx_published` (`published`), ADD KEY `idx_access` (`access`), ADD KEY `idx_checkout` (`checked_out`);
UPDATE `#__jem_types` SET `language` = '*' WHERE `language` = '' OR `language` IS NULL;

ALTER TABLE `#__jem_events` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_venues` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_categories` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_cats_event_relations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_register` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_groups` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_groupmembers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_config` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_attachments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__jem_countries` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE #__jem_attachments ENGINE=InnoDB;
ALTER TABLE #__jem_categories ENGINE=InnoDB;
ALTER TABLE #__jem_cats_event_relations ENGINE=InnoDB;
ALTER TABLE #__jem_events ENGINE=InnoDB;
ALTER TABLE #__jem_groupmembers ENGINE=InnoDB;
ALTER TABLE #__jem_groups ENGINE=InnoDB;
ALTER TABLE #__jem_register ENGINE=InnoDB;
ALTER TABLE #__jem_config ENGINE=InnoDB;
ALTER TABLE #__jem_venues ENGINE=InnoDB;
ALTER TABLE #__jem_countries ENGINE=InnoDB;
