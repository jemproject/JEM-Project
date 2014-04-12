ALTER TABLE `#__jem_settings`
	DROP `map24id`,
	DROP `mailinform`,
	DROP `mailinformrec`,
	DROP `mailinformuser`,
	DROP `displaymyevents`,
	DROP `gmapkey`,
	DROP `commentsystem`,
	DROP `icslimit`,
	DROP `sortorder`,
	DROP `repeat_window`,
	ADD `globalattribs` varchar(5120) NOT NULL;

DELETE FROM `#__menu`
	WHERE `type`='component' AND `title`='COM_JEM_MENU_ARCHIVE';

UPDATE `#__menu`
	SET `link` = 'index.php?option=com_jem&view=main'
	WHERE `title` = 'COM_JEM_MENU_MAINMENU';

ALTER TABLE `#__jem_events`
	MODIFY `title` varchar(255),
	MODIFY `alias` varchar(255),
	ADD	`fulltext` mediumtext NOT NULL,
	ADD	`created_by_alias` varchar(255) NOT NULL,
	ADD	`access` int(10) NOT NULL DEFAULT '1',
	ADD	`metadata` text NOT NULL,
	ADD	`featured` tinyint(3) unsigned NOT NULL DEFAULT '0',
	ADD	`attribs` varchar(5120) NOT NULL,
	ADD `language` char(7) NOT NULL,
	CHANGE `datdescription` `introtext` mediumtext NOT NULL;

ALTER TABLE `#__jem_categories`
	ADD `title` varchar(255) NOT NULL,
	ADD `note` varchar(255) NOT NULL,
	ADD `lft` int(11) NOT NULL DEFAULT '0',
	ADD `rgt` int(11) NOT NULL DEFAULT '0',
	ADD `level` int(10) unsigned NOT NULL DEFAULT '1',
	ADD `language` varchar(7) NOT NULL,
	ADD `created_user_id` int(10) unsigned NOT NULL DEFAULT '0',
	ADD `created_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	ADD `path` varchar(255) NOT NULL,
	ADD `metadata` varchar(2048) NOT NULL,
	ADD `modified_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	ADD `modified_user_id` int(10) unsigned NOT NULL DEFAULT '0',
	CHANGE `catdescription` `description` MEDIUMTEXT NOT NULL;

ALTER TABLE `#__jem_categories`
	DROP PRIMARY KEY,
	CHANGE `id` `id` INT(11);

UPDATE `#__jem_categories`
	SET id = id+1, parent_id = parent_id+1;

ALTER TABLE `#__jem_categories`
	CHANGE `id` `id` INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT;
	
ALTER TABLE `#__jem_groups`
	ADD publishevent int(11) NOT NULL,
	ADD editevent int(11) NOT NULL;

-- increment catid, ensure to increment highest id first
UPDATE `#__jem_cats_event_relations`
	SET catid = catid+1
	ORDER BY catid DESC;

--  insert root category
INSERT IGNORE INTO `#__jem_categories`
	(`id`, `parent_id`, `lft`, `rgt`, `level`, `catname`, `alias`, `access`, `published`)
	VALUES (1, 0, 0, 1, 0, 'root', 'root', 1, 1);

--  change (frontend) menu item myattending to myattendances
UPDATE `#__menu`
	SET `link` = 'index.php?option=com_jem&view=myattendances'
	WHERE `client_id` = 0 AND `link` = 'index.php?option=com_jem&view=myattending';

--  increment category id in (frontend) menu item "index.php?option=com_jem&view=category&id="
--  (note: on category calendar id is stored in 'params' and not in 'link' so it is changed in script.php)
UPDATE `#__menu`
	SET `link` = CONCAT(LEFT(`link`, LENGTH(`link`) - LOCATE('=', REVERSE(`link`)) + 1), RIGHT(`link`, LOCATE('=', REVERSE(`link`)) - 1) + 1)
	WHERE `client_id` = 0 AND `link` LIKE 'index.php?option=com_jem&view=category&id=%'; 
