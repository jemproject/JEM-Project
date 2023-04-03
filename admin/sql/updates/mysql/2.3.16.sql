-- update all JEM table from v.2.3.15 to v.2.3.16 to new JEM version with support Joomla 4

UPDATE #__jem_config SET value = 'H:i' WHERE keyname = 'formattime';
UPDATE #__jem_config SET value = 'H' WHERE keyname = 'formathour';
