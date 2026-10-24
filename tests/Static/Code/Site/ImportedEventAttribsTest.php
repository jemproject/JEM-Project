<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImportedEventAttribsTest extends TestCase
{
    public function testAllFrontendEventReadersAcceptNullAttribs(): void
    {
        $event = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $eventslist = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');

        self::assertSame(1, substr_count($event, "loadString(\$data->attribs ?? '{}')"));
        self::assertSame(1, substr_count($event, "loadString(\$data[0]->attribs ?? '{}')"));
        self::assertSame(1, substr_count($eventslist, "loadString(\$item->attribs ?? '{}')"));
    }
}
