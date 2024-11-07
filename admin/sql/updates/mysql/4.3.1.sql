-- delete values

-- new values
ALTER TABLE `#__jem_events` ADD COLUMN `registra_until` datetime DEFAULT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` ADD COLUMN `registra_from` datetime DEFAULT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` ADD COLUMN `reginvitedonly` INT(1) NOT NULL DEFAULT '0' AFTER `unregistra_until`; 

-- change values
ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` INT(11) NULL DEFAULT '0'; 
UPDATE `#__jem_events` SET unregistra_until = NULL WHERE unregistra_until = 0;
ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` VARCHAR(20) NULL;
UPDATE `#__jem_events` SET unregistra_until = NULL WHERE unregistra_until != 0 AND times IS NULL OR dates IS NULL;
UPDATE `#__jem_events` SET unregistra_until = DATE_FORMAT(DATE_SUB(CONCAT(dates, ' ', times), INTERVAL unregistra_until HOUR),'%Y-%m-%d %H:%i:%s') WHERE unregistra_until != 0 AND times IS NOT NULL AND dates IS NOT NULL;
ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` datetime DEFAULT NULL;

-- update values

