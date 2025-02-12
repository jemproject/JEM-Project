-- delete values

-- new values
UPDATE `#__jem_config` SET value = JSON_SET(value,'$.global_editevent_starttime_limit', '0', '$.global_editevent_endtime_limit', '23', '$.global_editevent_minutes_block', '1') WHERE keyname = 'globalattribs';
-- change values

-- update values

