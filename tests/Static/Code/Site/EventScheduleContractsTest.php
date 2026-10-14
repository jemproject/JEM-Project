<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventScheduleContractsTest extends TestCase
{
    public function testBothEditorsDeclareAndRenderTheProgramme(): void
    {
        foreach (['admin', 'site'] as $client) {
            $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $client . '/models/forms/event.xml');
            $fields = $xml->xpath('//field[@name="event_schedule"]');
            self::assertCount(1, $fields);
            self::assertSame('eventschedule', (string) $fields[0]['type']);
            self::assertSame('raw', (string) $fields[0]['filter']);
        }
        foreach (['admin/views/event/tmpl/edit.php', 'site/views/editevent/tmpl/edit.php',
            'site/views/editevent/tmpl/responsive/edit.php'] as $path) {
            self::assertStringContainsString("'event_schedule'", file_get_contents(JEM_TEST_ROOT . '/' . $path));
        }
    }

    public function testRegularSeriesPreflightPrecedesPersistenceAndIncludesPastRoot(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $branch = substr($model, strpos($model, '// This event is part of a recurrence series.'));
        $preflight = strpos($branch, '$this->validateEventScheduleBatch($events)');
        $write = strpos($branch, 'parent::save($event)');
        self::assertNotFalse($preflight);
        self::assertNotFalse($write);
        self::assertLessThan($write, $preflight);
        self::assertStringContainsString('($exclusionsSubmitted || $scheduleTimes !== null)', $branch);
    }
}
