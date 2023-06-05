-- insert new config values
ALTER TABLE `#__jem_categories` ADD `emailacljl` TINYINT NOT NULL DEFAULT '0' AFTER `email`;
INSERT INTO `a8wlc_jem_config` (`keyname`, `value`, `access`) VALUES ('flagicons_path', 'media/com_jem/images/flags/w20-png/', '0');