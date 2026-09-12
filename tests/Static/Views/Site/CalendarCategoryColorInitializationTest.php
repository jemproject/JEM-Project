<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CalendarCategoryColorInitializationTest extends TestCase
{
    public function testCalendarTemplatesInitializeCategoryColorOutput(): void
    {
        $templates = array(
            'site/views/venue/tmpl/calendar.php',
            'site/views/venue/tmpl/responsive/calendar.php',
            'site/views/weekcal/tmpl/default.php',
        );

        foreach ($templates as $relativePath) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertMatchesRegularExpression(
                '/\$multicatname = \'\';\s+\$color = \'\';\s+\$colorpic = \'\';/',
                $source,
                $relativePath
            );
        }
    }
}
