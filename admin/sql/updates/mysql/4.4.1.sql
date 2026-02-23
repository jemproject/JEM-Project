-- delete values

-- new values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_venue', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_registration', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_registration_counters', '1');

-- change values
ALTER TABLE `#__jem_events` CHANGE `access` `access` INT(10) UNSIGNED NOT NULL DEFAULT '1';
ALTER TABLE `#__jem_categories` CHANGE `access` `access` INT(10) UNSIGNED NOT NULL DEFAULT '1';
ALTER TABLE `#__jem_venues` CHANGE `access` `access` INT(10) UNSIGNED NOT NULL DEFAULT '1';
ALTER TABLE `#__jem_attachments` CHANGE `access` `access` INT(10) UNSIGNED NOT NULL DEFAULT '1';

-- update values
UPDATE #__jem_venues SET access = 1 WHERE access = 0;
UPDATE #__jem_events SET access = 1 WHERE access = 0;
UPDATE #__jem_categories SET access = 1 WHERE access = 0;
UPDATE #__jem_attachments SET access = 1 WHERE access = 0;

