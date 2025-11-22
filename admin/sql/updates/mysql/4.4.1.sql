-- delete values

-- new values
CREATE TABLE IF NOT EXISTS `#__jem_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `type` TINYINT(1) NOT NULL COMMENT '1 = Event, 2 = Category, 3 = Venue',
    `icon` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
);

-- change values

-- update values


