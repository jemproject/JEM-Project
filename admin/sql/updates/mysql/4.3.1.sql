-- delete values

-- new values
ALTER TABLE `#__jem_events` ADD COLUMN `registra_until` datetime DEFAULT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` ADD COLUMN `registra_from` datetime DEFAULT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` ADD COLUMN `reginvitedonly` INT(1) NOT NULL DEFAULT '0' AFTER `unregistra_until`; 

-- change values
ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` datetime DEFAULT NULL;

-- update values

