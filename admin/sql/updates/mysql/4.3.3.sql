-- delete values

-- new values
UPDATE `#__jem_config` SET value = JSON_SET(value,'$.global_editevent_starttime_limit', '0', '$.global_editevent_endtime_limit', '23', '$.global_editevent_minutes_block', '1') WHERE keyname = 'globalattribs';
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`, `access`) VALUES ('recurrence_anticipation_lastday', '60', '0');
ALTER TABLE `#__jem_events` ADD `recurrence_bylastday` VARCHAR(20) NULL DEFAULT NULL AFTER `recurrence_byday`;
-- change values

-- update values

