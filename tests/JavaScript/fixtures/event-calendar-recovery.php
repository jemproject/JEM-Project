<?php

declare(strict_types=1);
define('_JEXEC', 1);
define('JEM_TEST_ROOT', dirname(__DIR__, 3));
$side = ($argv[1] ?? '') === 'site' ? 'site' : 'admin';
define('JEM_TEST_CALENDAR_SIDE', $side);
require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
Joomla\CMS\Factory::getContainer()->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
$value = $argv[2] ?? '';
$format = $argv[3] ?? 'Y-m-d';
$formats = ['Y-m-d' => '%Y-%m-%d', 'd-m-Y' => '%d-%m-%Y', 'd.m.Y' => '%d.%m.%Y',
    'd/m/Y' => '%d/%m/%Y', 'm/d/Y' => '%m/%d/%Y'];
$strings = new ReflectionProperty($language, 'strings');
$strings->setValue($language, array_replace($strings->getValue($language), [
    'DATE_FORMAT_CALENDAR_DATE' => $formats[$format], 'DATE_FORMAT_FILTER_DATE' => $format,
]));
/** Render the real event field using Joomla's calendar markup without a template. */
class EventRecoveryCalendar extends JFormFieldCalendarJem
{
    /** @return object Native layout renderer for an isolated browser form. */
    protected function getRenderer($layoutId = 'default')
    {
        return new class {
            /** @param array $displayData Calendar data. @return string Native calendar HTML. */
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
?>
<!doctype html><html><head>
<?php foreach (['core', 'validate', 'date-helper', 'calendar', 'date-validation'] as $script) : ?>
<script defer src="/<?php echo $script; ?>.js"></script>
<?php endforeach; ?>
</head><body><div id="messages"></div>
<form id="event-form" class="form-validate" action="/submit" method="post">
<input type="hidden" name="task" value="">
<?php foreach (['dates', 'enddates'] as $name) :
    $field = new EventRecoveryCalendar();
    $field->setDatabase(Joomla\CMS\Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class));
    $field->setup($xml->xpath('//field[@name="' . $name . '"]')[0], $value);
    echo '<label id="' . $name . '-lbl" for="' . $name . '">' . $name . '</label>';
    echo $field->input;
endforeach; ?>
<button type="button" id="save">Save</button>
</form>
<script type="application/json" class="joomla-script-options new"><?php
echo json_encode(['joomla.jtext' => $document->getScriptOptions('joomla.jtext')]);
?></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Joomla.renderMessages = messages => { document.getElementById('messages').textContent = JSON.stringify(messages); };
    document.getElementById('save').addEventListener('click', () => {
        const form = document.getElementById('event-form');
        if (document.formvalidator.isValid(form)) Joomla.submitform('event.save', form);
    });
});
</script>
</body></html>
