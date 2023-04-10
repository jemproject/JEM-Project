-- update all JEM table from v.2.3.16 to v.2.3.17 to new JEM version with support Joomla 4
UPDATE #__jem_config SET value = 'jpg,gif,png,webp' WHERE keyname = 'image_filetypes';
