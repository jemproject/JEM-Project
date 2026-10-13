<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RecurrenceExclusionsContractTest extends TestCase
{
    public function testBothFormsAndAllEditorsExposeTheSelection(): void
    {
        foreach (['admin', 'site'] as $client) {
            $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $client . '/models/forms/event.xml');
            $fields = $xml->xpath('//field[@name="recurrence_exclusions"]');
            self::assertCount(1, $fields);
            self::assertSame('true', (string) $fields[0]['multiple']);
            self::assertSame('array', (string) $fields[0]['filter']);
            self::assertSame('recurrence_type:1,2,3,4,5,6', (string) $fields[0]['showon']);
        }
        foreach (['admin/views/event/tmpl/edit.php', 'site/views/editevent/tmpl/edit_extended.php',
            'site/views/editevent/tmpl/responsive/edit_extended.php'] as $file) {
            self::assertStringContainsString("renderField('recurrence_exclusions')", file_get_contents(JEM_TEST_ROOT . '/' . $file));
        }
    }

    public function testGenerationChecksExclusionsBeforeCreatingOrCopyingAnything(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        $guard = strpos($source, '$excluded = $exclusionRows');
        self::assertNotFalse($guard);
        $store = strpos($source, '$new_event->store()', $guard);
        self::assertGreaterThan($guard, $store);
        self::assertStringContainsString("(int) \$recurrence_row['first_id']", substr($source, strpos($source, '$policyQuery'), 250));
        $excluded = substr($source, strpos($source, 'if ($excluded)'), $store - strpos($source, 'if ($excluded)'));
        self::assertStringContainsString('calculate_recurrence($recurrence_row)', $excluded);
        self::assertStringContainsString('continue;', $excluded);
        self::assertStringNotContainsString("['counter']++", $excluded);
    }

    public function testChangingExclusionsDoesNotDissolveTheExistingSeries(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        preg_match('/\$fieldNotAllow = \[(.*?)\];/s', $source, $fields);
        self::assertStringNotContainsString('recurrence_exclusions', $fields[1]);
        self::assertStringNotContainsString('attribs', $fields[1]);
        self::assertStringContainsString("\$data['attribs']['recurrence_exclusions'] = \$exclusions", $source);
        self::assertStringContainsString('array_diff($exclusions, $previousExclusions, array_keys($availableExclusions))', $source);
        self::assertStringContainsString('A past root may be outside', $source);
    }
}
