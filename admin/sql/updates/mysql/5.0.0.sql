-- delete values

-- new values

-- change values
UPDATE `#__jem_config` SET `value` = JSON_INSERT(`value`, '$.actionlog_enabled', '0') WHERE `keyname` = 'globalattribs' AND JSON_EXTRACT(`value`, '$.actionlog_enabled') IS NULL;
UPDATE `#__jem_venues` SET `attribs` = '{}' WHERE `attribs` IS NULL OR `attribs` = '' OR `attribs` = '""' OR `attribs` = "''" OR NOT JSON_VALID(`attribs`);
UPDATE `#__jem_events` SET `attribs` = '{}' WHERE `attribs` IS NULL OR `attribs` = '' OR `attribs` = '""' OR `attribs` = "''" OR NOT JSON_VALID(`attribs`);
UPDATE `#__jem_categories` SET `metadata` = '{}' WHERE `metadata` IS NULL OR `metadata` = '' OR `metadata` = '""' OR `metadata` = "''" OR NOT JSON_VALID(`metadata`);
UPDATE `#__jem_categories` SET `path` = NULL WHERE `id` = 1 AND `catname` = 'root' AND `path` IS NOT NULL;
  
-- update values
