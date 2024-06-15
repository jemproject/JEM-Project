-- update values
DELETE FROM `#__jem_config` WHERE `ujrfe_jem_config`.`keyname` = 'recurrence_anticipation';
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_day', '3', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_week', '12', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_month', '60', '0');
INSERT INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_year', '180', '0');

ALTER TABLE `#__jem_events` ADD `bookedrootevent` INT(1) NOT NULL DEFAULT '0' AFTER `requestanswer`; 
