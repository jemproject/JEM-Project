# JEM 2.3.6
released 2023-04-04

Forked from jemproject/JEM-Project

Based on Egnarts94/JEM-Project Responsive JEM

## what's new?
This release is updated to prepare for the migration to Joomla 4 and JEM4.
Please consult the help files for more information.

- and many many more...

## This version includes:
- Import/Export view
- Multicategory support
- Parent category
- Ical output
- Gmap
- Calendar
- Option to Exclude categories in simplelistview
- 10 Custom fields
- Contact

Tips, suggestions are very welcome..

Project Homepage: https://www.joomlaeventmanager.net/

Translation via:  https://www.transifex.com/projects/p/JEM/

## Known Problems:
- You can not automatically update your JEM installation via the Joomla updater. If the default JEM releases an update and you install this, JEM-Responsive is removed automatically
- All new settings are in english?!?! - Yes, because I did not update all language packs.

## How do I install and enable it?
1. Download this package (or copy just download url)
2. Install it as a normal extension in Joomla
3. In Joomla Backend, go to Components -> JEM -> Settings -> Basic Settings, there you find a new entry called "Style". There are the global settings for enabling JEM Responsive. Set "Layout Style" to "Modern Responsive Style" and "Use Icon Font" to "Yes"
4. If you used the template override previously, delete all overrides from you template, they are not required anymore.

## What can I do with JEM-Responsive?
If you want a demo, take a look here: jem-test.lkgchemnitz.de 
After installation, go to your Backend->Components->JEM->Help->JEM Responsive, where you can find a documentation of JEM Responsive. Let me know if you miss something!

## Why do you use already module-class suffix?
Because the default JEM does not support some settings of JEM-Responsive. And so, only expert users, that know what they do can change the behaviour of JEM Responsive.
