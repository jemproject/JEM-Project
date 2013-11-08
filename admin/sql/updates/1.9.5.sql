ALTER TABLE `#__jem_settings`
	DROP `map24id`,
	DROP `mailinform`,
	DROP `mailinformrec`,
	DROP `mailinformuser`,
	DROP `displaymyevents`;

DELETE FROM `#__menu`
	WHERE `type`='component' AND `title`='COM_JEM_MENU_ARCHIVE';

ALTER TABLE `#__jem_events`
	MODIFY `title` VARCHAR(255),
	MODIFY `alias` VARCHAR(255);
	
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
	ADD `modified_user_id` int(10) unsigned NOT NULL DEFAULT '0';
	
INSERT IGNORE INTO `#__jem_categories`
	(`parent_id`, `lft`, `rgt`, `level`, `catname`, `alias`, `access`, `path`)
	VALUES ('0', '0', '1', '0', 'root','root','1','');