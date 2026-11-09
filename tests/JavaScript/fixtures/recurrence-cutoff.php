<?php

declare(strict_types=1);

define('_JEXEC', 1);
define('JEM_TEST_ROOT', dirname(__DIR__, 3));
require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
Joomla\CMS\Factory::getContainer()->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
$side = ($argv[1] ?? '') === 'site' ? 'site' : 'admin';
$value = $argv[2] ?? '';
require_once JEM_TEST_ROOT . '/' . $side . '/models/fields/recurrenceend.php';

/** Render the real Joomla calendar without template lookup or installed-site writes. */
class RecurrenceCutoffFixtureField extends JFormFieldRecurrenceend
{
    /** @return object Native calendar renderer for this isolated fixture. */
    protected function getRenderer($layoutId = 'default')
    {
        return new class {
            /** @param array $displayData Native field data. @return string Calendar markup. */
            public function render(array $displayData): string
            {
                ob_start();
                require JPATH_ROOT . '/layouts/joomla/form/field/calendar.php';
                return (string) ob_get_clean();
            }
        };
    }
}
$xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
$element = $xml->xpath('//field[@name="recurrence_limit_date"]')[0];
$element['id'] = 'jform_recurrence_limit_date';
$field = new RecurrenceCutoffFixtureField();
$field->setDatabase(Joomla\CMS\Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class));
$field->setup($element, $value);
?>
<!doctype html><html><head>
<?php foreach (['core', 'validate', 'date-helper', 'calendar', 'date-validation', 'recurrence'] as $script) : ?>
<script defer src="/<?php echo $script; ?>.js"></script>
<?php endforeach; ?>
</head><body><div id="messages"></div>
<form id="recurrence-form" class="form-validate" action="/submit" method="post">
<input type="hidden" name="task" value="">
<input id="jform_dates" value="2028-02-29">
<input type="hidden" id="recurrence_number" value="1">
<input type="hidden" id="recurrence_number_saved" value="1">
<input type="hidden" id="recurrence_byday" value="">
<select id="jform_recurrence_type">
<?php foreach (range(0, 7) as $type) : ?>
<option value="<?php echo $type; ?>" <?php echo $type === 5 ? 'selected' : ''; ?>><?php echo $type; ?></option>
<?php endforeach; ?>
</select><div id="recurrence_output"></div>
<div id="counter_row">
<label id="jform_recurrence_limit_date-lbl" for="jform_recurrence_limit_date">By the end of</label>
<?php echo $field->input; ?>
</div><button type="button" id="save">Save</button>
</form>
<script type="application/json" class="joomla-script-options new"><?php
echo json_encode(['joomla.jtext' => $document->getScriptOptions('joomla.jtext')]);
?></script>
<script>
// Only the surrounding editor is synthetic; field, calendar, recurrence and validation are real.
var $select_output = ['', 'Every [placeholder] days', 'Every [placeholder] weeks',
    'Every [placeholder] months', 'Monthly [placeholder]', 'Every [placeholder] years', 'Monthly [placeholder]'];
var $weekday = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'].map(day => [day, day]);
var $last = 'last', $before_last = 'before last';
document.addEventListener('DOMContentLoaded', () => {
    Joomla.renderMessages = messages => {
        document.getElementById('messages').textContent = JSON.stringify(messages);
    };
    start_recurrencescript('jform_recurrence_type');
    document.getElementById('save').addEventListener('click', () => {
        const form = document.getElementById('recurrence-form');
        if (document.formvalidator.isValid(form)) Joomla.submitform('event.save', form);
    });
});
</script>
</body></html>
