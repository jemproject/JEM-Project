<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importauthor.php';
require_once JEM_TEST_ROOT . '/admin/helpers/importjournal.php';

final class ImportAuditTest extends TestCase
{
    public static function authorCases(): iterable
    {
        yield 'type creator, not importer' => ['types', ['core.create'], false, true];
        yield 'import permission alone is not authorship' => ['types', ['jem.tools.manage'], false, false];
        yield 'blocked administrator' => ['events', ['core.admin'], true, false];
        yield 'event creator' => ['events', ['jem.events.access', 'jem.events.create'], false, true];
        yield 'venue creator' => ['venues', ['jem.venues.access', 'jem.venues.create'], false, true];
        yield 'category creator' => ['categories', ['jem.categories.access', 'core.create'], false, true];
        yield 'attachment editor' => ['attachments', ['jem.events.access', 'jem.events.edit'], false, true];
        yield 'attachment creator alone cannot edit parents' => ['attachments', ['jem.events.access', 'jem.events.create'], false, false];
        yield 'unsupported kind even for administrator' => ['invalid', ['core.admin'], false, false];
    }

    #[DataProvider('authorCases')]
    public function testAuthorEligibility(string $type, array $grants, bool $block, bool $expected): void
    {
        $user = new class ($grants, $block) {
            public int $id = 42;
            public bool $guest = false;
            public function __construct(private array $grants, public bool $block) {}
            public function authorise($action, $asset): bool
            {
                TestCase::assertSame('com_jem', $asset);
                return in_array($action, $this->grants, true);
            }
        };
        self::assertSame($expected, JemImportAuthorHelper::isEligible($user, $type));
        $user->guest = true;
        self::assertFalse(JemImportAuthorHelper::isEligible($user, $type));
    }

    public static function dates(): iterable
    {
        yield 'original valid timestamp' => ['2010-04-05 06:07:08', '2010-04-05 06:07:08'];
        yield 'date only' => ['2010-04-05', '2010-04-05 00:00:00'];
        yield 'missing' => ['', '2026-10-30 12:00:00'];
        yield 'zero' => ['0000-00-00 00:00:00', '2026-10-30 12:00:00'];
        yield 'invalid day' => ['2026-02-30 12:00:00', '2026-10-30 12:00:00'];
        yield 'invalid bytes' => ["2026-01-01\0", '2026-10-30 12:00:00'];
    }

    #[DataProvider('dates')]
    public function testNewRecordsIgnoreSourceUserIdsAndModificationHistory(string $date, string $expected): void
    {
        $result = JemImportAuthorHelper::normalise(
            ['created' => $date, 'created_by' => 999, 'modified_by' => 888, 'modified' => '2000-01-01', 'created_by_alias' => 'Source user'],
            'events', null, 42, 99, '2026-10-30 12:00:00'
        );
        self::assertSame($expected, $result['created']);
        self::assertSame(42, $result['created_by']);
        self::assertNull($result['modified']);
        self::assertSame(0, $result['modified_by']);
        self::assertSame('', $result['created_by_alias']);
    }

    public function testUpdateKeepsLocalCreationAndRecordsActualImporter(): void
    {
        $stored = (object) ['created_user_id' => 21, 'created_time' => '2011-01-02 03:04:05'];
        $result = JemImportAuthorHelper::normalise(
            ['created_user_id' => 999, 'created_time' => '2020-01-01', 'modified_user_id' => 888],
            'categories', $stored, 42, 99, '2026-10-30 12:00:00'
        );
        self::assertSame(21, $result['created_user_id']);
        self::assertSame($stored->created_time, $result['created_time']);
        self::assertSame(99, $result['modified_user_id']);
        self::assertSame('2026-10-30 12:00:00', $result['modified_time']);
    }

    public function testEveryOutcomeSurvivesAndRollbackCannotLookLikeSuccess(): void
    {
        $journal = new JemImportJournal();
        $journal->begin('events', 2, 71);
        $journal->record('added', 901);
        $journal->begin('events', 3, 72);
        $journal->record('updated', 72);
        for ($line = 4; $line <= 12; $line++) {
            $journal->begin('events', $line, 0);
            $journal->record('error', 0, 'Validation failed');
        }
        $entries = $journal->drain();
        self::assertCount(11, $entries);
        self::assertSame([], $journal->drain());
        [$message, $priority] = JemImportJournal::format($entries[0], true);
        self::assertStringContainsString('source_id=71 id=901 outcome=ROLLED_BACK_ADDED', $message);
        self::assertSame('warning', $priority);
        self::assertSame('error', JemImportJournal::format($entries[2], true)[1]);
        self::assertStringContainsString('outcome=ADDED', JemImportJournal::format($entries[0], false)[0]);
    }

    public function testSqlExceptionRetainsItsRowAndDoesNotForgeLogLines(): void
    {
        $journal = new JemImportJournal();
        $journal->begin('types', 9, 12);
        $entries = $journal->drain(new RuntimeException("Unknown column\nFAKE SUCCESS token=secret"));
        [$message, $priority] = JemImportJournal::format($entries[0], false);
        self::assertStringContainsString('entity=types row=9 source_id=12', $message);
        self::assertStringContainsString('Unknown column', $message);
        self::assertStringNotContainsString("\n", $message);
        self::assertStringNotContainsString('token=secret', $message);
        self::assertSame('error', $priority);
    }

    public function testWarningDoesNotLoseTheContextOfTheSavedRow(): void
    {
        $journal = new JemImportJournal();
        $journal->begin('events', 5, 21);
        $journal->record('warning', 0, 'Normalised date');
        $journal->record('added', 81);
        $entries = $journal->drain();
        self::assertCount(2, $entries);
        self::assertSame(5, $entries[1]['line']);
        self::assertSame(21, $entries[1]['source_id']);
        self::assertSame('warning', JemImportJournal::format($entries[0], false)[1]);
    }

    public function testCsvControllerRequiresExplicitSelectionAndLogsAfterCommit(): void
    {
        $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        $csv = substr($controller, strpos($controller, 'private function CsvImport('));
        self::assertLessThan(strpos($csv, "input->files->get("), strpos($csv, '->setImportAuthor('));
        self::assertLessThan(strpos($csv, '$this->writeImportRows($logKey, $model, false)'), strpos($csv, '->transactionCommit()'));
        self::assertStringContainsString('$this->writeImportRows($logKey, $model, true', $csv);
        self::assertStringContainsString('user_id=', $controller);
        self::assertStringContainsString('JemImportJournal::clean((string) $message)', $controller);
        $view = file_get_contents(JEM_TEST_ROOT . '/admin/views/import/tmpl/default.php');
        self::assertStringContainsString('name="import_author_', $view);
        self::assertStringContainsString('htmlspecialchars($author->text', $view);
    }
}
