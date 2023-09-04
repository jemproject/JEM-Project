-- insert new config values
ALTER TABLE `#__jem_events` MODIFY `author_ip` varchar(39);
ALTER TABLE `#__jem_venues` MODIFY `author_ip` varchar(39);
ALTER TABLE `#__jem_events` ADD `requestanswer` TINYINT(1) NOT NULL DEFAULT '0' AFTER `waitinglist`;
ALTER TABLE `#__jem_events` MODIFY `recurrence_limit_date` date NULL DEFAULT null;
ALTER TABLE `#__jem_events` MODIFY `checked_out` INT(11) UNSIGNED NULL DEFAULT NULL; 
ALTER TABLE `#__jem_venues` MODIFY `checked_out` INT(11) UNSIGNED NULL DEFAULT NULL; 
ALTER TABLE `#__jem_categories` MODIFY `checked_out` INT(11) UNSIGNED NULL DEFAULT NULL; 
ALTER TABLE `#__jem_groups` MODIFY `checked_out` INT(11) UNSIGNED NULL DEFAULT NULL; 

UPDATE `#__jem_categories` SET `modified_time` = null WHERE `modified_time` LIKE '%0000-00-00%';
UPDATE `#__jem_categories` SET `checked_out_time` = null WHERE `checked_out_time` LIKE '%0000-00-00%';
UPDATE `#__jem_categories` SET `created_time` = now() WHERE `created_time` LIKE '%0000-00-00%';
UPDATE `#__jem_events` SET `created` = now() WHERE `created` LIKE '%0000-00-00%';
UPDATE `#__jem_events` SET `modified` = null WHERE `modified` LIKE '%0000-00-00%';
UPDATE `#__jem_events` SET `checked_out_time` = null WHERE `checked_out_time` LIKE '%0000-00-00%';
UPDATE `#__jem_events` SET `recurrence_limit_date` = null WHERE `recurrence_limit_date` LIKE '%0000-00-00%';
UPDATE `#__jem_groups` SET `checked_out_time` = null WHERE `checked_out_time` LIKE '%0000-00-00%';
UPDATE `#__jem_venues` SET `created` = now() WHERE `created` LIKE '%0000-00-00%';
UPDATE `#__jem_venues` SET `modified` = null WHERE `modified` LIKE '%0000-00-00%';
UPDATE `#__jem_venues` SET `checked_out_time` = null WHERE `checked_out_time` LIKE '%0000-00-00%';
UPDATE `#__jem_venues` SET `publish_up` = null WHERE `publish_up` LIKE '%0000-00-00%';
UPDATE `#__jem_venues` SET `publish_down` = null WHERE `publish_down` LIKE '%0000-00-00%';
UPDATE `#__jem_attachments` SET `added` = null WHERE `added` LIKE '%0000-00-00%';
UPDATE `#__jem_events` SET `checked_out` = null WHERE `checked_out` = 0;
UPDATE `#__jem_categories` SET `checked_out` = null WHERE `checked_out` = 0;
UPDATE `#__jem_venues` SET `checked_out` = null WHERE `checked_out` = 0;
UPDATE `#__jem_groups` SET `checked_out` = null WHERE `checked_out` = 0;

INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('flyer', '0', '0');
