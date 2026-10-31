-- Existing attachment modification history is unknown; do not invent it.
ALTER TABLE `#__jem_attachments` ADD COLUMN `modified` DATETIME NULL DEFAULT NULL AFTER `created_by` /** CAN FAIL **/;
ALTER TABLE `#__jem_attachments` ADD COLUMN `modified_by` INT(11) NOT NULL DEFAULT '0' AFTER `modified` /** CAN FAIL **/;

-- Restore a missing type language column on older or partially upgraded sites.
ALTER TABLE `#__jem_types` ADD COLUMN `language` CHAR(7) NOT NULL DEFAULT '*' /** CAN FAIL **/;
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('image_filename_policy', 'unique');

-- Align legacy 4.4.2/4.5.0 definitions with a clean installation.
ALTER TABLE `#__jem_categories` ADD COLUMN `image_as_default` TINYINT(1) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__jem_types` ALTER COLUMN `name` SET DEFAULT '';
ALTER TABLE `#__jem_special_days` MODIFY `weekdays` VARCHAR(32) NOT NULL DEFAULT '';
-- NULL means unknown history; never manufacture a creation date for legacy rows.
-- Separate the expression default: Joomla checks MODIFY defaults as literals.
-- MySQL requires parentheses for an expression in ALTER COLUMN SET DEFAULT.
ALTER TABLE `#__jem_special_days` MODIFY `created` DATETIME NULL;
ALTER TABLE `#__jem_special_days` ALTER COLUMN `created` SET DEFAULT (CURRENT_TIMESTAMP);
ALTER TABLE `#__jem_special_days` ADD INDEX `idx_day_type` (`day_type`) /** CAN FAIL **/;
ALTER TABLE `#__jem_special_days` ADD INDEX `idx_published_dates` (`published`, `start_date`, `end_date`) /** CAN FAIL **/;
ALTER TABLE `#__jem_special_days` ADD INDEX `idx_checkout` (`checked_out`) /** CAN FAIL **/;
-- The installer removes recognised redundant legacy indexes by column signature.
