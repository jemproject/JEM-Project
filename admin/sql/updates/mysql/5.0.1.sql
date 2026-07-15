-- delete values

-- new values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('globalattribs', '{"loglevel":"2"}');
UPDATE `#__jem_config` SET `value` = JSON_INSERT(`value`, '$.import_additional_blocked_tags', '', '$.import_allow_trusted_iframes', '0', '$.import_trusted_iframe_hosts', '') WHERE `keyname` = 'globalattribs';

-- change values

-- update values
