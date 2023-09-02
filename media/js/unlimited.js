/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

function unlimited_starter() {
    document.getElementById('adminForm').onsubmit = submit_unlimited;
}

function include_unlimited($unlimited_name) {
    document.getElementById("recurrence_limit_date").value = $unlimited_name;	// write the word "unlimited" in the textbox
    return false;
}

function submit_unlimited() {
    var $value = document.getElementById("recurrence_limit_date").value;
    var $date_array = $value.split("-");
    if ($date_array.length < 3) {
        document.getElementById("recurrence_limit_date").value = "0000-00-00";
    }
}
