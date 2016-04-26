--  new table to hold JEM settings / configuration values
CREATE TABLE IF NOT EXISTS `#__jem_config` (
  `keyname` varchar(100) NOT NULL,
  `value` text,
  `access` int(10) NOT NULL DEFAULT '0' COMMENT 'rfu',
  PRIMARY KEY (`keyname`),
  KEY `idx_access` (`access`)
) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`;

-- add some additional keys to events table
ALTER TABLE `#__jem_events`
  CHANGE `access` `access` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  ADD KEY `idx_venue` (`locid`),
  ADD KEY `idx_access` (`access`),
  ADD KEY `idx_checkout` (`checked_out`),
  ADD KEY `idx_pubstate` (`published`),
  ADD KEY `idx_createdby` (`created_by`),
  ADD KEY `idx_language` (`language`)
;

-- add 'access', 'attribs', 'language' and some additional keys to venues table
ALTER TABLE `#__jem_venues`
  ADD `access` int(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `publish_down`,
  ADD `attribs` varchar(5120) NOT NULL,
  ADD `language` char(7) NOT NULL,
  ADD KEY `idx_access` (`access`),
  ADD KEY `idx_checkout` (`checked_out`),
  ADD KEY `idx_pubstate` (`published`),
  ADD KEY `idx_createdby` (`created_by`),
  ADD KEY `idx_language` (`language`)
;

-- make 'access' field of attachments table conform to others
ALTER TABLE `#__jem_attachments`
  CHANGE `access` `access` INT(10) UNSIGNED NOT NULL DEFAULT '0'
;

-- add 'status', 'comment' and some additional keys to attendee table
ALTER TABLE `#__jem_register`
  ADD `status` tinyint(3) NOT NULL default '1',
  ADD `comment` varchar(255) DEFAULT '',
  ADD KEY `idx_event` (`event`),
  ADD KEY `idx_event_status` (`event`,`status`),
  ADD KEY `idx_user` (`uid`)
;

-- add some additional keys to groupmembers table
ALTER TABLE `#__jem_groupmembers`
  ADD KEY `idx_group` (`group_id`),
  ADD KEY `idx_user` (`member`)
;