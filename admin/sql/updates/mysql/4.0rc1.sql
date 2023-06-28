-- insert new config values
ALTER TABLE `#__jem_events` ADD `requestanswer` TINYINT(1) NOT NULL DEFAULT '0' AFTER `waitinglist`;