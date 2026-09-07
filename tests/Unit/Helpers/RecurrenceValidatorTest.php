<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/recurrencevalidator.class.php';

final class RecurrenceValidatorTest extends TestCase
{
    public function testNormalisesValidWeekdaySelections(): void
    {
        self::assertSame(array('MO', 'FR'), JemRecurrenceValidator::normaliseWeekdays('mo, FR'));
        self::assertSame(array('TU', 'SU'), JemRecurrenceValidator::normaliseWeekdays(array('TU', 'SU')));
        self::assertSame(array('MO', 'FR'), JemRecurrenceValidator::normaliseWeekdays('MO,FR,MO'));
    }

    public function testRejectsEmptyAndInvalidWeekdaySelections(): void
    {
        self::assertFalse(JemRecurrenceValidator::normaliseWeekdays(null));
        self::assertFalse(JemRecurrenceValidator::normaliseWeekdays(''));
        self::assertFalse(JemRecurrenceValidator::normaliseWeekdays('0'));
        self::assertFalse(JemRecurrenceValidator::normaliseWeekdays('MO,XX'));
        self::assertFalse(JemRecurrenceValidator::normaliseWeekdays('MO,'));
        self::assertFalse(JemRecurrenceValidator::normaliseWeekdays(array()));
    }

    public function testCleanupSkipsInvalidRulesWithoutFrontendMessages(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');

        self::assertStringNotContainsString(
            "enqueueMessage(Text::_('COM_JEM_WRONG_EVENTRECURRENCE_WEEKDAY')",
            $helper
        );
        self::assertStringContainsString('Skipping invalid recurrence definition for event ID', $helper);
        self::assertSame(2, substr_count($helper, '$nextRecurrence === false'));
    }

    public function testEventSaveRejectsInvalidWeekdayRules(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');

        self::assertStringContainsString(
            'JemRecurrenceValidator::normaliseWeekdays($recurrencebyday)',
            $model
        );
        self::assertStringContainsString("Text::_('COM_JEM_WRONG_EVENTRECURRENCE_WEEKDAY')", $model);
    }
}
