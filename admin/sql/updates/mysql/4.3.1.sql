-- delete values

-- new values


-- change values
ALTER TABLE `#__jem_events` ADD COLUMN `registra_until` VARCHAR(7) NOT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` ADD COLUMN `registra_from` VARCHAR(7) NOT NULL AFTER `registra`;
ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` VARCHAR(7) NOT NULL;

-- update values

