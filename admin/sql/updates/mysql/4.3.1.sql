-- delete values
DELETE FROM `#__jem_config` WHERE `keyname` = 'recurrence_anticipation';

-- new values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_day', '3', '0');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_week', '12', '0');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_month', '60', '0');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_year', '180', '0');

-- change values
ALTER TABLE `#__jem_events` ADD COLUMN `singlebooking` INT(1) NOT NULL DEFAULT '0' AFTER `requestanswer`;
ALTER TABLE `#__jem_events` ADD COLUMN `seriesbooking` INT(1) NOT NULL DEFAULT '0' AFTER `requestanswer`;
ALTER TABLE `#__jem_events` ADD COLUMN `registra_from` VARCHAR(7) NOT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` VARCHAR(7) NOT NULL; 

-- update values
UPDATE `#__jem_events` SET `recurrence_number` = 7 WHERE `recurrence_number` = 6 AND `recurrence_type` = 4;
UPDATE `#__jem_events` SET `recurrence_number` = 6 WHERE `recurrence_number` = 5 AND `recurrence_type` = 4;
UPDATE `#__jem_config` SET `value` = '15%' WHERE keyname = 'catfrowidth' AND value='';