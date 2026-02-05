-- ===============================================
-- JEM Upgrade 4.2.1 → 4.2.2
-- Fix extension quickicon key
-- ===============================================

UPDATE `#__extensions` SET element = 'jem' WHERE element = 'jemquickicon';
