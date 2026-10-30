-- Existing attachment modification history is unknown; do not invent it.
ALTER TABLE `#__jem_attachments` ADD COLUMN `modified` DATETIME NULL DEFAULT NULL AFTER `created_by` /** CAN FAIL **/;
ALTER TABLE `#__jem_attachments` ADD COLUMN `modified_by` INT(11) NOT NULL DEFAULT '0' AFTER `modified` /** CAN FAIL **/;

-- Restore a missing type language column on older or partially upgraded sites.
ALTER TABLE `#__jem_types` ADD COLUMN `language` CHAR(7) NOT NULL DEFAULT '*' /** CAN FAIL **/;
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('image_filename_policy', 'unique');
