ALTER TABLE `#__jem_categories`
	ADD email varchar(200) NOT NULL DEFAULT '';

ALTER TABLE `#__jem_events`
	MODIFY `title` varchar(255) NOT NULL DEFAULT '',
	MODIFY `alias` varchar(255) NOT NULL DEFAULT '';
