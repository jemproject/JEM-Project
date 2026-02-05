-- ===============================================
-- JEM Upgrade 4.3.0 → 4.3.1
-- Structural changes to event registration fields
-- ===============================================

ALTER TABLE `#__jem_events` 
    ADD COLUMN registra_until datetime DEFAULT NULL AFTER registra;

ALTER TABLE `#__jem_events` 
    ADD COLUMN registra_from datetime DEFAULT NULL AFTER registra;

ALTER TABLE `#__jem_events` 
    ADD COLUMN reginvitedonly INT(1) NOT NULL DEFAULT '0' AFTER unregistra_until;

ALTER TABLE `#__jem_events` 
    CHANGE unregistra_until unregistra_until datetime DEFAULT NULL;
