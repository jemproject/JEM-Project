ALTER TABLE `#__jem_settings`
	DROP `map24id`,
	DROP `mailinform`,
	DROP `mailinformrec`,
	DROP `mailinformuser`
	DROP `displaymyevents`;

DELETE FROM `#__menu`
	WHERE `type`='component' AND `title`='COM_JEM_MENU_ARCHIVE';
