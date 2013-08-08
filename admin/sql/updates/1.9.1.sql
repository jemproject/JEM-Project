ALTER TABLE `#__jem_venues` ADD publish_up datetime NOT NULL , ADD publish_down datetime NOT NULL;
ALTER TABLE `#__jem_groups` ADD addvenue int(11) NOT NULL,
ADD addevent int(11) NOT NULL,
ADD publishvenue int(11) NOT NULL,
ADD editvenue int(11) NOT NULL;
ALTER TABLE `#__jem_settings` ADD fitemid varchar(5) NOT NULL;