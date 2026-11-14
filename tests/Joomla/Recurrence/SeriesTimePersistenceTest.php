<?php

declare(strict_types=1);

namespace JemProject\Tests\SeriesTimePersistence;

use JemSeriesInheritance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Resolve real inheritance policies and execute the production table preparation. */
final class SeriesTimePersistenceTest extends TestCase
{
    /** Load the unchanged policy and the model methods with isolated persistence boundaries. */
    public static function setUpBeforeClass(): void
    {
        require_once getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/site/classes/seriesinheritance.class.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $methods = '';
        foreach (['_prepareTable', 'saveOccurrenceWithResolvedTimes'] as $method) {
            self::assertSame(1, preg_match('/    protected function ' . $method . '\(.*?^    \}/ms', $source, $match));
            $methods .= $match[0];
        }
        self::assertSame(1, preg_match('/    protected bool \$preserveOccurrenceTimes = false;/', $source, $property));
        eval('namespace ' . __NAMESPACE__ . '; class Model extends PersistenceBoundary {' . $property[0] . $methods . '}');
    }

    /** Start each test with stale child selectors and no stored save context. */
    protected function setUp(): void
    {
        Factory::$input = new InputBoundary(['starthours' => '16', 'startminutes' => '00', 'endhours' => '17', 'endminutes' => '00']);
    }

    /** Provide a complete occurrence whose identity must survive time restoration. */
    private function event(): array
    {
        return ['id' => 20, 'recurrence_first_id' => 10, 'inherit' => 1, 'dates' => '2030-06-15',
            'times' => '16:00:00', 'endtimes' => '17:00:00', 'title' => 'Talk &amp; questions', 'version' => 1,
            'online_meeting_url' => 'https://example.test/child',
            'attribs' => ['series_inheritance' => ['overrides' => ['times', 'online']]]];
    }

    /** Cover re-enabling a single group and re-enabling global inheritance. */
    public static function restorationCases(): iterable
    {
        yield 'time override' => [1, '10:00:00', '11:00:00'];
        yield 'global inheritance off' => [0, '10:00:00', '11:00:00'];
        yield 'midnight' => [1, '00:00:00', '01:00:00'];
        yield 'unknown end' => [1, '10:00:00', null];
        yield 'both times unknown' => [1, null, null];
    }

    /** Resolved hours must survive table preparation even with stale request selectors. */
    #[DataProvider('restorationCases')]
    public function testRestoredHoursReachPersistence(int $inherit, ?string $start, ?string $end): void
    {
        $child = $this->event();
        $child['inherit'] = $inherit;
        $root = array_replace($child, ['id' => 10, 'recurrence_first_id' => 0, 'times' => $start, 'endtimes' => $end]);
        $submitted = array_replace($child, ['series_inheritance_present' => 1, 'series_inherit' => 1,
            'series_inherit_fields' => array_values(array_diff(array_keys(JemSeriesInheritance::GROUPS), ['online']))]);
        $resolved = JemSeriesInheritance::submission($submitted, $child, $root);
        $model = new Model();
        self::assertTrue($model->persistOccurrence($resolved));
        $saved = $model->saved[0];
        self::assertSame($start, $saved['times']);
        self::assertSame($end, $saved['endtimes']);
        self::assertNotContains('times', JemSeriesInheritance::policy($saved)['overrides']);
        self::assertContains('online', JemSeriesInheritance::policy($saved)['overrides']);
        foreach (['id', 'dates', 'recurrence_first_id', 'online_meeting_url'] as $field) {
            self::assertSame($child[$field], $saved[$field]);
        }
        self::assertSame('Talk & questions', $saved['title']);
        self::assertSame(2, $saved['version']);
        self::assertFalse($model->preserving());
    }

    /** A parent title edit may not replace the independent hours with the parent's selectors. */
    public function testParentContentPropagationPreservesIndependentChildHours(): void
    {
        $child = $this->event();
        $root = array_replace($child, ['id' => 10, 'times' => '10:00:00', 'endtimes' => '11:00:00']);
        Factory::$input = new InputBoundary(['starthours' => '10', 'endhours' => '11']);
        $resolved = JemSeriesInheritance::propagate($child, $root, ['title' => 'Updated title']);
        $model = new Model();
        self::assertTrue($model->persistOccurrence($resolved));
        self::assertSame('Updated title', $model->saved[0]['title']);
        self::assertSame('16:00:00', $model->saved[0]['times']);
        self::assertSame('17:00:00', $model->saved[0]['endtimes']);
        self::assertContains('times', JemSeriesInheritance::policy($model->saved[0])['overrides']);
    }

    /** New manual hours remain editable when time inheritance is disabled. */
    public function testIndependentManualEditPersists(): void
    {
        $child = $this->event();
        $submitted = array_replace($child, ['times' => '18:30:00', 'endtimes' => '20:00:00',
            'series_inheritance_present' => 1, 'series_inherit' => 1,
            'series_inherit_fields' => array_values(array_diff(array_keys(JemSeriesInheritance::GROUPS), ['times', 'online']))]);
        $resolved = JemSeriesInheritance::submission($submitted, $child, $child);
        $model = new Model();
        self::assertTrue($model->persistOccurrence($resolved));
        self::assertSame('18:30:00', $model->saved[0]['times']);
        self::assertSame('20:00:00', $model->saved[0]['endtimes']);
        self::assertContains('times', JemSeriesInheritance::policy($model->saved[0])['overrides']);
    }

    /** Enumerate normal save outcomes and failures which must restore the previous context. */
    public static function saveOutcomes(): iterable
    {
        yield 'success' => ['success'];
        yield 'false result' => ['false'];
        yield 'exception' => ['throw'];
    }

    /** A later ordinary save still uses its own selectors after any occurrence-save outcome. */
    #[DataProvider('saveOutcomes')]
    public function testContextNeverLeaksToTheNextSave(string $outcome): void
    {
        $model = new Model();
        $model->outcome = $outcome;
        try {
            self::assertSame($outcome === 'success', $model->persistOccurrence($this->event()));
            self::assertNotSame('throw', $outcome);
        } catch (RuntimeException $error) {
            self::assertSame('throw', $outcome);
            self::assertSame('Persistence failure', $error->getMessage());
        }
        self::assertFalse($model->preserving());
        $model->outcome = 'success';
        Factory::$input = new InputBoundary(['starthours' => '09', 'startminutes' => '15', 'endhours' => '10']);
        self::assertTrue($model->save($this->event()));
        $saved = $model->saved[array_key_last($model->saved)];
        self::assertSame('09:15', $saved['times']);
        self::assertSame('10:00', $saved['endtimes']);
    }

    /** A nested occurrence save restores the outer context before its preparation. */
    public function testNestedSaveRetainsOuterResolvedTimes(): void
    {
        $model = new Model();
        $model->nested = array_replace($this->event(), ['id' => 21, 'times' => '12:00:00']);
        self::assertTrue($model->persistOccurrence(array_replace($this->event(), ['times' => '10:00:00'])));
        self::assertSame('12:00:00', $model->saved[0]['times']);
        self::assertSame('10:00:00', $model->saved[1]['times']);
        self::assertFalse($model->preserving());
    }
}

/** Capture data reaching persistence after the real preparation method. */
class PersistenceBoundary
{
    public array $saved = [];
    public string $outcome = 'success';
    public ?array $nested = null;

    /** Invoke the production occurrence-save wrapper. */
    public function persistOccurrence(array $data): bool
    {
        return $this->saveOccurrenceWithResolvedTimes($data);
    }

    /** Observe only the model-internal preservation context. */
    public function preserving(): bool
    {
        return $this->preserveOccurrenceTimes;
    }

    /** Simulate Joomla's preparation and persistence boundary, including failure paths. */
    public function save(array $data): bool
    {
        if ($this->nested !== null) {
            $nested = $this->nested;
            $this->nested = null;
            $this->persistOccurrence($nested);
        }
        $table = (object) $data;
        $this->_prepareTable($table);
        if ($this->outcome === 'throw') {
            throw new RuntimeException('Persistence failure');
        }
        if ($this->outcome === 'false') {
            return false;
        }
        $this->saved[] = (array) $table;
        return true;
    }
}

/** Minimal request boundary containing only editor time selectors. */
final class InputBoundary
{
    /** Store the selectors supplied by a test. */
    public function __construct(private array $values) {}

    /** Return one selector or its production default. */
    public function get(string $key, $default = '', $filter = '')
    {
        return $this->values[$key] ?? $default;
    }
}

/** Supply the two Joomla services read by table preparation. */
final class Factory
{
    public static InputBoundary $input;

    /** Return the controlled editor input. */
    public static function getApplication(): object
    {
        return (object) ['input' => self::$input];
    }

    /** Return an unused database placeholder without contacting a live database. */
    public static function getContainer(): object
    {
        return new class {
            /** Match the database service lookup made by the production method. */
            public function get(string $service): object
            {
                return new \stdClass();
            }
        };
    }
}
