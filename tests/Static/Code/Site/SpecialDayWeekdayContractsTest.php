<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SpecialDayWeekdayContractsTest extends TestCase
{
    public function testSpecialDayTablePreservesBlankAndSundayAsDifferentValues(): void
    {
        $table = (string) file_get_contents(JEM_TEST_ROOT . '/admin/tables/jem_special_days.php');

        self::assertStringContainsString('$array[\'weekdays\'] = $this->normaliseWeekdays($array[\'weekdays\']);', $table);
        self::assertStringContainsString('$this->weekdays = $this->normaliseWeekdays($this->weekdays);', $table);
        self::assertStringContainsString('$weekday === \'\' || !ctype_digit($weekday)', $table);
    }

    public function testCalendarUsesZeroAsSundayInsteadOfAnEmptyWeekdayFallback(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');

        self::assertStringContainsString('} elseif (empty($row->weekdays)) {', $helper);
        self::assertStringContainsString('if ($weekdays && !in_array((int) $date->format(\'w\'), $weekdays, true)) {', $helper);
        self::assertStringNotContainsString('$ignoreDefaultWeekday', $helper);
        self::assertStringNotContainsString('$hasMultiDayDatedRange', $helper);
    }
}
