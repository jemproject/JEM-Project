<?php

declare(strict_types=1);

namespace JemProject\Tests\BackendRecurrence;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Exercise the production occurrence-save block and ACL guard with stored rows. */
final class BackendRecurrenceAclTest extends TestCase
{
    /** Load the exact production methods without starting Joomla or changing a database. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/admin/classes/backendacl.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/seriesinheritance.class.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        self::assertSame(1, preg_match('/    protected function protectBackendEventAclData\(.*?^    \}/ms', $source, $guard));
        $start = strpos($source, '            // Prepare complete rows before saving:');
        $end = strpos($source, '            $table->load($data[\'id\']);', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $block = substr($source, $start, $end - $start);
        self::assertSame(1, preg_match('/            \$diff = array_udiff_assoc\(.*?^            \}\);/ms', $source, $comparison));
        eval('namespace ' . __NAMESPACE__ . '; use \\RuntimeException; use \\JemSeriesInheritance;'
            . 'final class Model extends Boundary {' . $guard[0]
            . 'public function run(array $data, array $diff, bool $backend = true): bool {'
            . '$cats = [1];' . $block . 'return $saved;}'
            . 'public function differences(array $data, array $eventdb): array {'
            . $comparison[0] . 'return $diff;}}');
    }

    /** Reset the actor and all stored fixtures for every case. */
    protected function setUp(): void
    {
        JemHelperBackend::$permissions = ['jem.events.access', 'jem.events.edit.own'];
    }

    /** Confirm the reported title edit saves the parent and all four occurrences. */
    public function testRootTitleEditPreservesIdentitiesAndSchedules(): void
    {
        $model = new Model();
        $before = $model->rows;
        $data = array_replace($before[10], ['title' => 'Updated workshop']);
        unset($data['created_by']);
        self::assertTrue($model->run($data, ['title' => $data['title']]));
        self::assertCount(5, $model->saved);
        foreach ($model->saved as $id => $row) {
            self::assertSame('Updated workshop', $row['title']);
            foreach (['id', 'created_by', 'dates', 'recurrence_first_id', 'published', 'featured'] as $field) {
                self::assertSame($before[$id][$field], $row[$field], $field);
            }
        }
    }

    /** Saving a child must not change its parent or siblings. */
    public function testChildEditSavesOnlyThatOccurrence(): void
    {
        $model = new Model();
        self::assertTrue($model->run(array_replace($model->rows[12], ['title' => 'Child only']), ['title' => 'Child only']));
        self::assertSame([12], array_keys($model->saved));
    }

    /** Keep each occurrence's state and provenance when the editor lacks those grants. */
    public function testProtectedFieldsAreRestoredFromEachStoredOccurrence(): void
    {
        $model = new Model();
        $changes = ['title' => 'Changed', 'published' => 1, 'featured' => 1, 'ordering' => 9,
            'publish_up' => '2040-01-01 00:00:00', 'publish_down' => null,
            'created_by' => 999, 'created' => '2040-01-01 00:00:00'];
        self::assertTrue($model->run(array_replace($model->rows[10], $changes), $changes));
        foreach ($model->saved as $id => $row) {
            foreach (['published', 'featured', 'ordering', 'publish_up', 'publish_down', 'created_by', 'created'] as $field) {
                self::assertSame($model->rows[$id][$field], $row[$field], $field);
            }
        }
    }

    /** An allowed author change must not invalidate edit-own after authorisation. */
    public function testAuthorAndStatePermissionsRemainEffective(): void
    {
        JemHelperBackend::$permissions[] = 'jem.events.edit.created';
        JemHelperBackend::$permissions[] = 'jem.events.edit.state';
        $model = new Model();
        $data = array_replace($model->rows[12], ['created_by' => 999, 'created' => '2030-01-01 00:00:00', 'published' => 1, 'featured' => 1]);
        self::assertTrue($model->run($data, []));
        self::assertSame(999, $model->saved[12]['created_by']);
        self::assertSame(1, $model->saved[12]['published']);
        self::assertSame(1, $model->saved[12]['featured']);
    }

    /** Supply negative ownership, access, persistence and frontend boundaries. */
    public static function deniedCases(): iterable
    {
        yield 'forged parent author' => ['parent'];
        yield 'unowned child' => ['child'];
        yield 'no component access' => ['access'];
        yield 'invalid category' => ['category'];
        yield 'missing stored event' => ['missing'];
        yield 'frontend denied' => ['frontend'];
    }

    /** No invalid target or submitted owner may bypass occurrence checks. */
    #[DataProvider('deniedCases')]
    public function testInvalidSavesAreRejected(string $case): void
    {
        $model = new Model();
        $data = $model->rows[10];
        if ($case === 'parent') { $model->rows[10]['created_by'] = 999; }
        if ($case === 'child') { $model->rows[14]['created_by'] = 999; }
        if ($case === 'access') { JemHelperBackend::$permissions = ['jem.events.edit.own']; }
        if ($case === 'category') { $data['cats'] = [404]; }
        if ($case === 'missing') { $data['id'] = 404; $data['recurrence_first_id'] = 10; }
        $this->expectException(RuntimeException::class);
        try {
            $model->run($data, [], $case !== 'frontend');
        } finally {
            self::assertSame([], $model->saved);
        }
    }

    /** A wider edit grant allows another author's stored occurrence. */
    public function testEditPermissionAllowsAnotherAuthorsEvent(): void
    {
        JemHelperBackend::$permissions = ['jem.events.access', 'jem.events.edit'];
        $model = new Model();
        $model->rows[12]['created_by'] = 999;
        self::assertTrue($model->run($model->rows[12], []));
        self::assertSame(999, $model->saved[12]['created_by']);
    }

    /** Preserve scalar form/DB equivalence while detecting real array changes without warnings. */
    public function testArrayFormValuesDoNotCauseWarningsOrFalseScheduleChanges(): void
    {
        $model = new Model();
        $stored = ['recurrence_number' => '1', 'attribs' => ['mode' => 1], 'cats' => '1'];
        self::assertSame([], $model->differences(['recurrence_number' => 1, 'attribs' => ['mode' => 1], 'cats' => '1'], $stored));
        self::assertSame(['attribs' => ['mode' => 2]], $model->differences(['attribs' => ['mode' => 2]], $stored));
        self::assertSame(['attribs' => []], $model->differences(['attribs' => []], ['attribs' => '{}']));
    }
}

/** Narrow persistence and permission boundaries for the production save block. */
class Boundary
{
    public array $rows = [];
    public array $saved = [];
    public string $error = '';

    /** Build five stored occurrences with deliberately different publication values. */
    public function __construct()
    {
        for ($id = 10; $id < 15; ++$id) {
            $this->rows[$id] = ['id' => $id, 'title' => 'Workshop', 'created_by' => 7, 'created' => '2026-01-01 10:00:00',
                'dates' => '2030-06-' . ($id - 9), 'recurrence_first_id' => $id === 10 ? 0 : 10,
                'published' => $id % 2, 'featured' => $id % 2, 'cats' => [1], 'ordering' => $id,
                'publish_up' => '2026-01-01 10:00:00', 'publish_down' => '2030-07-01 10:00:00'];
        }
    }

    /** Return a read-only table facade containing only stored event fields. */
    public function getTable(): object
    {
        return new class ($this) extends \stdClass {
            public function __construct(private Boundary $model) {}
            public function load($id): bool
            {
                if (!isset($this->model->rows[$id])) { return false; }
                foreach ($this->model->rows[$id] as $key => $value) { $this->$key = $value; }
                return true;
            }
            public function getProperties(): array { return array_filter(get_object_vars($this), static fn ($key) => $key !== 'model', ARRAY_FILTER_USE_KEY); }
        };
    }

    public function save($data): bool { $this->saved[$data['id']] = $data; return true; }
    public function setError($message): void { $this->error = $message; }
    public function getError(): string { return $this->error; }
    public function getListRecurrenceEventsbyId(...$args): array { return array_map(static fn ($row) => (object) $row, $this->rows); }
    public function normaliseEventCategoryIds($cats): array { return (array) $cats; }
    public function getEventCategoryIds($id): array { return $this->rows[$id]['cats']; }
    public function canReplaceRecurrenceChild($operation, $event): bool { return JemHelperBackend::can('event', $operation, (object) $event); }
    public function validateEventCategoryIds($cats, ...$args): bool { return $cats === [1]; }
    public function protectFrontendFeaturedData(&$event, $cats): bool { return false; }
    public function validateSpecialDayEventDates($event): bool { return true; }
    public function _storeCategoriesSelected(...$args): bool { return true; }
}

/** Use the real 5.0.2 component ACL policy, with an actor whose id is 7. */
final class JemHelperBackend
{
    public static array $permissions = [];
    public static function can($type, $operation, $record): bool
    {
        return \JemBackendAclPolicy::allows($type, $operation, $record->created_by ?? null, 7,
            static fn ($action) => in_array($action, self::$permissions, true));
    }
}

/** Return stable untranslated error identifiers. */
final class Text
{
    public static function _($key): string { return $key; }
}
