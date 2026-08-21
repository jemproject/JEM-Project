-- delete values

-- new values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('globalattribs', '{"loglevel":"2"}');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('backend_events_order', 'a.dates');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('backend_events_direction', 'ASC');
UPDATE `#__jem_config` SET `value` = JSON_INSERT(`value`, '$.import_additional_blocked_tags', '', '$.import_allow_trusted_iframes', '0', '$.import_trusted_iframe_hosts', '') WHERE `keyname` = 'globalattribs';

-- Keep each schema change in a separate Joomla-detectable statement so the
-- Extensions: Database check can identify and repair individual missing fields.
ALTER TABLE `#__jem_venues` ADD COLUMN `district` VARCHAR(100) NOT NULL DEFAULT '' AFTER `city` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD COLUMN `level` VARCHAR(100) NOT NULL DEFAULT '' AFTER `district` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD COLUMN `capacity` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `level` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT '' AFTER `country` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD COLUMN `email` VARCHAR(254) NOT NULL DEFAULT '' AFTER `timezone` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD COLUMN `phone` VARCHAR(50) NOT NULL DEFAULT '' AFTER `email` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD COLUMN `mobile` VARCHAR(50) NOT NULL DEFAULT '' AFTER `phone` /** CAN FAIL **/;

ALTER TABLE `#__jem_attachments` ADD COLUMN `downloads` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `created_by` /** CAN FAIL **/;
ALTER TABLE `#__jem_attachments` ADD COLUMN `last_download` DATETIME NULL DEFAULT NULL AFTER `downloads` /** CAN FAIL **/;

ALTER TABLE `#__jem_events` ADD COLUMN `timezone_mode` VARCHAR(10) NOT NULL DEFAULT 'joomla' AFTER `endtimes` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT '' AFTER `timezone_mode` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `start_utc` DATETIME NULL DEFAULT NULL AFTER `timezone` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `end_utc` DATETIME NULL DEFAULT NULL AFTER `start_utc` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `last_visit` DATETIME NULL DEFAULT NULL AFTER `hits` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD INDEX `idx_start_utc` (`start_utc`) /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD INDEX `idx_end_utc` (`end_utc`) /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `series_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `recurrence_bylastday` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `series_order` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `series_id` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD INDEX `idx_series` (`series_id`, `series_order`) /** CAN FAIL **/;

CREATE TABLE IF NOT EXISTS `#__jem_event_series` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `root_event_id` INT(11) UNSIGNED NOT NULL DEFAULT '0', `title` VARCHAR(255) NOT NULL DEFAULT '', `series_type` VARCHAR(20) NOT NULL DEFAULT 'custom', `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', `modified` DATETIME NULL DEFAULT NULL, `modified_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', `published` TINYINT(1) NOT NULL DEFAULT '1', PRIMARY KEY (`id`), KEY `idx_root_event` (`root_event_id`), KEY `idx_created_by` (`created_by`), KEY `idx_published` (`published`)) ENGINE=InnoDB;

INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_timezone_default', 'joomla');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('waitinglist_automatic', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('waitinglist_strategy', 'strict');

-- change values

-- update values
