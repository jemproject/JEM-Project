ALTER TABLE `#__jem_events`
	CHANGE dates dates date NULL default NULL;

UPDATE `#__jem_events`
	SET dates = NULL WHERE dates = '0000-00-00';