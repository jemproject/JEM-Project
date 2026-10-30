<?php

declare(strict_types=1);

define('_JEXEC', 1);
define('JEM_TEST_ROOT', dirname(__DIR__, 3));
require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
$side = ($argv[1] ?? '') === 'site' ? 'site' : 'admin';
$value = $argv[2] ?? '';
$xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/specialday.xml');
?>
<!doctype html><html><head>
<?php foreach (['core', 'validate', 'date-helper', 'calendar', 'date-validation'] as $script) : ?>
<script defer src="/<?php echo $script; ?>.js"></script>
<?php endforeach; ?>
</head><body><div id="messages"></div>
<form id="specialday-form" class="form-validate" action="/submit" method="post">
<input type="hidden" name="task" value="">
<?php foreach (['start_date', 'end_date'] as $name) :
    $field = new SpecialDayFixtureCalendar();
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
    // Only the message container is synthetic. Validation and submit use Joomla.
    Joomla.renderMessages = messages => {
        document.getElementById('messages').textContent = JSON.stringify(messages);
    };
    document.getElementById('save').addEventListener('click', () => {
        const form = document.getElementById('specialday-form');
        if (document.formvalidator.isValid(form)) Joomla.submitform('specialday.save', form);
    });
});
</script>
</body></html>

