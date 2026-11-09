<?php

declare(strict_types=1);
define('_JEXEC', 1);
define('JEM_TEST_ROOT', dirname(__DIR__, 3));
require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
Joomla\CMS\Factory::getContainer()->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
require JEM_TEST_ROOT . '/admin/models/fields/recurrenceend.php';
$side = ($argv[1] ?? '') === 'site' ? 'site' : 'admin';
$locale = $argv[2] ?? 'de-DE';
$formats = ['de-DE' => ['%d.%m.%Y', 'd.m.Y'], 'es-ES' => ['%d-%m-%Y', 'd-m-Y'],
    'en-US' => ['%m/%d/%Y', 'm/d/Y'], 'fr-FR' => ['%d/%m/%Y', 'd/m/Y']];
[$display, $filter] = $formats[$locale];
$strings = new ReflectionProperty($language, 'strings');
$strings->setValue($language, array_replace($strings->getValue($language), [
    'DATE_FORMAT_CALENDAR_DATE' => $display, 'DATE_FORMAT_FILTER_DATE' => $filter,
]));

/** Use native Joomla calendar markup without an installed template or database writes. */
trait PreviewNativeCalendarRenderer
{
    /** @return object Native calendar renderer for this isolated fixture. */
    protected function getRenderer($layoutId = 'default')
    {
        return new class {
            /** @param array $displayData Field data. @return string Native calendar markup. */
            public function render(array $displayData): string
            {
                ob_start();
                require JPATH_ROOT . '/layouts/joomla/form/field/calendar.php';
                return (string) ob_get_clean();
            }
        };
    }
}
/** Actual event date field with template lookup isolated. */
class PreviewEventCalendar extends JFormFieldCalendarJem
{
    use PreviewNativeCalendarRenderer;
}
/** Actual cutoff field with template lookup isolated. */
class PreviewEndCalendar extends JFormFieldRecurrenceend
{
    use PreviewNativeCalendarRenderer;
}
$xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
$formId = $side === 'admin' ? 'event-form' : 'adminForm';
?>
<!doctype html><html><head>
<?php foreach (['core', 'validate', 'date-helper', 'calendar', 'date-validation', 'recurrence-change'] as $script) : ?>
<script defer src="/<?php echo $script; ?>.js"></script>
<?php endforeach; ?>
</head><body><div id="messages"></div>
<form id="<?php echo $formId; ?>" class="form-validate" action="/submit" method="post">
<input type="hidden" name="task" value="">
<input type="hidden" name="jem_recurrence_event_id" value="1">
<input type="hidden" name="jem_recurrence_choice" value="">
<input type="hidden" name="jem_recurrence_fingerprint" value="">
<input type="hidden" name="jform[id]" value="1">
<input type="hidden" name="jform[title]" value="Existing event">
<?php foreach (['dates' => '2031-11-08', 'enddates' => '2031-11-09', 'recurrence_limit_date' => '2031-12-31'] as $name => $value) :
    $element = $xml->xpath('//field[@name="' . $name . '"]')[0];
    $element['id'] = 'jform_' . $name;
    $element['name'] = 'jform[' . $name . ']';
    $field = $name === 'recurrence_limit_date' ? new PreviewEndCalendar() : new PreviewEventCalendar();
    $field->setDatabase(Joomla\CMS\Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class));
    $field->setup($element, $value);
?>
<label for="jform_<?php echo $name; ?>"><?php echo $name; ?></label>
<?php echo $field->input; endforeach; ?>
<dialog class="jem-recurrence-change">
<div data-recurrence-message></div><div data-recurrence-blocked></div>
<label><input type="radio" value="keep" name="decision"><span data-recurrence-keep-label></span></label>
<label data-recurrence-delete><input type="radio" value="delete" name="decision">Delete children</label>
<button type="button" data-recurrence-continue disabled>Continue</button>
<button type="button" data-recurrence-cancel>Cancel</button>
</dialog>
<button type="button" id="save">Save</button>
<button type="submit" id="native-save">Native submit</button>
</form>
<script type="application/json" class="joomla-script-options new"><?php
echo json_encode(['joomla.jtext' => $document->getScriptOptions('joomla.jtext')]);
?></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Joomla.renderMessages = messages => { document.getElementById('messages').textContent = JSON.stringify(messages); };
    Joomla.submitbutton = task => {
        const form = document.querySelector('form');
        if (document.formvalidator.isValid(form)) Joomla.submitform(task, form);
    };
    document.getElementById('save').addEventListener('click', () => Joomla.submitbutton('event.save'));
});
</script>
</body></html>
