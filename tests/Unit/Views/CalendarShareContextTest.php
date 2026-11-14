<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Calendar toolbar inputs must identify the displayed entity, including archived views. */
final class CalendarShareContextTest extends TestCase
{
    /** @return iterable Classic/responsive calendar views with current and archived events. */
    public static function layouts(): iterable
    {
        foreach (['venue', 'category'] as $view) {
            foreach (['calendar.php', 'responsive/calendar.php'] as $layout) {
                foreach (['', 'archive'] as $task) {
                    yield "$view/$layout/$task" => [$view, $layout, $task];
                }
            }
        }
    }

    /** @param string $view Entity view. @param string $layout Template. @param string $task Publication scope. @return void */
    #[DataProvider('layouts')]
    public function testToolbarReceivesSelectedEntity(string $view, string $layout, string $task): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/tmpl/' . $layout);
        self::assertSame(1, preg_match('/\$btn_params\s*=\s*(array\([^;]+\));/', $source, $match));
        $template = (object) [
            'venue' => (object) ['id' => 25, 'slug' => '25:auditorium'],
            'catid' => 42,
            'task' => $task,
            'print_link' => 'print-target',
            'pdf_link' => 'pdf-target',
            'ical_link' => 'ical-target',
            'archive_link' => 'archive-target',
        ];
        // Execute the actual template expression, with only its view data replaced.
        $build = eval('return function () { return ' . $match[1] . '; };');
        $params = $build->bindTo($template, null)();
        $expected = $view === 'venue' ? '25:auditorium' : 42;
        self::assertSame($expected, $params['slug'] ?? null, 'Copy Link and email require the selected entity.');
        self::assertSame($expected, $params['id'] ?? null);
        self::assertSame($task, $params['task']);
        self::assertSame('print-target', $params['print_link']);
        self::assertSame('ical-target', $params['ical_link']);
        self::assertSame('archive-target', $params['archive_link']);
        if ($view === 'venue') {
            self::assertSame('pdf-target', $params['pdf_link']);
        }
    }
}
