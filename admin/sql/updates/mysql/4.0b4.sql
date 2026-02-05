-- ===============================================
-- JEM Upgrade 4.0b2 → 4.0b4
-- Table structure additions
-- ===============================================

ALTER TABLE `#__jem_events`
    ADD COLUMN minbookeduser INT NOT NULL DEFAULT 0 AFTER maxplaces;

ALTER TABLE `#__jem_events`
    ADD COLUMN maxbookeduser INT NOT NULL DEFAULT 1 AFTER minbookeduser;

ALTER TABLE `#__jem_events`
    ADD COLUMN reservedplaces INT NOT NULL DEFAULT 1 AFTER maxbookeduser;

ALTER TABLE `#__jem_register`
    ADD COLUMN places INT NOT NULL DEFAULT 1 AFTER uid;
