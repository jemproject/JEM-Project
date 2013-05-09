<?php
/**
* @version 1.9 $Id$ 
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined( '_JEXEC' ) or die;

/**
* cleanup
* taken from PHP documentation comments
*
* @author Lybegard Karl-Olof
* @since 0.9
*/
function quoted_printable_encode($input) {

	$text1 = strip_tags($input, "<br><p>");
	$text1 = preg_replace('@([\r\n])[\s]+@',' ', $text1);    // Strip out white space
	$text1 = html_entity_decode($text1, ENT_QUOTES, "ISO-8859-15");
	$text1 = str_replace("<br />", "\r", $text1);
	$text1 = str_replace("<br/>" , "\r", $text1);
	$text1 = str_replace("<p>"   , "\r", $text1);
	$text1 = str_replace("</p>"  , "\r", $text1);

	$hex 		= array('0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F');
	$lines 		= preg_split("/(?:\r\n|\r|\n)/", $text1);
	$linebreak 	= '=0D=0A';
	$escape 	= '=';
	$output 	= '';

	for ($j=0;$j<count($lines);$j++) {
		$line 		= $lines[$j];
		$linlen 	= strlen($line);
		$newline 	= '';

		for($i = 0; $i < $linlen; $i++) {
			$c 		= substr($line, $i, 1);
			$dec 	= ord($c);

			if ( ($dec == 32) && ($i == ($linlen - 1)) ) { // convert space at eol only
				$c = '=20';
			} elseif ( ($dec == 61) || ($dec < 32 ) || ($dec > 126) ) { // always encode "\t", which is *not* required
				$h2 = floor($dec/16);
				$h1 = floor($dec%16);
				$c 	= $escape.$hex["$h2"] . $hex["$h1"];
			}

			$newline .= $c;
		} // end of for
		$output .= $newline;
		if ($j<count($lines)-1) {
			$output .= $linebreak;
		}
	}
	return trim($output);
}

/**
* offers the vcal/ical functonality
*
* @package JEM
* @author Lybegard Karl-Olof
* @since 0.9
*/
class vCal {

	var $properties;

	var $filename;

	function setTimeZone($timezone) {
		$this->properties['TIMEZONE'] = $timezone;
	}

	function setSummary($summary) {
		$this->properties['SUMMARY'] = quoted_printable_encode($summary);
	}

	function setDescription($description) {
		// $this->properties['DESCRIPTION'] = str_replace("\r", "=0D=0A=", $description);
		$this->properties['DESCRIPTION'] = quoted_printable_encode($description);
	}

	// Set start date with local time
	function setStartDate($startdate) {
		$l_startdate = $startdate + ($this->properties['TIMEZONE'] * 60 * 60);
		$this->properties['STARTDATE'] = date("Ymd\THi00", $l_startdate).'Z';

	}

	// Set end date with local time
	function setEndDate($enddate) {
		$l_enddate = $enddate + ($this->properties['TIMEZONE'] * 60 * 60);
		$this->properties['ENDDATE'] = date("Ymd\THi00", $l_enddate).'Z';
	}

	// Set location
	function setLocation($location) {
		$this->properties['LOCATION'] = quoted_printable_encode($location);
	}

	// added ability to set filename
	function setFilename( $filename ) {
		$this->filename = 'webcal'.$filename;
	}

	/**
	* generates the vcal file
	*
	* @author Lybegard Karl-Olof
	* @since 0.9
	*/
	function generateHTMLvCal() {

		$app = JFactory::getApplication();
		// header info for page
//		header( 'Content-Type: text/x-vCalendar');
		header( 'Content-Type: text/calendar');
		header( 'Content-Disposition: inline; filename='.$this->filename.'.vcs');
		?>
BEGIN:VCALENDAR
VERSION:1.0
PRODID:WebCalendar
TZ:<?php echo $this->properties['TIMEZONE']."\n" ?>
BEGIN:VEVENT
UID:1234567890<?php echo rand(1111111111,9999999999); ?>RBC
SUMMARY;ENCODING=QUOTED-PRINTABLE:<?php echo $this->properties['SUMMARY']."\n" ?>
DESCRIPTION;ENCODING=QUOTED-PRINTABLE:<?php echo $this->properties['DESCRIPTION']."\n" ?>
DTSTART:<?php echo $this->properties['STARTDATE']."\n" ?>
DTEND:<?php echo $this->properties['ENDDATE']."\n" ?>
LOCATION;ENCODING=QUOTED-PRINTABLE:<?php echo $this->properties['LOCATION']."\n" ?>
END:VEVENT
END:VCALENDAR
<?php
		$app->close();
	}

	/**
	* generates the ical file
	*
	* @author Lybegard Karl-Olof
	* @since 0.9
	*/
	function generateHTMLiCal() {

		$app = JFactory::getApplication();

		// header info for page
		header( 'Content-Type: text/calendar');
		header( 'Content-Disposition: inline; filename='.$this->filename.'.ics');
		?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:WebCalendar
BEGIN:VEVENT
UID:1234567890<?php echo rand(1111111111,9999999999); ?>RBC
CATEGORIES:WEBCALNOTE
CLASS:PUBLIC
DTSTAMP:20070112T214206Z
CREATED:20070112T213639Z
SUMMARY;ENCODING=QUOTED-PRINTABLE:<?php echo $this->properties['SUMMARY']."\n" ?>
DESCRIPTION;ENCODING=QUOTED-PRINTABLE:<?php echo $this->properties['DESCRIPTION']."\n" ?>
LOCATION;ENCODING=QUOTED-PRINTABLE:<?php echo $this->properties['LOCATION']."\n" ?>
TRANSP:OPAQUE
DTSTART:<?php echo $this->properties['STARTDATE']."\n" ?>
DTEND:<?php echo $this->properties['ENDDATE']."\n" ?>
END:VEVENT
END:VCALENDAR
<?php
		$app->close();
	}
}
?>