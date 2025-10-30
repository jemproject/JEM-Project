-- delete values

-- new values
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('access_level_locked_events', '[\"1\"]', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('access_level_locked_venues', '[\"1\"]', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('access_level_locked_categories', '[\"1\"]', '0');
ALTER TABLE `#__jem_venues` ADD `color` VARCHAR(7) NOT NULL AFTER `alias`;

-- change values
UPDATE `#__jem_config` SET `value` = 'media/com_jem/images/flags/w80-webp/' WHERE `keyname` = 'flagicons_path' AND `value` = 'media/com_jem/images/flags/w20-png/';

-- update values

