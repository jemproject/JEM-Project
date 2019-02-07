-- insert new config values
INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`)
	VALUES ('image_filetypes', 'jpg,gif,png'), ('csv_delimiter', '"'), ('csv_bom', '1');
