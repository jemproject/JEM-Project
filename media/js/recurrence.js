/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * @author     Sascha Karnatz
 */

var $content;   // the content object
var $select_value;
var $select_element;

function start_recurrencescript(el) {

    // $content = $("#recurrence_output"); // get the object (position) of the output
    $content = document.getElementById('recurrence_output');  // get the object (position) of the output
    // $select_element = $("#"+el);
    $select_element = document.getElementById(el);

    output_recurrencescript(); // start the output

    // additional event handler (jQuery guarded for Joomla 5)
    if (window.jQuery) {
        $("#" + el).on('change', output_recurrencescript);
    } else {
        $select_element.addEventListener('change', output_recurrencescript);
    }
}

/**
 * the output of the script (a part of them is included in
 * this function)
 *
 * @access public
 **/
function output_recurrencescript() {

    var $select_value = $select_element.value;	// the value of the select list

    if ($select_value != 0) { // want the user a recurrence
        // create an element by the generate_output function
        // ** $select_output is an array of all sentences of each type **
        var $element = generate_output($select_output[$select_value], $select_value);

        // robust DOM handling (no replaceChild on empty container)
        $content.textContent = '';
        $content.appendChild($element);

        set_parameter();	// set the new parameter

        // show the counter
        document.getElementById("counter_row").style.display = "table-row";

    } else {

        document.getElementById("recurrence_number").value = 0;	// set the parameter
        var $nothing = document.createElement("span"); // create a new "empty" element
        $nothing.appendChild(document.createTextNode(""));

        // robust DOM handling (no replaceChild on empty container)
        $content.textContent = '';
        $content.appendChild($nothing);

        document.getElementById("counter_row").style.display = "none"; // hide the counter
    }
}

/**
 * use the sentences of each type and include selectlist into this phrases
 *
 * @var array select_output
 * @var integer select_value
 * @return object the generated span element
 * @access public
 **/
function generate_output($select_output, $select_value) {

    var $output_array = $select_output.split("[placeholder]"); // split the output into two parts
    var $span = document.createElement("span"); // create a new element

    for (var $i = 0; $i < $output_array.length; $i++) {

        $weekday_array = $output_array[$i].split("[placeholder_weekday]"); // split by the weekday placeholder
        $lastday_array = $output_array[$i].split("[placeholder_lastday]"); // split by the weekday placeholder

        if ($weekday_array.length > 1) { // is the weekday placeholder set?

            for (var $k = 0; $k < $weekday_array.length; $k++) {
                $span.appendChild(document.createTextNode($weekday_array[$k])); // include the the text snippets into span - element
                if ($k == 0) { // the first iteration get an extra weekday selectlist
                    $span.appendChild(generate_selectlist_weekday());
                }
            }

        } else  if ($lastday_array.length > 1) { // is the lastday placeholder set?

            for (var $k = 0; $k < $lastday_array.length; $k++) {
                $span.appendChild(document.createTextNode($lastday_array[$k])); // include the the text snippets into span - element
                if ($k == 0) { // the first iteration get an extra weekday selectlist
                    $span.appendChild(generate_selectlist_lastday());
                }
            }

        } else {
            $span.appendChild(document.createTextNode($output_array[$i])); // include the text snippet
        }

        if ($i == 0) { // first iteration get an extra selectlist
            $span.appendChild(generate_selectlist($select_value));
        }
    }

    return $span;
}

/**
 * this function generate the normal selectlist
 *
 * @var integer select_value
 * @return object the generated selectlist
 * @access public
 **/
function generate_selectlist($select_value) {

    var $selectlist = document.createElement("select"); // new select element
    $selectlist.name = "recurrence_selectlist"; // add attributes
    $selectlist.id = "recurrence_selectlist";

    // event handling without inline assignment
    $selectlist.addEventListener('change', set_parameter);

    var $limit;

    switch ($select_value) {
        case "1":
            $limit = 31; // days (1 month)
            break;
        case "2":
            $limit = 52; // weeks (1 year)
            break;
        case "3":
            $limit = 18; // months (1'5 years)
            break;
        case "4":
            $limit = 7; // weekdays (7 cases)
            break;
        case "5":
            $limit = 12; // years ( 1 dozen years)
            break;
        case "6":
            $limit = 7; // last day ( 7 last days of month)
            break;
        default:
            $limit =24; // orders (future, hours?)
            break;
    }

    for (var $j = 0; $j < $limit; $j++) {

        var $option = document.createElement("option"); // create option element
        var $valueSelected = parseInt(document.getElementById("recurrence_number").value, 10);
        var $valueSelected_saved = parseInt(document.getElementById("recurrence_number_saved").value, 10);

        if ($j == $valueSelected_saved - 1) { // the selected - attribute
            $option.selected = true;
        }

        if (($j >= 5) && ($select_value == 4)) { // get the word for "last" and "before last" in the weekday section

            var $name_value = "";

            switch ($j) {
                case 5:
                    $name_value = $last;
                    break;
                case 6:
                    $name_value = $before_last;
                    break;
            }
            $option.appendChild(document.createTextNode($name_value)); // insert the name
            $option.value = $j + 1; // and the value

        } else {
            $option.appendChild(document.createTextNode($j + 1)); // + 1 day because their is no recuring each "0" day
            $option.value = $j + 1;
        }

        $selectlist.appendChild($option);	// include the option - element into the select - element
    }

    return $selectlist;
}

/**
 * this function generate the weekday selectlist
 *
 * @return object the generated weekday selectlist
 * @access public
 **/
function generate_selectlist_weekday() {

    var $selectlist = document.createElement("select");	// the new selectlist
    $selectlist.name = "recurrence_selectlist_weekday";	// add attributes
    $selectlist.id = "recurrence_selectlist_weekday";
    $selectlist.multiple = true;
    $selectlist.size = 7;

    var selected = document.getElementById("recurrence_byday").value.split(','); // array of selected values

    for (var $j = 0; $j < 7; $j++) {						// the 7 days

        var $option = document.createElement("option");	// create the option - elements
        $option.value = $weekday[$j][0];	// add the value
        $option.appendChild(document.createTextNode($weekday[$j][1])); // + 1 day because their is no recuring each "0" day

        if (selected.includes($option.value)) {	// the selected - attribute
            $option.selected = true;
        }

        $selectlist.appendChild($option);	// include the option - element into the select - element
    }

    var handler = function () {

        var result = '';
        var isempty = true;

        for (var i = 0; i < this.length; i++) {
            if (this.options[i].selected) {
                if (isempty) {
                    isempty = false;
                } else {
                    result += ',';
                }
                result += this.options[i].value;
            }
        }

        document.getElementById('recurrence_byday').value = result;
    };

    if (window.jQuery) {
        $($selectlist).on('change', handler);
    } else {
        $selectlist.addEventListener('change', handler);
    }

    return $selectlist;
}

/**
 * this function generate the lastday selectlist
 *
 * @return object the generated lastday selectlist
 * @access public
 **/
function generate_selectlist_lastday() {
    var $selectlist = document.createElement("select");	// the new selectlist
    $selectlist.name = "recurrence_selectlist_lastday";	// add attributes
    $selectlist.id = "recurrence_selectlist_lastday";
    $selectlist.multiple = true;
    $selectlist.size = 7;

    var selected = document.getElementById("recurrence_bylastday").value.split(','); // array of selected values

    for (var $j = 0; $j < 7; $j++) { // the 7 last days

        var $option = document.createElement("option");	// create the option - elements
        $option.value = $lastday[$j][0];	// add the value
        $option.appendChild(document.createTextNode($lastday[$j][1])); // + 1 day because their is no recuring each "0" day

        if (selected.includes($option.value)) {	// the selected - attribute
            $option.selected = true;
        }

        $selectlist.appendChild($option);	// include the option - element into the select - element
    }

    var handler = function () {

        var result = '';
        var isempty = true;

        for (var i = 0; i < this.length; i++) {
            if (this.options[i].selected) {
                if (isempty) {
                    isempty = false;
                } else {
                    result += ',';
                }
                result += this.options[i].value;
            }
        }

        document.getElementById('recurrence_bylastday').value = result;
    };

    if (window.jQuery) {
        $($selectlist).on('change', handler);
    } else {
        $selectlist.addEventListener('change', handler);
    }

    return $selectlist;
}

/**
 * set the value of the hidden input tags
 *
 * @access public
 **/
function set_parameter() {
    // include the value into the recurrence_number input tag
    document.getElementById("recurrence_number").value =
        document.getElementById("recurrence_selectlist").value;
}
