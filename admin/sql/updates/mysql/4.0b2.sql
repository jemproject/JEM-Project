-- ===============================================
-- JEM Upgrade 2.3.17 → 4.0b2
-- Add a new configuration key and column
-- ===============================================

ALTER TABLE `#__jem_categories`
    ADD COLUMN emailacljl TINYINT NOT NULL DEFAULT 0 AFTER email;

INSERT INTO `#__jem_config` (`keyname`,`value`,`access`) VALUES
('flagicons_path','media/com_jem/images/flags/w20-webp/','0');
