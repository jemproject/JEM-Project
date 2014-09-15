/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

function unlimited_starter() {
	document.getElementById('adminForm').onsubmit = submit_unlimited;
}

function include_unlimited($unlimited_name) {
	$("recurrence_limit_date").value=$unlimited_name;	// write the word "unlimited" in the textbox
	return false;
}

function submit_unlimited() {
	var $value = $("recurrence_limit_date").value;
	var $date_array = $value.split("-");
	if ($date_array.length < 3) {
		$("recurrence_limit_date").value = "0000-00-00";
	}
}