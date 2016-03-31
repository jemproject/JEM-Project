-- add unique key to attendee table
ALTER TABLE `#__jem_register`
  ADD UNIQUE KEY `idx_user_event` (`uid`,`event`)
;
