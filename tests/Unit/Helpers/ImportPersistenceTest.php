<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importpersistence.php';

final class ImportPersistenceTest extends TestCase
{
    public function testNewExplicitIdUsesOnlyDatabaseColumns(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->name = 'New venue';
        self::assertSame('added', JemImportPersistenceHelper::store($table, $table->db, true));
        self::assertSame(['id' => 701, 'name' => 'New venue', 'published' => 0, 'description' => ''], $table->db->rows[701]);
        self::assertSame(0, $table->stores);
        self::assertSame(701, $table->id);
    }

    public function testExistingIdIsUpdatedWithoutAttemptingInsert(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->db->rows[701] = ['id' => 701, 'name' => 'Original'];
        $table->name = 'Updated';
        self::assertSame('updated', JemImportPersistenceHelper::store($table, $table->db, true));
        self::assertSame('Updated', $table->db->rows[701]['name']);
        self::assertSame(0, $table->db->inserts);
        self::assertSame(1, $table->stores);
    }

    public function testUnchangedExistingRowIsStillAnUpdate(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->name = 'Same';
        $table->db->rows[701] = ['id' => 701, 'name' => 'Same'];
        self::assertSame('updated', JemImportPersistenceHelper::store($table, $table->db, true));
        self::assertSame(0, $table->db->inserts);
    }

    public function testSqlInsertFailureNeverFallsBackToUpdate(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->db->insertException = new RuntimeException('Unknown column');
        try {
            JemImportPersistenceHelper::store($table, $table->db, true);
            self::fail('An SQL failure must propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('Unknown column', $e->getMessage());
        }
        self::assertSame(0, $table->stores);
        self::assertSame([], $table->db->rows);
    }

    public function testUniqueConflictOnAnotherColumnDoesNotOverwriteExistingData(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 702;
        $table->db->rows[701] = ['id' => 701, 'name' => 'Protected'];
        $table->db->insertException = new RuntimeException('Duplicate unique reference');
        try {
            JemImportPersistenceHelper::store($table, $table->db, true);
            self::fail('A unique-key conflict must be reported, not ignored.');
        } catch (RuntimeException $e) {
            self::assertSame('Duplicate unique reference', $e->getMessage());
        }
        self::assertSame([701 => ['id' => 701, 'name' => 'Protected']], $table->db->rows);
        self::assertSame(0, $table->stores);
    }

    public function testFalseInsertResultIsAnError(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->db->insertResult = false;
        $this->expectException(RuntimeException::class);
        JemImportPersistenceHelper::store($table, $table->db, true);
    }

    public function testFalseUpdateResultIsAnError(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->db->rows[701] = ['id' => 701];
        $table->storeResult = false;
        $this->expectException(RuntimeException::class);
        JemImportPersistenceHelper::store($table, $table->db, true);
    }

    public function testDisappearingUpdateTargetIsNotReportedAsUpdated(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->db->rows[701] = ['id' => 701];
        $table->deleteOnStore = true;
        $this->expectException(RuntimeException::class);
        JemImportPersistenceHelper::store($table, $table->db, true);
    }

    public function testReplaceDisabledDoesNotOverwriteSourceId(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->name = 'New';
        $table->db->rows[701] = ['id' => 701, 'name' => 'Original'];
        self::assertSame('added', JemImportPersistenceHelper::store($table, $table->db, false));
        self::assertSame(1, $table->id);
        self::assertSame('Original', $table->db->rows[701]['name']);
        self::assertSame('New', $table->db->rows[1]['name']);
    }

    public function testMissingSourceIdUsesNormalTableStore(): void
    {
        $table = new ImportPersistenceTableFixture();
        self::assertSame('added', JemImportPersistenceHelper::store($table, $table->db, true));
        self::assertSame(1, $table->stores);
        self::assertSame(1, $table->id);
    }

    public function testCategoryUpdateKeepsItsCsvStorePath(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->id = 701;
        $table->db->rows[701] = ['id' => 701];
        self::assertSame('updated', JemImportPersistenceHelper::store($table, $table->db, true, 'storeCsvImport'));
        self::assertSame(1, $table->csvStores);
    }

    public function testFailedNewRecordStoreIsAnError(): void
    {
        $table = new ImportPersistenceTableFixture();
        $table->storeResult = false;
        $this->expectException(RuntimeException::class);
        JemImportPersistenceHelper::store($table, $table->db, false);
    }

    public function testImportEntryPointsUseStrictPersistenceAndStopMigrationOnErrors(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/import.php');
        self::assertStringNotContainsString('->insertIgnore(', $model);
        self::assertSame(3, substr_count($model, 'JemImportPersistenceHelper::store('));
        $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        $start = strpos($controller, '$result = $model->storeJemData(');
        self::assertNotFalse($start);
        $stop = strpos($controller, "if (!empty(\$result['error']))", $start);
        $advance = strpos($controller, '$current += $size;', $start);
        self::assertNotFalse($stop);
        self::assertLessThan($advance, $stop);
        self::assertStringContainsString('return;', substr($controller, $stop, $advance - $stop));
    }

    public function testStrictSqlUpdateConvertsEmptyNullableFieldsAndPassesNulls(): void
    {
        $table = new ImportStrictTableFixture();
        $table->id = 701;
        $table->db->rows[701] = array('id' => 701, 'publish_down' => '2026-12-01 10:00:00');

        self::assertSame('updated', JemImportPersistenceHelper::store($table, $table->db, true, 'store', true));
        self::assertNull($table->publish_down);
        self::assertNull($table->series_id);
        self::assertTrue($table->updatedNulls);
    }

    public function testStrictSqlInsertConvertsLegacyZeroDate(): void
    {
        $table = new ImportStrictTableFixture();
        $table->id = 702;
        $table->publish_down = '0';

        self::assertSame('added', JemImportPersistenceHelper::store($table, $table->db, true));
        self::assertNull($table->db->rows[702]['publish_down']);
    }

    public function testSparseEventImportFillsOnlyOptionalTextAndOmitsDateExpression(): void
    {
        $table = new ImportStrictTableFixture();
        $table->id = 703;

        self::assertSame('added', JemImportPersistenceHelper::store($table, $table->db, true));
        self::assertSame('', $table->db->rows[703]['introtext']);
        self::assertSame('', $table->db->rows[703]['fulltext']);
        self::assertSame('', $table->db->rows[703]['metadata']);
        self::assertNull($table->db->rows[703]['created']);
        self::assertNull($table->db->rows[703]['notes']);
    }
}

final class ImportStrictTableFixture
{
    public int $id = 0;
    public string $title = 'Event';
    public $publish_down = '';
    public $series_id = '';
    public $introtext = null;
    public $fulltext = null;
    public $metadata = null;
    public $notes = null;
    public $created = 'current_timestamp()';
    public bool $updatedNulls = false;
    public ImportPersistenceDatabaseFixture $db;

    public function __construct()
    {
        $this->db = new ImportPersistenceDatabaseFixture();
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getTableName(): string
    {
        return '#__jem_fixture';
    }

    public function getFields(): array
    {
        return array(
            'id' => (object) array('Type' => 'int(11)', 'Null' => 'NO', 'Default' => null),
            'title' => (object) array('Type' => 'varchar(255)', 'Null' => 'NO', 'Default' => null),
            'publish_down' => (object) array('Type' => 'datetime', 'Null' => 'YES', 'Default' => null),
            'series_id' => (object) array('Type' => 'int(11)', 'Null' => 'YES', 'Default' => null),
            'introtext' => (object) array('Type' => 'mediumtext', 'Null' => 'NO', 'Default' => null),
            'fulltext' => (object) array('Type' => 'mediumtext', 'Null' => 'NO', 'Default' => null),
            'metadata' => (object) array('Type' => 'text', 'Null' => 'NO', 'Default' => null),
            'notes' => (object) array('Type' => 'text', 'Null' => 'NO', 'Default' => null),
            'created' => (object) array('Type' => 'datetime', 'Null' => 'NO', 'Default' => 'current_timestamp()'),
        );
    }

    public function getError(): string
    {
        return '';
    }

    public function store(bool $updateNulls = false): bool
    {
        $this->updatedNulls = $updateNulls;
        $this->db->rows[$this->id] = array(
            'id' => $this->id,
            'title' => $this->title,
            'publish_down' => $this->publish_down,
            'series_id' => $this->series_id,
        );

        return true;
    }
}

final class ImportPersistenceTableFixture
{
    public int $id = 0;
    public string $name = '';
    public int $published = 0;
    public string $description = '';
    public string $virtualField = 'must not reach SQL';
    protected bool $ageLevelSubmitted = true;
    public ImportPersistenceDatabaseFixture $db;
    public int $stores = 0;
    public int $csvStores = 0;
    public bool $storeResult = true;
    public bool $deleteOnStore = false;

    public function __construct()
    {
        $this->db = new ImportPersistenceDatabaseFixture();
    }

    protected function getDatabase(): ImportPersistenceDatabaseFixture
    {
        return $this->db;
    }

    public function getTableName(): string
    {
        return '#__jem_fixture';
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getFields(): array
    {
        return array_fill_keys(['id', 'name', 'published', 'description'], true);
    }

    public function getError(): string
    {
        return 'Forced table-store failure';
    }

    public function store(): bool
    {
        $this->stores++;
        if (!$this->storeResult) {
            return false;
        }
        if ($this->deleteOnStore) {
            unset($this->db->rows[$this->id]);
            return true;
        }
        if ($this->id === 0) {
            $this->id = 1;
        }
        $this->db->rows[$this->id] = ['id' => $this->id, 'name' => $this->name];
        return true;
    }

    public function storeCsvImport(): bool
    {
        $this->csvStores++;
        return $this->store();
    }
}

final class ImportPersistenceDatabaseFixture
{
    public array $rows = [];
    public int $inserts = 0;
    public ?RuntimeException $insertException = null;
    public bool $insertResult = true;
    private int $selectedId = 0;

    public function getQuery($new): self
    {
        return $this;
    }

    public function select($columns): self
    {
        return $this;
    }

    public function from($table): self
    {
        return $this;
    }

    public function quoteName($name): string
    {
        return $name;
    }

    public function setQuery($query): self
    {
        return $this;
    }

    public function loadResult(): int
    {
        return isset($this->rows[$this->selectedId]) ? 1 : 0;
    }

    public function where($condition)
    {
        $this->selectedId = (int) substr($condition, strpos($condition, '=') + 1);
        return $this;
    }

    public function insertObject($name, $record, $key): bool
    {
        $this->inserts++;
        if ($this->insertException) {
            throw $this->insertException;
        }
        if (!$this->insertResult) {
            return false;
        }
        $this->rows[$record->$key] = get_object_vars($record);
        return true;
    }
}
