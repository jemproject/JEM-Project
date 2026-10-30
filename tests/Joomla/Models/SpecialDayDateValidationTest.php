<?php

declare(strict_types=1);

use Joomla\CMS\Form\Form;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SpecialDayDateValidationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!is_file((string) getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a read-only Joomla 5/6 tree.');
        }
        require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
    }

    public static function invalidDates(): iterable
    {
        foreach (['2027-02-30', '2027-02-29', '2027-04-31', '2027-13-10',
            '2027-00-10', '2027-10-00', '0000-00-00', '2027-02-28 12:00:00',
            '2027-2-28', 'tomorrow', '2027-02-28 garbage'] as $date) {
            yield $date => [$date];
        }
    }

    private function form(string $side): Form
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/specialday.xml');
        $dates = $xml->xpath('//field[@name="start_date" or @name="end_date"]');
        Form::addFieldPath(JEM_TEST_ROOT . '/admin/models/fields');
        $form = new Form('specialday.' . $side);
        $form->load('<form>' . implode('', array_map(static fn ($field) => $field->asXML(), $dates)) . '</form>');
        return $form;
    }

    private function table($start, $end, int $id = 0): jem_special_days
    {
        $table = (new ReflectionClass(jem_special_days::class))->newInstanceWithoutConstructor();
        $table->setDispatcher(new Dispatcher());
        $table->id = $id;
        $table->title = 'Regression date';
        $table->alias = 'regression-date';
        $table->day_type_id = 1;
        $table->day_type = 'Holiday';
        $table->created = '2026-10-30 12:00:00';
        $table->created_by = 1;
        $table->start_date = $start;
        $table->end_date = $end;
        return $table;
    }

    #[DataProvider('invalidDates')]
    public function testBothFormsRejectInvalidStartAndEndDates(string $date): void
    {
        foreach (['admin', 'site'] as $side) {
            $form = $this->form($side);
            foreach (['start_date', 'end_date'] as $field) {
                self::assertInstanceOf(JFormFieldSpecialdaycalendar::class, $form->getField($field));
                $data = ['start_date' => '2027-02-28', 'end_date' => '2027-02-28', $field => $date];
                $result = $form->process($data);
                self::assertFalse($result);
                self::assertStringContainsString('not a valid calendar date', $form->getErrors()[0]->getMessage());
                self::assertSame($date, $data[$field]);
            }
        }
    }

    #[DataProvider('invalidDates')]
    public function testTableRejectsBypassedFormsAndPreservesRejectedValue(string $date): void
    {
        foreach ([0, 12] as $id) {
            foreach (['start_date', 'end_date'] as $field) {
                $table = $this->table('2027-02-28', '2027-02-28', $id);
                $table->$field = $date;
                self::assertFalse($table->check());
                self::assertStringContainsString('not a valid calendar date', (string) $table->getError());
                self::assertSame($date, $table->$field);
            }
        }
    }

    public function testValidLeapDatesAndSameDayRangesRemainValid(): void
    {
        foreach (['admin', 'site'] as $side) {
            $form = $this->form($side);
            foreach (['1970-01-01', '2028-02-29', '2027-04-30'] as $date) {
                self::assertSame('%Y-%m-%d', $form->getField('start_date')->format);
                $result = $form->process(['start_date' => $date, 'end_date' => $date]);
                self::assertSame(['start_date' => $date, 'end_date' => $date], $result);
                foreach ([0, 12] as $id) {
                    $table = $this->table($result['start_date'], $result['end_date'], $id);
                    self::assertTrue($table->check());
                    self::assertSame($date, $table->start_date);
                    self::assertSame($date, $table->end_date);
                }
            }
        }
    }

    public function testEmptyDatesRemainRequired(): void
    {
        foreach (['admin', 'site'] as $side) {
            self::assertFalse($this->form($side)->process(['start_date' => '', 'end_date' => '']));
        }
        self::assertFalse($this->table('', '')->check());
    }

    public function testExistingReversedRangePolicyIsPreserved(): void
    {
        $table = $this->table('2027-03-02', '2027-02-28');
        self::assertTrue($table->check());
        self::assertSame('2027-02-28', $table->start_date);
        self::assertSame('2027-03-02', $table->end_date);
    }

    public static function csvDates(): iterable
    {
        yield 'ISO' => ['2028-02-29', '2028-02-29'];
        yield 'day first' => ['29-02-2028', '2028-02-29'];
        yield 'unpadded day first' => ['9-2-2028', '2028-02-09'];
        yield 'unambiguous slash' => ['29/02/2028', '2028-02-29'];
        yield 'epoch' => ['1970-01-01', '1970-01-01'];
        foreach (['2027-02-30', '30-02-2027', '31/04/2027', '2027-13-10',
            '2027-02-29', '02/03/2027', 'tomorrow', 'NULL', '0000-00-00', ''] as $invalid) {
            yield $invalid => [$invalid, null];
        }
    }

    #[DataProvider('csvDates')]
    public function testAllCsvEntryPointsUseSameStrictParser(string $date, ?string $expected): void
    {
        self::assertSame($expected, JemSpecialDayDate::fromCsv($date));
        foreach (['admin/controllers/specialdays.php', 'site/controllers/specialdays.php'] as $source) {
            // Separate anonymous class avoids the identical BE/FE controller class names.
            $code = file_get_contents(JEM_TEST_ROOT . '/' . $source);
            preg_match('/private function normaliseSpecialDayCsvDate\(\$date\)\s*\{([^}]+)\}/', $code, $matches);
            self::assertNotEmpty($matches);
            $normalizer = eval('return new class { public function parse($date) {' . $matches[1] . '} };');
            self::assertSame($expected, $normalizer->parse($date), $source);
        }
        require_once JEM_TEST_ROOT . '/admin/controllers/import.php';
        $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, 'normaliseSpecialDaysDate');
        self::assertSame($expected, $method->invoke($controller, $date));
    }

    public function testPreviewRejectsInvalidEndButAllowsOmittedEnd(): void
    {
        require_once JEM_TEST_ROOT . '/admin/controllers/import.php';
        $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, 'normaliseSpecialDaysCsvRow');
        $data = ['title' => 'Holiday', 'day_type_id' => 1, 'day_type' => 'Holiday', 'start_date' => '2027-02-28'];
        foreach (['2027-02-30', '2027-02-29', 'NULL', '0000-00-00', 'nonsense'] as $end) {
            $result = $method->invoke($controller, $data + ['end_date' => $end], [], 2);
            self::assertFalse($result['valid'], $end);
            self::assertNotSame('2027-02-28', $result['record']['end_date']);
        }
        $result = $method->invoke($controller, $data, [], 2);
        self::assertTrue($result['valid']);
        self::assertSame('2027-02-28', $result['record']['end_date']);

        $result = $method->invoke($controller, $data + ['end_date' => '2027-02-27'], [], 2);
        self::assertTrue($result['valid']);
        self::assertSame('2027-02-27', $result['record']['start_date']);
        self::assertSame('2027-02-28', $result['record']['end_date']);
    }

    public function testRendererPreservesInvalidTextSafelyInsteadOfNormalisingIt(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/specialday.xml');
        foreach (['2027-02-30', 'not a date', '"><script>alert(1)</script>'] as $value) {
            $field = new SpecialDayFixtureCalendar();
            $field->setup($xml->xpath('//field[@name="start_date"]')[0], $value);
            $html = $field->input;
            self::assertStringContainsString('data-jem-invalid-date="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"', $html);
            self::assertStringContainsString('data-alt-value=""', $html);
            self::assertStringContainsString('validate-jemdate', $html);
            self::assertStringNotContainsString('<script>alert', $html);
            self::assertStringNotContainsString('2027-03-02', $html);
        }
    }

    public function testIcsExclusiveEndDoesNotConvertAnImpossibleDateIntoAValidOne(): void
    {
        require_once JEM_TEST_ROOT . '/admin/controllers/import.php';
        $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
        $source = new ReflectionMethod($controller, 'buildSpecialDaysIcsSourceRecords');
        $direct = new ReflectionMethod($controller, 'normaliseSpecialDaysIcsEvent');
        $preview = new ReflectionMethod($controller, 'normaliseSpecialDaysCsvRow');
        $options = ['day_type' => 'Holiday', 'day_type_id' => 1];
        foreach ([['20270228', '20270230', false], ['20270230', '20270303', false],
            ['20280229', '20280301', true]] as [$start, $end, $valid]) {
            $event = [
                'SUMMARY' => [['value' => 'Holiday']],
                'DTSTART' => [['raw' => $start, 'params' => ['VALUE' => 'DATE']]],
                'DTEND' => [['raw' => $end, 'params' => ['VALUE' => 'DATE']]],
            ];
            $result = $direct->invoke($controller, $event, $options, 1);
            self::assertSame($valid, $result['valid']);
            $records = $source->invoke($controller, [$event]);
            $result = $preview->invoke($controller, [
                'title' => 'Holiday',
                'start_date' => $records[0]['DTSTART'],
                'end_date' => $records[0]['DTEND'],
            ], $options, 1);
            self::assertSame($valid, $result['valid']);
            if ($valid) {
                self::assertSame('2028-02-29', $result['record']['end_date']);
            }
        }
    }

    public function testRendererKeepsValidDateWithoutTimezoneConversion(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/specialday.xml');
        foreach (['1970-01-01', '2028-02-29'] as $value) {
            $field = new SpecialDayFixtureCalendar();
            $field->setup($xml->xpath('//field[@name="start_date"]')[0], $value);
            $html = $field->input;
            self::assertStringContainsString('data-alt-value="' . $value . '"', $html);
            self::assertStringNotContainsString('data-jem-invalid-date', $html);
        }
    }
}

