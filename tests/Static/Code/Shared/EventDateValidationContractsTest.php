<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventDateValidationContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function calendarFieldProvider(): iterable
    {
        yield 'backend calendar field' => array(JEM_TEST_ROOT . '/admin/models/fields/calendarjem.php');
        yield 'frontend calendar field' => array(JEM_TEST_ROOT . '/site/models/fields/calendarjem.php');
    }

    #[DataProvider('calendarFieldProvider')]
    public function testFrontendAndBackendCalendarFieldsValidateBeforeNormalising(string $path): void
    {
        $contents = (string) file_get_contents($path);

        self::assertStringContainsString('public function filter($value, $group = null, ?Registry $input = null)', $contents);
        self::assertStringContainsString('createFromFormat(\'!\' . $format', $contents);
        self::assertStringContainsString('$date->format($format) !== (string) $value', $contents);
        self::assertStringContainsString("validate-jemdate", $contents);
        self::assertStringContainsString("useScript('jem.datevalidation')", $contents);
        self::assertStringContainsString('return parent::filter($value, $group, $input);', $contents);
    }

    public function testSharedSaveLayersRejectInvalidDatesBeforeDateComparisons(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $table = (string) file_get_contents(JEM_TEST_ROOT . '/admin/tables/event.php');
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');

        self::assertStringContainsString('normaliseEventDates($data)', $model);
        self::assertStringContainsString('JemHelper::isValidCalendarDate($value, $format)', $model);
        self::assertStringContainsString('JemHelper::isValidCalendarDate((string) $this->$field)', $table);
        self::assertStringContainsString('static public function isValidCalendarDate', $helper);

        self::assertLessThan(
            strpos($table, '// Check begin date is before end date'),
            strpos($table, '$dateFields = array('),
            'Strict date validation must run before start/end comparisons.'
        );
    }

    public function testBrowserValidatorDetectsJoomlaDateRollover(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/media/js/date-validation.js');

        self::assertStringContainsString("setHandler('jemdate', isStrictCalendarDate)", $script);
        self::assertStringContainsString('Date.parseFieldDate(', $script);
        self::assertStringContainsString('return roundTrip === inputValue;', $script);
        self::assertStringContainsString("if (inputValue === '')", $script);
    }
}
