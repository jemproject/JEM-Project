ALTER TABLE `#__jem_venues` ADD publish_upx datetime NOT NULL , ADD publish_downx datetime NOT NULL;
ALTER TABLE `#__jem_groups` ADD addvenuex int(11) NOT NULL,
ADD addeventx int(11) NOT NULL,
ADD publishvenuex int(11) NOT NULL,
ADD editvenuex int(11) NOT NULL;
ALTER TABLE `#__jem_settings` ADD fitemidx varchar(5) NOT NULL;