-- delete values

-- new values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('fancy_select_threshold', '10');

-- change values
UPDATE `#__jem_venues` SET `attribs` = '{}' WHERE `attribs` IS NULL OR `attribs` = '' OR `attribs` = '""' OR `attribs` = "''" OR NOT JSON_VALID(`attribs`);
UPDATE `#__jem_events` SET `attribs` = '{}' WHERE `attribs` IS NULL OR `attribs` = '' OR `attribs` = '""' OR `attribs` = "''" OR NOT JSON_VALID(`attribs`);
UPDATE `#__jem_categories` SET `metadata` = '{}' WHERE `metadata` IS NULL OR `metadata` = '' OR `metadata` = '""' OR `metadata` = "''" OR NOT JSON_VALID(`metadata`);
UPDATE `#__jem_categories` SET `path` = NULL WHERE `id` = 1 AND `catname` = 'root' AND `path` IS NOT NULL;
  
-- update values    
ALTER TABLE `#__jem_events` MODIFY `recurrence_number` int(11) NOT NULL DEFAULT '0';
ALTER TABLE `#__jem_events` ADD COLUMN `article_id` int(10) unsigned NOT NULL DEFAULT '0' AFTER `fulltext`;
ALTER TABLE `#__jem_events` ADD KEY `idx_article` (`article_id`);
ALTER TABLE `#__jem_events` ADD COLUMN `online_meeting_url` varchar(2048) NOT NULL DEFAULT '' AFTER `article_id`;
ALTER TABLE `#__jem_events` ADD COLUMN `online_meeting_label` varchar(255) NOT NULL DEFAULT '' AFTER `online_meeting_url`;
ALTER TABLE `#__jem_venues` MODIFY `latitude` decimal(10,6) DEFAULT NULL;
ALTER TABLE `#__jem_venues` MODIFY `longitude` decimal(10,6) DEFAULT NULL;
ALTER TABLE `#__jem_categories` ADD COLUMN `article_category_id` int(10) unsigned NOT NULL DEFAULT '0' AFTER `type_id`;
ALTER TABLE `#__jem_categories` ADD COLUMN `article_create_mode` tinyint(1) NOT NULL DEFAULT '0' AFTER `article_category_id`;
ALTER TABLE `#__jem_categories` ADD KEY `idx_article_category` (`article_category_id`);
ALTER TABLE `#__jem_categories` ADD KEY `idx_parent` (`parent_id`);

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

-- update row order
ALTER TABLE `#__jem_events` CHANGE `fulltext` `fulltext` MEDIUMTEXT NOT NULL AFTER `introtext`;
