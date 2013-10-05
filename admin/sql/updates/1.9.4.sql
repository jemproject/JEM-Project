ALTER TABLE `#__jem_groupmembers`
	ADD `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `#__jem_cats_event_relations`
	DROP PRIMARY KEY,
	ADD UNIQUE `category event relation` (`catid`, `itemid`);

ALTER TABLE `#__jem_cats_event_relations`
	ADD `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `#__jem_settings`
	DROP INDEX `id`,
	ADD PRIMARY KEY (`id`);

ALTER TABLE `#__jem_settings`
	CHANGE `id` `id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `#__jem_attachments`
	CHANGE `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `#__jem_countries`
	CHANGE `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;
