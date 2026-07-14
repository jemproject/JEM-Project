# JEM Events add-on for AcyMailing 10

This add-on replaces the former AcyMailing 5.x Joomla plugin and inserts JEM
events into emails created with AcyMailing 10.

## Compatibility

- Joomla 5.4 or Joomla 6
- JEM 5.0.1 Beta 1
- AcyMailing 10 (verified against 10.11.1)
- PHP 8.3 or newer

## Installation

Install `acym_jem.zip` from **System > Install > Extensions** in the Joomla
administrator. The package is a Joomla file extension and installs the AcyMailing
add-on at:

`administrator/components/com_acym/dynamics/jem/plugin.php`

Alternatively, extract the archive and copy its `jem` directory manually into
`administrator/components/com_acym/dynamics`.

Remove or uninstall the old `AcyMailing - JEM` / `tagjem` Joomla plugin first.
Its AcyMailing 5.x events are not used by this version.

After installation, **JEM Events** is registered in **AcyMailing > Add-ons > My
add-ons**. Open the AcyMailing email editor to use it in the dynamic-content
integrations when JEM is installed and enabled.

The separate `acym_jem_acyba.zip` archive contains only the `jem` add-on folder.
It is the distribution format to submit to Acyba for review and possible
publication in **Available add-ons**; it is not the Joomla installer.

The editable source of the catalog banner is stored in `assets/banner.svg`.

## Features

- Insert published events one by one.
- Search and filter the event picker by JEM category.
- Insert upcoming events automatically by category and date range.
- Include or exclude events without a date.
- Restrict automatic content to featured events.
- Display the title, date, venue, description, image, and read-more link.
- Control whether the integration is available from the frontend email editor.
- Use a JEM menu item ID when building event links.
- Support automatic campaigns, the "only new" option, and custom layouts.
