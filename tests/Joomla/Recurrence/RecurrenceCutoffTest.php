<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise the real recurrence calculation, editor fields and model without installed-site writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RecurrenceCutoffTest extends TestCase
{
    /** @return void Bootstrap native Joomla date/form classes with database access prohibited. */
    protected function setUp(): void
    {
        if (!is_file((string) getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5/6 tree.');
        }
        require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
    }

    /** @return iterable<string, array{string, int, array<string>}> Annual cadence and century boundaries. */
    public static function yearlyRules(): iterable
    {
        yield 'annual leap day' => ['2028-02-29', 1, ['2032-02-29', '2036-02-29', '2040-02-29']];
        yield 'every two years' => ['2028-02-29', 2, ['2032-02-29', '2036-02-29']];
        yield 'every three years' => ['2028-02-29', 3, ['2040-02-29', '2052-02-29']];
        yield 'every four years' => ['2028-02-29', 4, ['2032-02-29', '2036-02-29']];
        yield '2100 is not a leap year' => ['2096-02-29', 1, ['2104-02-29', '2108-02-29']];
        yield 'four years across 2100' => ['2096-02-29', 4, ['2104-02-29']];
        yield '2400 is a leap year' => ['2396-02-29', 1, ['2400-02-29', '2404-02-29']];
        yield 'ordinary anniversary' => ['2028-11-09', 3, ['2031-11-09', '2034-11-09']];
        yield 'year end' => ['2028-12-31', 1, ['2029-12-31', '2030-12-31']];
    }

    /**
     * Skip nonexistent anniversaries without changing cadence, time, duration or cutoff.
     * @param string $start First occurrence date.
     * @param int $interval Year interval.
     * @param array<string> $expected Consecutive valid occurrences.
     * @return void
     */
    #[DataProvider('yearlyRules')]
    public function testYearlyRecurrence(string $start, int $interval, array $expected): void
    {
        foreach (['UTC', 'Europe/Madrid', 'America/New_York'] as $timezone) {
            date_default_timezone_set($timezone);
            $row = ['dates' => $start, 'enddates' => (new DateTimeImmutable($start))->modify('+2 days')->format('Y-m-d'),
                'times' => '10:30:00', 'endtimes' => '12:00:00', 'recurrence_type' => '5',
                'recurrence_number' => $interval, 'recurrence_limit_date' => '2450-12-31'];
            foreach ($expected as $date) {
                $row = JemHelper::calculate_recurrence($row);
                self::assertSame($date, $row['dates']);
                self::assertSame((new DateTimeImmutable($date))->modify('+2 days')->format('Y-m-d'), $row['enddates']);
                self::assertSame('10:30:00', $row['times']);
                self::assertSame('12:00:00', $row['endtimes']);
                self::assertSame('2450-12-31', $row['recurrence_limit_date']);
            }
        }
    }

    /** @return void Reject stalled intervals and dates outside the SQL date range. */
    public function testYearlyGenerationHasFiniteBounds(): void
    {
        foreach ([0, -1, 4, 10000] as $interval) {
            self::assertFalse(JemHelper::calculate_recurrence([
                'dates' => '9996-02-29', 'enddates' => null, 'recurrence_type' => 5, 'recurrence_number' => $interval,
            ]));
        }
    }

    /** @return iterable<string, array{string, string, string}> Both editor XMLs and supported display patterns. */
    public static function editors(): iterable
    {
        foreach (['admin', 'site'] as $side) {
            foreach ([['%d.%m.%Y', 'd.m.Y'], ['%d-%m-%Y', 'd-m-Y'], ['%Y-%m-%d', 'Y-m-d'],
                ['%d/%m/%Y', 'd/m/Y'], ['%m/%d/%Y', 'm/d/Y']] as [$display, $filter]) {
                yield $side . ' ' . $filter => [$side, $display, $filter];
            }
        }
    }

    /**
     * Validate the actual editor fields, retaining rejected values instead of throwing.
     * @param string $side Editor source directory.
     * @param string $display Joomla strftime-style display pattern.
     * @param string $filter PHP date pattern.
     * @return void
     */
    #[DataProvider('editors')]
    public function testEditorRequiresCutoffOnlyForPeriodicRules(string $side, string $display, string $filter): void
    {
        $language = Factory::getApplication()->getLanguage();
        $strings = new ReflectionProperty($language, 'strings');
        $strings->setValue($language, array_replace($strings->getValue($language), [
            'DATE_FORMAT_CALENDAR_DATE' => $display, 'DATE_FORMAT_FILTER_DATE' => $filter,
        ]));
        Form::addFieldPath(JEM_TEST_ROOT . '/' . $side . '/models/fields');
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
        $fields = $xml->xpath('//field[@name="dates" or @name="recurrence_type" or @name="recurrence_limit_date"]');
        $form = new Form('cutoff.' . $side, ['control' => 'jform']);
        $definition = '<form>' . implode('', array_map(static fn ($field) => $field->asXML(), $fields)) . '</form>';
        $form->load($definition);
        // Each HTTP submission gets a fresh form/error collection.
        $process = static function (array $data) use (&$form, $definition, $side) {
            $form = new Form('cutoff.' . $side, ['control' => 'jform']);
            $form->load($definition);

            return $form->process($data);
        };
        $format = static fn (string $date): string => (new DateTimeImmutable($date))->format($filter);
        $start = $format('2028-02-29');
        $valid = $format('2040-02-29');

        foreach (range(1, 6) as $type) {
            foreach (['', '   ', null] as $empty) {
                $data = ['dates' => $start, 'recurrence_type' => (string) $type, 'recurrence_limit_date' => $empty];
                self::assertFalse($process($data));
                self::assertSame('Please enter an end date for the recurrence.', array_slice($form->getErrors(), -1)[0]->getMessage());
            }
            $result = $process(['dates' => $start, 'recurrence_type' => (string) $type, 'recurrence_limit_date' => $valid]);
            self::assertIsArray($result);
            self::assertSame('2040-02-29', $result['recurrence_limit_date']);
        }
        self::assertFalse($process(['dates' => $start, 'recurrence_type' => '5']));
        self::assertFalse($process(['dates' => $start, 'recurrence_type' => '5',
            'recurrence_limit_date' => $format('2027-02-28')]));
        self::assertSame('The recurrence end date cannot be earlier than the event start date.', array_slice($form->getErrors(), -1)[0]->getMessage());
        self::assertIsArray($process(['dates' => $start, 'recurrence_type' => '5', 'recurrence_limit_date' => $start]));

        $invalid = strtr($filter, ['Y' => '2031', 'm' => '02', 'd' => '29']);
        $data = ['dates' => $start, 'recurrence_type' => '5', 'recurrence_limit_date' => $invalid];
        self::assertSame($invalid, $form->filter($data)['recurrence_limit_date']);
        self::assertFalse($process($data));
        self::assertSame('Please enter a valid end date for the recurrence using the displayed date format.', array_slice($form->getErrors(), -1)[0]->getMessage());
        foreach (['0', '7'] as $type) {
            self::assertIsArray($process(['dates' => $start, 'recurrence_type' => $type, 'recurrence_limit_date' => '']));
        }
    }

    /** @return void Recheck the effective stored/submitted rule in the actual editor preview model. */
    public function testModelRejectsEmptyCutoffAndRetainsAnOmittedStoredCutoff(): void
    {
        require_once JEM_TEST_ROOT . '/admin/models/event.php';
        Factory::$application = new class (Factory::getApplication()) {
            public Joomla\Input\Input $input;
            /** @param object $base Read-only language and document services. */
            public function __construct(private object $base)
            {
                $this->input = new Joomla\Input\Input();
            }
            /** @return object Existing Joomla language object. */
            public function getLanguage(): object { return $this->base->getLanguage(); }
        };
        $model = new class extends JemModelEvent {
            /** Construct without booting MVC services. */
            public function __construct() {}
            /** @return object Synthetic stored row for the actual candidate method. */
            public function getTable($type = 'Event', $prefix = 'JemTable', $config = [])
            {
                return new class {
                    public int $recurrence_number = 1;
                    public string $recurrence_byday = '';
                    public string $recurrence_bylastday = '';
                    public string $attribs = '{}';
                    /** @return bool Whether the fixture row was requested. */
                    public function load($id): bool { return $id === 42; }
                    /** @return array Effective stored recurrence. */
                    public function getProperties(): array
                    {
                        return ['id' => 42, 'dates' => '2028-02-29', 'enddates' => null,
                            'recurrence_type' => 5, 'recurrence_limit_date' => '2040-02-29'];
                    }
                };
            }
            /** @param array $data Submitted fields. @return array Validated effective candidate. */
            public function candidate(array $data): array { return $this->recurrenceChangeCandidate($data); }
        };
        self::assertSame('2040-02-29', $model->candidate(['id' => 42])['recurrence_limit_date']);
        foreach (['', null, '2027-02-28'] as $value) {
            try {
                $model->candidate(['id' => 42, 'recurrence_limit_date' => $value]);
                self::fail('Invalid cutoff was accepted.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('recurrence', $error->getMessage());
            }
        }
        self::assertSame('', $model->candidate(['id' => 42, 'recurrence_type' => 0, 'recurrence_limit_date' => ''])['recurrence_limit_date']);
    }
}
