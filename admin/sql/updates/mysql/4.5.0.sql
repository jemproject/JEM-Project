-- delete values

-- new values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_venue', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_registration', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('event_show_registration_counters', '1');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('attachments_layout', 'column');
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('attachments_icon_size', 'normal');

CREATE TABLE IF NOT EXISTS `#__jem_links` (`id` INT(11) NOT NULL AUTO_INCREMENT,`event_id` INT(11) NOT NULL,`type` VARCHAR(50) NOT NULL,`title` VARCHAR(255) NOT NULL,`description` VARCHAR(255)  NULL,`url` TEXT NOT NULL,`params` TEXT DEFAULT NULL,`ordering` INT(11) DEFAULT 0,`state` TINYINT(1) DEFAULT 1,`created` DATETIME DEFAULT CURRENT_TIMESTAMP,`created_by` INT(11) NOT NULL,`modified` DATETIME DEFAULT NULL,`modified_by` INT(11) DEFAULT NULL,PRIMARY KEY (`id`),INDEX `idx_event_id` (`event_id`),INDEX `idx_state` (`state`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- change values
ALTER TABLE `#__jem_events` MODIFY `contactid` VARCHAR(100);

-- update values
UPDATE `#__jem_events` SET `contactid` = '' WHERE `contactid` = 0;
UPDATE `#__menu` SET `params` = REPLACE(`params`, '"tablefiltereventuntil":"0"', '"tablefiltereventuntil":""') WHERE `link` LIKE '%com_jem&view=eventslist%' AND `params` LIKE '%"tablefiltereventuntil":"0"%';
UPDATE `#__jem_config` SET `value` = 'txt,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,ics,jpg,jpeg,gif,png,webp,zip,tar.gz' WHERE `keyname` =  'attachments_types'  AND `value` = 'txt,pdf,jpg,jpeg,gif,png,zip,tar.gz';
