--  clear odering field because lft is used instead
UPDATE `#__jem_categories`
	SET `ordering` = 0
	WHERE NOT `ordering` = 0;
