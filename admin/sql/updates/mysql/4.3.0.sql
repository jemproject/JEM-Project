-- ===============================================
-- JEM Upgrade 4.2.2 → 4.3.0
-- New config recurrence fields and structural updates
-- ===============================================

DELETE FROM `#__jem_config` WHERE keyname = 'recurrence_anticipation';

INSERT IGNORE INTO `#__jem_config` (`keyname`,`value`,`access`) VALUES
('recurrence_anticipation_day','3','0'),
('recurrence_anticipation_week','12','0'),
('recurrence_anticipation_month','60','0'),
('recurrence_anticipation_year','180','0');

ALTER TABLE `#__jem_events` ADD COLUMN singlebooking INT(1) NOT NULL DEFAULT '0' AFTER requestanswer;
ALTER TABLE `#__jem_events` ADD COLUMN seriesbooking INT(1) NOT NULL DEFAULT '0' AFTER requestanswer;

UPDATE `#__jem_events` 
SET recurrence_number = 7 
WHERE recurrence_number = 6 
  AND recurrence_type = 4;

UPDATE `#__jem_events` 
SET recurrence_number = 6 
WHERE recurrence_number = 5 
  AND recurrence_type = 4;

UPDATE `#__jem_config` 
SET value = '15%' 
WHERE keyname = 'catfrowidth' AND value = '';
