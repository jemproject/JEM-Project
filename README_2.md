# JEM 2.3.1

## What's new:

- changed map icon to red (ff0000) 	
- attending registration as warning with invitation to register
- Hack for link to BreezingForms with hints: gives an alternative for those who don't want to register a joomla account 	
  which submits event date, event title, event contactemail to Breezingforms

- show/hide publishing tab in frontend (editevent and editvenue); default is "hide" 	
  event creators who don't know exactly what to do with meta und publishing should not change anything inside this tab, so better to hide this

- added COM_JEM__ALLATTENDING in language file and in settings.xml for "event_show_attendeenames"

- added Warning in COM_JEM_MYEVENTS_DISPLAYEMAILADDRESS
	Since the attendees manager is now open to "administrator and all from group", the email addresses could be viewed and downloaded from them too.	

- Limit is now always be on the same place
- Filter in search menu is now colored like all filters 

- replaced line in categoryelement.php and attendees.php for php >=7.4
  if(is_array($this) && $this->setId((int)$array[0]));

- attendees maxplaces are added in simplelist 	
	 
- FE adding attending users is for groupmembers possible too: creator, groupmembers and admins see a link
  (the number of booked) , but only, when they are registered for that event
  
- Higher the length for venue title the alias had 100 the venue name only 50

- added in responsive editevent forgotten <fieldset>

- possible bug in responsive editevent/editvenue buttons (in some templates the buttons were dead) 

- update Zebra_Image to 2.6  	

- attending and event registration is now in legacy view too
	 
- adapted legacy view of editevent and editvenue: additional tabs (extended, publish) here too 	

- to hide archived events in seakmachines: added expiration metatag to events
	 
- added "Hide past events" to settings	 

- Default tablewidth setting is now "" instead of "100%" to make default small responsive
	
- hints for alias in Frontend editevent and editvenue 	
	 
- Changed order in Frontend Form editevent: what you need in first tab

- Broken PDF attachment is fixed 	
  
- a simple venueslist is added (only City, State and Venue) with link to venue
  
- CB plugins are (a bit) revised: added columns, better layout, new language system with fallback

- added cb language override.php	
  
## All plugins and modules aren't changed!
	 

