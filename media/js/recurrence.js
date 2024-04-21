/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * @author     Sascha Karnatz
 */

var $content;   // the content object
var $select_value;
var $select_element;

function start_recurrencescript(el) {

//window.addEvent('domready', function() {
    // $content = $("#recurrence_output"); // get the object (position) of the output
    $content = document.getElementById('recurrence_output');  // get the object (position) of the output
    // $select_element = $("#"+el);
    $select_element = document.getElementById(el);

    output_recurrencescript(); // start the output
    $("#" + el).on('change', output_recurrencescript); // additional event handler
}

/**
 * the output of the script (a part of them is included in
 * this function)
 *
 * @access public
 **/
function output_recurrencescript() {

    var $select_value = $select_element.value;	// the value of the select list

    if ($select_value != 0) {	// want the user a recurrence
        // create an element by the generate_output function
        // ** $select_output is an array of all sentences of each type **
        var $element = generate_output($select_output[$select_value], $select_value);
        $content.replaceChild($element, $content.firstChild);	// include the element
        set_parameter();	// set the new parameter
        if (navigator.appName == "Microsoft Internet Explorer") {	// the IE don't know some CSS - classes
            document.getElementById("counter_row").style.display = "inline"; // show the counter for the IE
        } else {
            document.getElementById("counter_row").style.display = "table-row"; // show the counter for the normal browsers
        }
    } else {
        document.getElementById("recurrence_number").value = 0;	// set the parameter
        $nothing = document.createElement("span");	// create a new "empty" element
        $nothing.appendChild(document.createTextNode(""));
        $content.replaceChild($nothing, $content.firstChild);	// replace the old element by the new one
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

    var $output_array = $select_output.split("[placeholder]");	// split the output into two parts
    var $span = document.createElement("span");					// create a new element
    for ($i = 0; $i < $output_array.length; $i++) {
        $weekday_array = $output_array[$i].split("[placeholder_weekday]");	// split by the weekday placeholder

        if ($weekday_array.length > 1) {	// is the weekday placeholder set?
            for ($k = 0; $k < $weekday_array.length; $k++) {
                $span.appendChild(document.createTextNode($weekday_array[$k]));	// include the the text snippets into span - element
                if ($k == 0) {	// the first iteration get an extra weekday selectlist
                    $span.appendChild(generate_selectlist_weekday());
                }
            }
        } else {
            $span.appendChild(document.createTextNode($output_array[$i]));	// include the text snippet
        }
        if ($i == 0) {	// first iteration get an extra selectlist
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
    var $selectlist = document.createElement("select");	// new select element
    $selectlist.name = "recurrence_selectlist";	// add attributes
    $selectlist.id = "recurrence_selectlist";
    $selectlist.onchange = set_parameter;
    switch ($select_value) {
        case "1":
            $limit = 14;	// days
            break;
        case "2":
            $limit = 8;		// weeks
            break;
        case "3":
            $limit = 12;	// months
            break;
        default:
            $limit = 6;		// weekdays
            break;
    }
    for ($j = 0; $j < $limit; $j++) {
        var $option = document.createElement("option");	// create option element
        if ($j == (parseInt(document.getElementById("recurrence_number").value) - 1)) {	// the selected - attribute
            $option.selected = true;
        }
        if (($j >= 4) && ($select_value == 4)) {	// get the word for "last" and "before last" in the weekday section
            var $name_value = "";
            switch ($j) {
                case 4:
                    $name_value = $last;
                    break;
                case 5:
                    $name_value = $before_last;
                    break;
            }
            $option.appendChild(document.createTextNode($name_value)); 	// insert the name
            $option.value = $j + 1;										// and the value
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

    for ($j = 0; $j < 7; $j++) {						// the 7 days
        var $option = document.createElement("option");	// create the option - elements
        $option.value = $weekday[$j][0];	// add the value
        $option.appendChild(document.createTextNode($weekday[$j][1])); // + 1 day because their is no recuring each "0" day
        if (selected.includes($option.value)) {	// the selected - attribute
            $option.selected = true;
        }
        $selectlist.appendChild($option);	// include the option - element into the select - element
    }
    $($selectlist).on('change', function () {
        var result = '';
        var isempty = true;
        for (i = 0; i < this.length; i++) {
            if (this.options[i].selected) {
                if (isempty) {
                    isempty = false;
                } else {
                    result += ',';
                }
                result += this.options[i].value
            }
        }
        document.getElementById('recurrence_byday').value = result;
    });
    return $selectlist;
}

/**
 * set the value of the hidden input tags
 *
 * @access public
 **/
function set_parameter() {
    // include the value into the recurrence_number input tag
    document.getElementById("recurrence_number").value = document.getElementById("recurrence_selectlist").value;
}
