-- delete values
DELETE FROM `#__jem_config` WHERE `keyname` = 'recurrence_anticipation';

-- new values
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_day', '3', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_week', '12', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_month', '60', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_year', '180', '0');

-- change values
ALTER TABLE `#__jem_events` ADD `seriesbooking` INT(1) NOT NULL DEFAULT '0' AFTER `requestanswer`;
ALTER TABLE `#__jem_events` ADD `singlebooking` INT(1) NOT NULL DEFAULT '0' AFTER `seriesbooking`;

-- update values
UPDATE `#__jem_events` SET `recurrence_number` = 7 WHERE `recurrence_number` = 6 AND `recurrence_type` = 4;
UPDATE `#__jem_events` SET `recurrence_number` = 6 WHERE `recurrence_number` = 5 AND `recurrence_type` = 4;
