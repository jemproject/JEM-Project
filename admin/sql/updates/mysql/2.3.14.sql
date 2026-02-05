-- ===============================================
-- JEM Upgrade 2.3.13 → 2.3.14 (no JSON modifications)
-- Contains only table structure updates
-- ===============================================

ALTER TABLE `#__jem_attachments` 
    MODIFY added datetime NULL DEFAULT NULL;

ALTER TABLE `#__jem_categories` 
    MODIFY description mediumtext NULL DEFAULT NULL,
           MODIFY meta_keywords text NULL DEFAULT NULL,
           MODIFY meta_description text NULL DEFAULT NULL,
           MODIFY checked_out_time datetime NULL DEFAULT NULL,
           MODIFY note varchar(255) NULL DEFAULT NULL,
           MODIFY language varchar(7) NULL DEFAULT NULL,
           MODIFY created_time datetime NOT NULL DEFAULT current_timestamp(),
           MODIFY path varchar(255) NULL DEFAULT NULL,
           MODIFY metadata varchar(2048) NULL DEFAULT NULL,
           MODIFY modified_time datetime NULL DEFAULT NULL,
           MODIFY email varchar(200) NULL DEFAULT NULL;

ALTER TABLE `#__jem_cats_event_relations` 
    MODIFY ordering tinyint(11) NOT NULL DEFAULT 0;

ALTER TABLE `#__jem_events`
    MODIFY modified datetime NULL DEFAULT NULL,
           MODIFY created datetime NOT NULL DEFAULT current_timestamp(),
           MODIFY meta_keywords varchar(200) NULL DEFAULT NULL,
           MODIFY meta_description varchar(255) NULL DEFAULT NULL,
           MODIFY checked_out_time datetime NULL DEFAULT NULL,
           MODIFY attribs varchar(5120) NOT NULL DEFAULT '';

ALTER TABLE `#__jem_events`
    MODIFY language char(7) NOT NULL DEFAULT '';

ALTER TABLE `#__jem_groups`
    MODIFY description mediumtext NULL DEFAULT NULL,
           MODIFY checked_out_time datetime NULL DEFAULT NULL;

ALTER TABLE `#__jem_venues`
    MODIFY locdescription mediumtext NULL DEFAULT NULL,
           MODIFY meta_keywords text NULL DEFAULT NULL,
           MODIFY meta_description text NULL DEFAULT NULL,
           MODIFY created datetime NOT NULL DEFAULT current_timestamp(),
           MODIFY modified datetime NULL DEFAULT NULL,
           MODIFY checked_out_time datetime NULL DEFAULT NULL,
           MODIFY publish_up datetime NULL DEFAULT NULL,
           MODIFY publish_down datetime NULL DEFAULT NULL,
           MODIFY attribs varchar(5120) NULL DEFAULT NULL,
           MODIFY language char(7) NULL DEFAULT NULL;
