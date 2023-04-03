-- update all JEM table from v.2.3.13 to v.2.3.14 to new JEM version with support Joomla 4
ALTER TABLE #__jem_attachments
  MODIFY added datetime NULL DEFAULT null;

ALTER TABLE #__jem_categories
  MODIFY description mediumtext NULL DEFAULT null
  , MODIFY meta_keywords text NULL DEFAULT null
  , MODIFY meta_description text NULL DEFAULT null
  , MODIFY checked_out_time datetime NULL DEFAULT null
  , MODIFY note varchar(255) NULL DEFAULT null
  , MODIFY language varchar(7) NULL DEFAULT null
  , MODIFY created_time datetime NOT NULL DEFAULT current_timestamp()
  , MODIFY path varchar(255) NULL DEFAULT null
  , MODIFY metadata varchar(2048) NULL DEFAULT null
  , MODIFY modified_time datetime NULL DEFAULT null
  , MODIFY email varchar(200) NULL DEFAULT null;

ALTER TABLE #__jem_cats_event_relations
  MODIFY ordering tinyint(11) NOT NULL DEFAULT 0;

ALTER TABLE #__jem_events
  MODIFY modified datetime NULL DEFAULT null
  , MODIFY created datetime NOT NULL DEFAULT current_timestamp()
  , MODIFY meta_keywords varchar(200) NULL DEFAULT null
  , MODIFY meta_description varchar(255) NULL DEFAULT null
  , MODIFY checked_out_time datetime NULL DEFAULT null
  , MODIFY attribs varchar(5120) NOT NULL DEFAULT ''''
  , MODIFY language char(7) NOT NULL DEFAULT '''';

ALTER TABLE #__jem_groups
  MODIFY description mediumtext NULL DEFAULT null
  , MODIFY checked_out_time datetime NULL DEFAULT null;

ALTER TABLE #__jem_venues
  MODIFY locdescription mediumtext NULL DEFAULT null
  , MODIFY meta_keywords text NULL DEFAULT null
  , MODIFY meta_description text NULL DEFAULT null
  , MODIFY created datetime NOT NULL DEFAULT current_timestamp()
  , MODIFY modified datetime NULL DEFAULT null
  , MODIFY checked_out_time datetime NULL DEFAULT null
  , MODIFY publish_up datetime NULL DEFAULT null
  , MODIFY publish_down datetime NULL DEFAULT null
  , MODIFY attribs varchar(5120) NULL DEFAULT null
  , MODIFY language char(7) NULL DEFAULT null;
