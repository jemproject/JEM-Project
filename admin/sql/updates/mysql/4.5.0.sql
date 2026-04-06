-- delete values

-- new values

INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES
('event_show_venue', '1'),
('event_show_registration', '1'),
('event_show_registration_counters', '1');

-- change values

ALTER TABLE `#__jem_events` MODIFY `contactid` VARCHAR(100);

-- update values
