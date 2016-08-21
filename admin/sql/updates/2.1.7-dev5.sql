-- add 'unregistra_until' to events table
ALTER TABLE `#__jem_events`
  ADD `unregistra_until` int(11) NOT NULL default '0' AFTER `unregistra`
;
