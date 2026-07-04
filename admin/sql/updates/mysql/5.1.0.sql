-- JEM 5.1.0 alpha1
-- Event image subfolder foundation.

ALTER TABLE `#__jem_events` ADD COLUMN `image_path` VARCHAR(255) NOT NULL DEFAULT '' AFTER `fullimage`;