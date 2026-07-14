# JEM Events add-on submission for AcyMailing

## Archive to review

Submit `acym_jem_acyba_v5.0.1.zip` to Acyba. The archive extracts to a single
`jem` directory and is intended for the AcyMailing add-on catalog. It is not a
Joomla extension installer.

## Catalog metadata

- Name: JEM Events
- Folder name: `jem`
- Version: 5.0.1
- Category: Events management
- Level: Starter
- CMS: Joomla
- Compatibility: Joomla 5.4/6, JEM 5.0.1, AcyMailing 10
- Documentation: https://www.joomlaeventmanager.net/
- Description: Insert individual JEM events in emails and automatically insert
  upcoming events by category.

## Main capabilities

- Individual event selection with search and category filtering.
- Automatic insertion by category, date range, featured state, and open dates.
- Automatic campaigns and "only new" filtering.
- Title, date, venue, image, description, and read-more output controls.
- Clickable titles and images, truncation, custom layouts, and JEM Itemid.
- Configurable availability in the frontend email editor.

## Review notes

The add-on is an original JEM implementation using AcyMailing's public dynamic
content API. It replaces the obsolete AcyMailing 5 `tagjem` Joomla plugin; no
legacy AcyMailing 5 code is included.

The companion `acym_jem_v5.0.1.zip` is maintained by the JEM project as a
Joomla installer. It registers the add-on in **My add-ons**. Publication in
**Available add-ons** remains under Acyba's catalog process.

## Validation before submission

1. Install `acym_jem_v5.0.1.zip` on Joomla 5.4 and Joomla 6 with JEM 5.0.1
   and AcyMailing 10.
2. Confirm **JEM Events** is active in **AcyMailing > Add-ons > My add-ons**.
3. Confirm **JEM Events** appears in the email editor under **Dynamic text
   type** and in the dynamic content blocks.
4. Insert one event and send a test email.
5. Insert automatic content by category and send a test email.
6. Check title/date/venue/image/description/read-more options and event links.
7. Reinstall the ZIP and confirm saved add-on settings and active state remain
   unchanged.
8. Uninstall it and confirm its files and local AcyMailing registration are
   removed.
