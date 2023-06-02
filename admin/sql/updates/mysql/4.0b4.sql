-- insert new config values
ALTER TABLE `#__jem_events` ADD `minbookeduser` INT NOT NULL DEFAULT '0' AFTER `maxplaces`; 
ALTER TABLE `#__jem_events` ADD `maxbookeduser` INT NOT NULL DEFAULT '0' AFTER `minbookeduser`; 
ALTER TABLE `#__jem_events` ADD `reservedplaces`  INT NOT NULL DEFAULT '0' AFTER `maxbookeduser`; 
ALTER TABLE `#__jem_register` ADD `places` INT NOT NULL DEFAULT '1' AFTER `uid`; 