<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise the production comparison and its downstream inheritance consumer. */
final class RecurringEventComparisonTest extends TestCase
{
    private static string $comparison;
    private static array $contentFields;

    /** @return void Load the exact model block and Joomla Registry without booting a site. */
    public static function setUpBeforeClass(): void
    {
        $autoload = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\') . '/libraries/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT for the Joomla Registry dependency.');
        }
        require_once $autoload;
        require_once JEM_TEST_ROOT . '/site/classes/seriesinheritance.class.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $start = strpos($source, '            // Get the fields changed');
        $end = strpos($source, '            //If $diff contains', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        self::$comparison = substr($source, $start, $end - $start);
        self::assertSame(1, preg_match('/\$fieldAllow = \[.*?\];/', $source, $match));
        eval($match[0]);
        self::$contentFields = array_flip($fieldAllow);
    }

    /**
     * @param array $data Submitted form values.
     * @param array $eventdb Stored event values.
     * @return array Actual production differences; any PHP warning fails the test.
     */
    private function differences(array $data, array $eventdb): array
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            eval('use Joomla\\Registry\\Registry;' . self::$comparison);
            return $diff;
        } finally {
            restore_error_handler();
        }
    }

    /** @return array Parent before an edit, with database scalar types. */
    private function parentEvent(): array
    {
        return ['id' => 10, 'recurrence_first_id' => '0', 'recurrence_type' => '2',
            'recurrence_number' => '1', 'title' => 'Workshop', 'introtext' => 'Original description',
            'locid' => '1', 'cats' => '1', 'dates' => '2031-11-03', 'attribs' => '{}',
            'metadata' => '{}', 'image_metadata' => '{}'];
    }

    /** @return void Nested arrays must not warn or discard content needed by inheriting children. */
    public function testNestedFormDataPreservesInheritedContent(): void
    {
        $parent = $this->parentEvent();
        $submitted = array_replace($parent, ['title' => 'Updated workshop', 'introtext' => 'New description',
            'locid' => 2, 'cats' => '2', 'attribs' => [], 'metadata' => ['robots' => ['index' => true]],
            'image_metadata' => ['alt' => 'Workshop image']]);
        $diff = $this->differences($submitted, $parent);
        self::assertArrayNotHasKey('recurrence_number', $diff);
        self::assertArrayNotHasKey('recurrence_type', $diff);
        $child = array_replace($parent, ['id' => 11, 'recurrence_first_id' => 10, 'dates' => '2031-11-10',
            'inherit' => 1, 'attribs' => ['series_inheritance' => ['overrides' => []]]]);
        $changes = array_intersect_key($diff, self::$contentFields);
        $result = JemSeriesInheritance::propagate($child, $parent, $changes);
        foreach (['title', 'introtext', 'locid', 'cats', 'image_metadata'] as $field) {
            self::assertSame($submitted[$field], $result[$field], $field);
        }
        foreach (['id', 'recurrence_first_id', 'dates'] as $field) {
            self::assertSame($child[$field], $result[$field], $field);
        }
        $child['attribs']['series_inheritance']['overrides'] = ['title', 'venue'];
        $result = JemSeriesInheritance::propagate($child, $parent, $changes);
        self::assertSame($child['title'], $result['title']);
        self::assertSame($child['locid'], $result['locid']);
        self::assertSame($submitted['introtext'], $result['introtext']);
        $child['inherit'] = 0;
        self::assertSame($child, JemSeriesInheritance::propagate($child, $parent, $changes));
    }

    /** @return iterable<string, array{string, mixed, mixed, bool}> Form/database scalar equivalence. */
    public static function recurrenceValues(): iterable
    {
        foreach (['recurrence_first_id', 'recurrence_number', 'recurrence_type', 'recurrence_counter',
            'recurrence_limit', 'recurrence_limit_date', 'recurrence_byday', 'recurrence_bylastday'] as $field) {
            yield $field . ' same scalar' => [$field, '1', 1, false];
            yield $field . ' changed scalar' => [$field, '1', 2, true];
            yield $field . ' empty' => [$field, null, '', false];
        }
    }

    /**
     * @param string $field Recurrence field being compared.
     * @param mixed $stored Database value.
     * @param mixed $submitted Form value.
     * @param bool $changed Expected change detection.
     * @return void Keep scalar comparison behavior while ignoring unrelated arrays.
     */
    #[DataProvider('recurrenceValues')]
    public function testRecurrenceChangeDetection(string $field, mixed $stored, mixed $submitted, bool $changed): void
    {
        $diff = $this->differences([$field => $submitted, 'attribs' => [], 'metadata' => ['robots' => []]],
            [$field => $stored, 'attribs' => '{}', 'metadata' => '{}']);
        self::assertSame($changed, array_key_exists($field, $diff));
        if ($changed) {
            self::assertSame($submitted, $diff[$field]);
        }
        $omitted = $this->differences(['attribs' => []], [$field => $stored]);
        self::assertArrayNotHasKey($field, $omitted);
    }

    /** @return void Array changes keep their type, and unchanged nested data produces no difference. */
    public function testNestedValuesAndAttributesRetainTheirRepresentation(): void
    {
        $nested = ['alt' => 'Workshop', 'options' => ['visible' => true, 'width' => 640]];
        $stored = ['attribs' => '{}', 'image_metadata' => $nested];
        self::assertSame([], $this->differences(['attribs' => [], 'image_metadata' => $nested], $stored));
        $changed = $nested;
        $changed['options']['visible'] = false;
        $diff = $this->differences(['attribs' => [], 'image_metadata' => $changed], $stored);
        self::assertSame(['image_metadata' => $changed], $diff);
        $diff = $this->differences(['attribs' => ['recurrence_exclusions' => ['2031-11-10']]], $stored);
        self::assertSame('{"recurrence_exclusions":["2031-11-10"]}', $diff['attribs']);
    }
}
