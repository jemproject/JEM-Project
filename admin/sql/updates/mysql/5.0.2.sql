-- Restore a missing type language column on older or partially upgraded sites.
ALTER TABLE `#__jem_types` ADD COLUMN `language` CHAR(7) NOT NULL DEFAULT '*' /** CAN FAIL **/;
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('image_filename_policy', 'unique');
