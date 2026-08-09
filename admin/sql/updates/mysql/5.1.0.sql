-- JEM 5.1.0 event and venue hierarchy
ALTER TABLE `#__jem_events` ADD COLUMN `parent_event_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `series_order` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `event_tree_order` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `parent_event_id` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD COLUMN `show_in_calendar` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `event_tree_order` /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD INDEX `idx_parent_event` (`parent_event_id`, `event_tree_order`) /** CAN FAIL **/;
ALTER TABLE `#__jem_events` ADD INDEX `idx_event_calendar_tree` (`show_in_calendar`, `parent_event_id`) /** CAN FAIL **/;

ALTER TABLE `#__jem_venues` ADD COLUMN `parent_venue_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `type_id` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD COLUMN `venue_tree_order` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `parent_venue_id` /** CAN FAIL **/;
ALTER TABLE `#__jem_venues` ADD INDEX `idx_parent_venue` (`parent_venue_id`, `venue_tree_order`) /** CAN FAIL **/;
