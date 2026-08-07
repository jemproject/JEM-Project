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

    if ($select_value === '7') {
        document.getElementById("recurrence_number").value = 0;
        $content.textContent = '';
        document.getElementById("counter_row").style.display = "none";
        toggle_custom_schedule(true);
        return;
    }

    toggle_custom_schedule(false);

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

function custom_schedule_labels() {
    return window.jemCustomScheduleLabels || {};
}

function custom_schedule_rows() {
    var field = document.getElementById('custom_schedule_json');
    if (!field || !field.value) {
        return [];
    }

    try {
        var rows = JSON.parse(field.value);
        return Array.isArray(rows) ? rows : [];
    } catch (error) {
        return [];
    }
}

function custom_schedule_seed_row() {
    var value = function (id) {
        var element = document.getElementById(id);
        return element ? element.value : '';
    };

    return {
        event_id: 0,
        date: value('jform_dates'),
        time: value('jform_times'),
        end_date: value('jform_enddates'),
        end_time: value('jform_endtimes')
    };
}

function store_custom_schedule() {
    var field = document.getElementById('custom_schedule_json');
    var table = document.getElementById('custom_schedule_rows');
    if (!field || !table) {
        return;
    }

    field.value = JSON.stringify(Array.from(table.querySelectorAll('tr')).map(function (row) {
        return {
            event_id: Number.parseInt(row.dataset.eventId || '0', 10) || 0,
            date: row.querySelector('[data-field="date"]').value,
            time: row.querySelector('[data-field="time"]').value,
            end_date: row.querySelector('[data-field="end_date"]').value,
            end_time: row.querySelector('[data-field="end_time"]').value
        };
    }));
}

function render_custom_schedule_row(data) {
    var labels = custom_schedule_labels();
    var row = document.createElement('tr');
    row.dataset.eventId = Number.parseInt(data.event_id || '0', 10) || 0;

    [['date', 'date'], ['time', 'time'], ['end_date', 'date'], ['end_time', 'time']].forEach(function (definition) {
        var cell = document.createElement('td');
        var input = document.createElement('input');
        input.type = definition[1];
        input.className = 'form-control form-control-sm';
        input.dataset.field = definition[0];
        input.value = data[definition[0]] || '';
        if (definition[0] === 'date') {
            input.required = true;
        }
        input.addEventListener('change', store_custom_schedule);
        cell.appendChild(input);
        row.appendChild(cell);
    });

    var actions = document.createElement('td');
    var duplicate = document.createElement('button');
    duplicate.type = 'button';
    duplicate.dataset.action = 'duplicate';
    duplicate.className = 'btn btn-sm btn-outline-secondary me-1';
    duplicate.textContent = labels.duplicate || 'Duplicate';
    duplicate.addEventListener('click', function () {
        var copy = {
            event_id: 0,
            date: row.querySelector('[data-field="date"]').value,
            time: row.querySelector('[data-field="time"]').value,
            end_date: row.querySelector('[data-field="end_date"]').value,
            end_time: row.querySelector('[data-field="end_time"]').value
        };
        row.parentNode.insertBefore(render_custom_schedule_row(copy), row.nextSibling);
        store_custom_schedule();
    });
    actions.appendChild(duplicate);

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.dataset.action = 'remove';
    remove.className = 'btn btn-sm btn-outline-danger';
    remove.textContent = labels.remove || 'Remove';
    remove.disabled = row.dataset.eventId !== '0';
    if (remove.disabled) {
        remove.title = labels.cancelExisting || 'Cancel an existing occurrence from its event editor.';
    }
    remove.addEventListener('click', function () {
        row.remove();
        store_custom_schedule();
    });
    actions.appendChild(remove);
    row.appendChild(actions);

    return row;
}

function render_custom_schedule() {
    var table = document.getElementById('custom_schedule_rows');
    if (!table || table.dataset.rendered === 'true') {
        return;
    }

    var rows = custom_schedule_rows();
    if (!rows.length) {
        rows.push(custom_schedule_seed_row());
        rows.push({event_id: 0, date: '', time: '', end_date: '', end_time: ''});
    }
    rows.forEach(function (row) {
        table.appendChild(render_custom_schedule_row(row));
    });
    table.dataset.rendered = 'true';
    store_custom_schedule();

    var add = document.getElementById('custom_schedule_add');
    if (add) {
        add.addEventListener('click', function () {
            table.appendChild(render_custom_schedule_row({event_id: 0, date: '', time: '', end_date: '', end_time: ''}));
            store_custom_schedule();
        });
    }

    var scope = document.getElementById('custom_series_scope');
    if (scope) {
        scope.addEventListener('change', update_custom_schedule_scope);
    }
    update_custom_schedule_scope();
}

function update_custom_schedule_scope() {
    var scope = document.getElementById('custom_series_scope');
    var table = document.getElementById('custom_schedule_rows');
    var add = document.getElementById('custom_schedule_add');
    var locked = scope && scope.value === 'occurrence';
    if (!table) {
        return;
    }

    table.querySelectorAll('input').forEach(function (input) {
        input.disabled = locked;
    });
    table.querySelectorAll('button').forEach(function (button) {
        var row = button.closest('tr');
        button.disabled = locked || (button.dataset.action === 'remove' && row && row.dataset.eventId !== '0');
    });
    if (add) {
        add.disabled = locked;
    }
}

function toggle_custom_schedule(show) {
    var editor = document.getElementById('custom_schedule_editor');
    if (!editor) {
        return;
    }
    editor.hidden = !show;
    if (show) {
        render_custom_schedule();
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
