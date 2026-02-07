-- ===============================================
-- JEM Upgrade 4.4.0 → 5.0.0
-- ===============================================

-- 1. Venues: correct attribs field
-- ------------------------------------
UPDATE `#__jem_venues` 
SET `attribs` = JSON_OBJECT() 
WHERE `attribs` IS NULL 
   OR `attribs` = '' 
   OR `attribs` = '""'
   OR `attribs` = "''"
   OR NOT JSON_VALID(`attribs`);

-- 2. Events: correct attribs field
-- ------------------------------------
UPDATE `#__jem_events` 
SET `attribs` = JSON_OBJECT() 
WHERE `attribs` IS NULL 
   OR `attribs` = '' 
   OR `attribs` = '""'
   OR `attribs` = "''"
   OR NOT JSON_VALID(`attribs`);

-- 3. Categories: correct metadata field
-- -----------------------------------------
UPDATE `#__jem_categories` 
SET `metadata` = JSON_OBJECT() 
WHERE `metadata` IS NULL 
   OR `metadata` = '' 
   OR `metadata` = '""'
   OR `metadata` = "''"
   OR NOT JSON_VALID(`metadata`);