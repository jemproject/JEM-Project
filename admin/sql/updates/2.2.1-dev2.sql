-- change 'contactid' on events table to int
ALTER TABLE `#__jem_events`
  CHANGE `contactid` `contactid` int(10) NOT NULL DEFAULT '0'
;
