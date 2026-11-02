<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Native field HTML, PHP form parsing and reminder validation without installed-site writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ReminderSelectionTest extends TestCase
{
    /** @return void Load native Joomla libraries and an in-memory reminder catalogue. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite') || !class_exists('SQLite3')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT, pdo_sqlite and sqlite3; no installed site is modified.');
        }
        require JEM_TEST_ROOT . '/tests/Support/reminder_selection_environment.php';
    }

    /**
     * Load a production field, binding only its installed service path to the checkout.
     *
     * @param string $side Admin or frontend field.
     * @param array $value Selected IDs.
     * @return JFormFieldReminderDefinitions Native field with the actual fancy-select layout.
     */
    private function field(string $side, array $value): object
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/' . $side . '/models/fields/reminderdefinitions.php');
        $source = str_replace(
            "JPATH_SITE . '/components/com_jem/classes/reminderservice.class.php'",
            "JEM_TEST_ROOT . '/site/classes/reminderservice.class.php'",
            $source
        );
        eval('?>' . $source);
        $field = new class (new Form('test.reminders', ['control' => 'jform'])) extends JFormFieldReminderDefinitions {
            /** @param string $layoutId Native layout ID. @return object Native layout renderer with isolated paths. */
            protected function getRenderer($layoutId = 'default')
            {
                if ($layoutId !== 'joomla.form.field.list-fancy-select') {
                    throw new RuntimeException('Unexpected reminder layout: ' . $layoutId);
                }
                return new class {
                    /** @param mixed $value HTML value. @return string Escaped layout value. */
                    public function escape($value): string
                    {
                        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                    }

                    /** @param array $displayData Native field data. @return string Actual Joomla layout output. */
                    public function render(array $displayData): string
                    {
                        ob_start();
                        try {
                            require JPATH_ROOT . '/layouts/joomla/form/field/list-fancy-select.php';
                            return ob_get_contents();
                        } finally {
                            ob_end_clean();
                        }
                    }
                };
            }
        };
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
        self::assertTrue($field->setup($xml->xpath('//field[@name="reminder_ids"]')[0], $value));
        return $field;
    }

    /** @return object Exact production save-time validation, stopping before persistence. */
    private function validator(): object
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        self::assertSame(1, preg_match('/(\$reminderSelectionSubmitted = .*?)\s*\$previousArticleContentEvent/s', $source, $match));
        eval('use Joomla\\CMS\\Language\\Text; class ReminderSelectionValidator {
            public $error = null;
            public function setError($error) { $this->error = $error; }
            public function run($data) {' . $match[1] . ' return $reminderIds; }
        }');
        return new ReminderSelectionValidator();
    }

    /** @return iterable<string, array{string, array, bool, bool}> Both forms and existing validation policies. */
    public static function submissions(): iterable
    {
        foreach (['admin', 'site'] as $side) {
            foreach ([[], [2], [2, 3], [1], [2, 2, 3]] as $ids) {
                foreach ([false, true] as $enabled) {
                    foreach ([false, true] as $allowed) {
                        yield $side . '/' . json_encode($ids) . '/' . (int) $enabled . '/' . (int) $allowed
                            => [$side, $ids, $enabled, $allowed];
                    }
                }
            }
        }
    }

    /**
     * Render native field names, parse a browser-shaped submission and validate it.
     * @param string $side Form side. @param array $ids Selection. @param bool $enabled Switch. @param bool $allowed Feature.
     * @return void
     */
    #[DataProvider('submissions')]
    public function testSubmission(string $side, array $ids, bool $enabled, bool $allowed): void
    {
        $field = $this->field($side, $ids);
        $html = $field->input;
        self::assertStringContainsString('<joomla-field-fancy-select', $html);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $hidden = $dom->getElementsByTagName('input')->item(0)->getAttribute('name');
        $select = $dom->getElementsByTagName('select')->item(0)->getAttribute('name');
        self::assertSame('jform[reminder_ids][]', $select);
        self::assertSame($select, $hidden, 'The empty sentinel must not introduce another array dimension.');
        $selected = [];
        foreach ((new DOMXPath($dom))->query('//select/option[@selected]') as $option) {
            $selected[] = (int) $option->getAttribute('value');
        }
        self::assertSame(array_values(array_unique($ids)), $selected, 'Render exactly the explicit selection.');
        $body = urlencode($hidden) . '=';
        foreach ($ids as $id) {
            $body .= '&' . urlencode($select) . '=' . $id;
        }
        parse_str($body, $post);
        self::assertSame(array_merge([''], array_map('strval', $ids)), $post['jform']['reminder_ids']);
        JemFeaturePolicy::$allowed = $allowed;
        $validator = $this->validator();
        $expected = !$allowed || ($enabled && !$ids) ? false : ($enabled ? array_values(array_unique($ids)) : []);
        self::assertSame($expected, $validator->run($post['jform'] + ['event_reminders_enabled' => (int) $enabled]));
        self::assertSame(
            !$allowed ? 'COM_JEM_EVENT_FEATURE_NOTIFICATION_DISABLED'
                : ($enabled && !$ids ? 'COM_JEM_EVENT_REMINDERS_REQUIRE_INTERVAL' : null),
            $validator->error
        );
    }

    /** @return iterable<string, array{mixed, array}> Invalid shapes must not manufacture IDs. */
    public static function selections(): iterable
    {
        yield 'empty' => [[''], []];
        yield 'old nested sentinel' => [[['']], []];
        yield 'old sentinel and selected' => [[[''], '2'], [2]];
        yield 'duplicates' => [['', '2', 2, '3', 1], [2, 3, 1]];
        yield 'invalid types' => [[true, false, [], ['2'], -1, 0, '2junk', '1.5', 1.5, new stdClass()], []];
        yield 'overflow' => [[(string) PHP_INT_MAX . '0'], []];
        yield 'scalar ID' => ['2', [2]];
        yield 'missing' => [null, []];
        yield 'object container' => [(object) ['id' => 2], []];
    }

    /** @param mixed $values Submitted data. @param array $expected Valid IDs. @return void */
    #[DataProvider('selections')]
    public function testNormalizationAndModelValidation($values, array $expected): void
    {
        self::assertSame($expected, JemReminderService::normaliseDefinitionIds($values));
        $validator = $this->validator();
        self::assertSame($expected ?: false, $validator->run(['reminder_ids' => $values, 'event_reminders_enabled' => 1]));
        self::assertSame($expected ? null : 'COM_JEM_EVENT_REMINDERS_REQUIRE_INTERVAL', $validator->error);
        self::assertSame([], $validator->run(['reminder_ids' => $values, 'event_reminders_enabled' => 0]));
    }

    /** @return iterable<string, array{string}> Both duplicate field implementations. */
    public static function sides(): iterable
    {
        yield 'backend' => ['admin'];
        yield 'frontend' => ['site'];
    }

    /** @param string $side Field side. @return void A nested sentinel cannot expose unpublished interval 1. */
    #[DataProvider('sides')]
    public function testOptionLookupIgnoresNestedSentinel(string $side): void
    {
        $field = $this->field($side, [[''], '2']);
        self::assertSame([2, 3], array_map(static fn ($option) => (int) $option->value, $field->options));
    }

    /** @return void Service selection keeps event ownership and source/code deduplication. */
    public function testServiceLookupDoesNotManufactureIdsOrCrossEvents(): void
    {
        $reflection = new ReflectionClass(JemReminderService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($service, Factory::getContainer()->get('DatabaseDriver'));
        $method = $reflection->getMethod('loadSelectionDefinitions');
        self::assertSame([], $method->invoke($service, [['']], 42));
        $rows = $method->invoke($service, [[''], '2', '3', '4', '5', '2'], 42);
        self::assertSame([4, 3], array_map(static fn ($row) => (int) $row->id, $rows));
        $rows = $method->invoke($service, ['1'], 42);
        self::assertSame([1], array_map(static fn ($row) => (int) $row->id, $rows), 'An explicitly selected ID 1 remains valid.');
    }

    /** @return void Updates without reminder fields keep the existing feature-policy behaviour. */
    public function testMissingReminderFieldsDoNotRequireTheFeature(): void
    {
        JemFeaturePolicy::$allowed = false;
        $validator = $this->validator();
        self::assertSame([], $validator->run(['title' => 'Unrelated event update']));
        self::assertNull($validator->error);
    }
}
