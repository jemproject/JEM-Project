-- ===============================================
-- JEM Upgrade 4.3.2 → 4.3.3
-- Ensure JSON is valid before JSON_SET, then execute
-- ===============================================

UPDATE `#__jem_config`
SET value = JSON_SET(
    CASE
        WHEN value IS NULL OR value = '' THEN '{}'
        WHEN JSON_VALID(value)=0 THEN '{}'
        ELSE value
    END,
    '$.global_editevent_starttime_limit','0',
    '$.global_editevent_endtime_limit','23',
    '$.global_editevent_minutes_block','1'
)
WHERE keyname = 'globalattribs';

INSERT IGNORE INTO `#__jem_config` (`keyname`,`value`,`access`) VALUES ('recurrence_anticipation_lastday','60','0');

ALTER TABLE `#__jem_events` ADD COLUMN recurrence_bylastday VARCHAR(20) NULL DEFAULT NULL AFTER recurrence_byday;
ALTER TABLE `#__jem_events` ADD COLUMN publish_down DATETIME NULL DEFAULT NULL AFTER modified_by;
ALTER TABLE `#__jem_events` ADD COLUMN publish_up DATETIME NULL DEFAULT NULL AFTER modified_by;

INSERT INTO `#__jem_config` (`keyname`,`value`,`access`) VALUES
('categories_order','0','0'),
('defaultCategory','0','0'),
('defaultVenue','0','0');

UPDATE `#__jem_events` SET publish_up = created;
