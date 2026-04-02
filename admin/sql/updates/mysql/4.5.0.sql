-- delete values

-- new values
CREATE TABLE IF NOT EXISTS `#__jem_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `type` TINYINT(1) NOT NULL COMMENT '1 = Event, 2 = Category, 3 = Venue',
    `icon` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
);

INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES
('event_show_venue', '1'),
('event_show_registration', '1'),
('event_show_registration_counters', '1');

-- change values

-- update values
