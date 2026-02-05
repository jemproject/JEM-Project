-- ===============================================
-- JEM Upgrade 2.3.16 → 2.3.17
-- Simple config change
-- ===============================================

UPDATE `#__jem_config` 
SET value = 'jpg,gif,png,webp' 
WHERE keyname = 'image_filetypes';
