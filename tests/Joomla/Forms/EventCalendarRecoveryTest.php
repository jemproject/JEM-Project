<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Native form validation/retry checks without installed-site or database writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventCalendarRecoveryTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> Editors and display formats. */
    public static function editors(): iterable
    {
        foreach (['admin', 'site'] as $side) {
            foreach (['Y-m-d' => '%Y-%m-%d', 'd-m-Y' => '%d-%m-%Y', 'd.m.Y' => '%d.%m.%Y',
                'd/m/Y' => '%d/%m/%Y', 'm/d/Y' => '%m/%d/%Y'] as $filter => $display) {
                yield $side . ' ' . $filter => [$side, $display, $filter];
            }
        }
    }

    /**
     * Exercise actual event XML and native Form::process, including malformed input.
     * @param string $side Actual backend/frontend field implementation.
     * @param string $display Calendar display format.
     * @param string $filter Localized input format.
     * @return void
     */
    #[DataProvider('editors')]
    public function testInvalidInputIsRecoverableAndCorrectedDatesPass(string $side, string $display, string $filter): void
    {
        $definition = $this->boot($side, $display, $filter);
        $form = null;
        $process = static function (array $data) use ($definition, &$form) {
            $form = new Form('event.date.recovery', ['control' => 'jform']);
            $form->load($definition);
            return $form->process($data + ['recurrence_type' => '0']);
        };
        $local = static fn (string $date): string => (new DateTimeImmutable($date))->format($filter);
        $bad = strtr($filter, ['Y' => '2026', 'm' => '02', 'd' => '31']);
        $invalid = [$bad, 'now', '0000-00-00', [], ['bad'], false, true, 20261113,
            $local('2026-11-13') . "\0", "20\0 26-11-13", '2026-11-13 12:30:00',
            '2026-11-13extra', '"><script>alert(1)</script>'];
        if ($filter !== 'Y-m-d') {
            $invalid[] = '2026-11-13';
        }
        foreach (['dates', 'enddates'] as $name) {
            foreach ($invalid as $value) {
                $data = ['dates' => $local('2026-11-13'), $name => $value];
                self::assertFalse($process($data), $name . ': ' . json_encode($value));
                self::assertStringContainsString('not a valid calendar date', $form->getErrors()[0]->getMessage());
                // Joomla filters once more before redirecting; this must not throw either.
                self::assertFalse($form->filter($data)[$name]);
            }
        }
        foreach (['2028-02-29', '2026-11-13', '2026-03-04'] as $date) {
            $result = $process(['dates' => $local($date), 'enddates' => $local($date)]);
            self::assertIsArray($result);
            self::assertStringStartsWith($date . ' ', $result['dates']);
            self::assertStringStartsWith($date . ' ', $result['enddates']);
        }
        self::assertIsArray($process(['dates' => '', 'enddates' => '']));
        self::assertIsArray($process(['dates' => null]));
        self::assertIsArray($process(['dates' => ' ' . $local('2026-11-13') . ' ']));
        foreach (range(1, 6) as $type) {
            self::assertFalse($process(['dates' => $local('2026-11-13'), 'recurrence_type' => $type,
                'recurrence_limit_date' => '']));
            self::assertSame('Please enter an end date for the recurrence.', $form->getErrors()[0]->getMessage());
        }
        self::assertIsArray($process(['dates' => $local('2026-11-13'), 'recurrence_type' => 2,
            'recurrence_limit_date' => $local('2026-12-31')]));
    }

    /**
     * Preserve rejected text and localize valid stored/retried dates without ambiguity.
     * @param string $side Actual editor implementation.
     * @param string $display Calendar display format.
     * @param string $filter Localized input format.
     * @return void
     */
    #[DataProvider('editors')]
    public function testRetryRenderingPreservesText(string $side, string $display, string $filter): void
    {
        $this->boot($side, $display, $filter);
        $field = new class extends JFormFieldCalendarJem {
            /** @return object Capture native renderer data without requiring a template. */
            protected function getRenderer($layoutId = 'default')
            {
                return new class {
                    /** @param array $data Calendar data. @return string Encoded renderer arguments. */
                    public function render(array $data): string { return json_encode($data); }
                };
            }
        };
        $field->setDatabase(Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class));
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
        $element = $xml->xpath('//field[@name="dates"]')[0];
        $bad = strtr($filter, ['Y' => '2026', 'm' => '02', 'd' => '31']);
        foreach ([$bad, 'now', '"><script>alert(1)</script>', []] as $value) {
            $field->setup($element, $value);
            $data = json_decode($field->input, true);
            self::assertSame('', $data['value']);
            self::assertStringContainsString('data-jem-invalid-date="'
                . htmlspecialchars(is_string($value) ? $value : '', ENT_QUOTES, 'UTF-8') . '"', $data['dataAttribute']);
            self::assertStringNotContainsString('<script>', $data['dataAttribute']);
        }
        foreach (['', null, '0000-00-00', '0000-00-00 00:00:00'] as $empty) {
            $field->setup($element, $empty);
            $data = json_decode($field->input, true);
            self::assertSame('', $data['value']);
            self::assertStringNotContainsString('data-jem-invalid-date', $data['dataAttribute']);
        }
        foreach (['2026-11-13', '2028-02-29', '2026-03-04'] as $iso) {
            $local = (new DateTimeImmutable($iso))->format($filter);
            foreach ([$iso, $iso . ' 10:30:00', $local] as $value) {
                $field->setup($element, $value);
                $data = json_decode($field->input, true);
                self::assertSame($local, $data['value']);
                self::assertStringNotContainsString('data-jem-invalid-date', $data['dataAttribute']);
            }
        }
    }

    /**
     * Load the actual selected field class and editor XML with isolated Joomla services.
     * @param string $side Editor implementation.
     * @param string $display Calendar display format.
     * @param string $filter PHP input format.
     * @return string Minimal form definition extracted from production XML.
     */
    private function boot(string $side, string $display, string $filter): string
    {
        if (!is_file((string) getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5/6 tree.');
        }
        define('JEM_TEST_CALENDAR_SIDE', $side);
        require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
        self::assertSame(realpath(JEM_TEST_ROOT . '/' . $side . '/models/fields/calendarjem.php'),
            realpath((new ReflectionClass(JFormFieldCalendarJem::class))->getFileName()));
        $language = Factory::getApplication()->getLanguage();
        $strings = new ReflectionProperty($language, 'strings');
        $strings->setValue($language, array_replace($strings->getValue($language), [
            'DATE_FORMAT_CALENDAR_DATE' => $display, 'DATE_FORMAT_FILTER_DATE' => $filter,
        ]));
        Form::addFieldPath(JEM_TEST_ROOT . '/' . $side . '/models/fields');
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
        $fields = $xml->xpath('//field[@name="dates" or @name="enddates" or @name="recurrence_type" or @name="recurrence_limit_date"]');
        return '<form>' . implode('', array_map(static fn ($field) => $field->asXML(), $fields)) . '</form>';
    }
}
