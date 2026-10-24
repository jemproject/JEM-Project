<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImportEventDefaultsTest extends TestCase
{
    public function testBothEventTablesNormaliseMissingAttribsBeforeStore(): void
    {
        foreach (array('event.php', 'jem_events.php') as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/admin/tables/' . $file);

            self::assertStringContainsString("\$this->attribs === null || \$this->attribs === ''", $source);
            self::assertStringContainsString("\$this->attribs = '{}';", $source);
        }
    }
}
