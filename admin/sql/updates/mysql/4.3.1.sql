-- delete values

-- new values


-- change values
ALTER TABLE `#__jem_events` ADD COLUMN `registra_until` datetime DEFAULT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` ADD COLUMN `registra_from` datetime DEFAULT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` datetime DEFAULT NULL;

-- update values

