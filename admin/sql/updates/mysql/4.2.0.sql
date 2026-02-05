-- ===============================================
-- JEM Upgrade 4.1.0 → 4.2.0
-- Insert new config flags
-- ===============================================

INSERT INTO `#__jem_config` (`keyname`,`value`,`access`) VALUES
('event_show_venue','1','0'),
('event_show_registration','1','0'),
('event_show_registration_counters','1','0');
