-- update all JEM table from v.2.3.13 to v.2.3.14 to new JEM version with support Joomla 4
ALTER TABLE #__jem_attachments
  MODIFY COLUMN added datetime NULL DEFAULT null;

ALTER TABLE #__jem_categories
  MODIFY COLUMN description mediumtext NULL DEFAULT null
  , MODIFY COLUMN meta_keywords text NULL DEFAULT null
  , MODIFY COLUMN meta_description text NULL DEFAULT null
  , MODIFY COLUMN checked_out_time datetime NULL DEFAULT null
  , MODIFY COLUMN note varchar(255) NULL DEFAULT null
  , MODIFY COLUMN language varchar(7) NULL DEFAULT null
  , MODIFY COLUMN created_time datetime NOT NULL DEFAULT current_timestamp()
  , MODIFY COLUMN path varchar(255) NULL DEFAULT null
  , MODIFY COLUMN metadata varchar(2048) NULL DEFAULT null
  , MODIFY COLUMN modified_time datetime NULL DEFAULT null
  , MODIFY COLUMN email varchar(200) NULL DEFAULT null;

ALTER TABLE #__jem_cats_event_relations
  MODIFY COLUMN ordering tinyint(11) NOT NULL DEFAULT 0;

ALTER TABLE #__jem_events
  MODIFY COLUMN modified datetime NULL DEFAULT null
  , MODIFY COLUMN created datetime NOT NULL DEFAULT current_timestamp()
  , MODIFY COLUMN meta_keywords varchar(200) NULL DEFAULT null
  , MODIFY COLUMN meta_description varchar(255) NULL DEFAULT null
  , MODIFY COLUMN checked_out_time datetime NULL DEFAULT null
  , MODIFY COLUMN attribs varchar(5120) NOT NULL DEFAULT ''''
  , MODIFY COLUMN language char(7) NOT NULL DEFAULT '''';

ALTER TABLE #__jem_groups
  MODIFY COLUMN description mediumtext NULL DEFAULT null
  , MODIFY COLUMN checked_out_time datetime NULL DEFAULT null;

ALTER TABLE #__jem_venues
  MODIFY COLUMN locdescription mediumtext NULL DEFAULT null
  , MODIFY COLUMN meta_keywords text NULL DEFAULT null
  , MODIFY COLUMN meta_description text NULL DEFAULT null
  , MODIFY COLUMN created datetime NOT NULL DEFAULT current_timestamp()
  , MODIFY COLUMN modified datetime NULL DEFAULT null
  , MODIFY COLUMN checked_out_time datetime NULL DEFAULT null
  , MODIFY COLUMN publish_up datetime NULL DEFAULT null
  , MODIFY COLUMN publish_down datetime NULL DEFAULT null
  , MODIFY COLUMN attribs varchar(5120) NULL DEFAULT null
  , MODIFY COLUMN language char(7) NULL DEFAULT null;
