-- delete values

-- new values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('globalattribs', '{"loglevel":"2"}');
UPDATE `#__jem_config` SET `value` = JSON_INSERT(`value`, '$.import_additional_blocked_tags', '', '$.import_allow_trusted_iframes', '0', '$.import_trusted_iframe_hosts', '') WHERE `keyname` = 'globalattribs';
ALTER TABLE `#__jem_venues` ADD `district` VARCHAR(100) NOT NULL DEFAULT '' AFTER `city`, ADD `level` VARCHAR(100) NOT NULL DEFAULT '' AFTER `district`, ADD `capacity` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `level`, ADD `email` VARCHAR(254) NOT NULL DEFAULT '' AFTER `country`, ADD `phone` VARCHAR(50) NOT NULL DEFAULT '' AFTER `email`, ADD `mobile` VARCHAR(50) NOT NULL DEFAULT '' AFTER `phone`;
ALTER TABLE `#__jem_attachments` ADD `downloads` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `created_by`, ADD `last_download` DATETIME NULL DEFAULT NULL AFTER `downloads`;
ALTER TABLE `#__jem_events` ADD `last_visit` DATETIME NULL DEFAULT NULL AFTER `hits`;

-- change values

-- update values
