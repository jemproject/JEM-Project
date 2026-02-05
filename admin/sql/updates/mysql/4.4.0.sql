-- ===============================================
-- JEM Upgrade 4.3.4 → 4.4.0
-- New config values and structural updates
-- ===============================================

INSERT INTO `#__jem_config` (`keyname`,`value`,`access`) VALUES
('access_level_locked_events','[\"1\"]','0'),
('access_level_locked_venues','[\"1\"]','0'),
('access_level_locked_categories','[\"1\"]','0');

ALTER TABLE `#__jem_venues` ADD `color` VARCHAR(7) NOT NULL AFTER alias;

UPDATE `#__jem_config`
SET value = 'media/com_jem/images/flags/w80-webp/'
WHERE keyname = 'flagicons_path'
  AND value = 'media/com_jem/images/flags/w20-png/';

ALTER TABLE `#__jem_events` MODIFY author_ip varchar(45) DEFAULT NULL;
ALTER TABLE `#__jem_venues` MODIFY author_ip varchar(45) NOT NULL DEFAULT '';
ALTER TABLE `#__jem_register` MODIFY uip varchar(45) NOT NULL DEFAULT '';
