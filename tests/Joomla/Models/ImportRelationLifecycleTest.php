<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** CSV relation validation, controller summaries and transactions on disposable SQL tables. */
final class ImportRelationLifecycleTest extends TestCase
{
    private static ?array $report = null;

    /**
     * Execute the checkout model/controller probe once for this Joomla environment.
     *
     * @return array Captured database state, messages, warnings and result counters.
     */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_IMPORT_RELATIONS') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_IMPORT_RELATIONS=1; requires CREATE DATABASE.');
        }
        $command = [PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/import_relations_probe.php', $root, JEM_TEST_ROOT];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        self::assertSame('', $error);
        return self::$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{string,bool}> Successful and rejected import scenarios. */
    public static function scenarios(): iterable
    {
        foreach (['new','update','unchanged','two_events','replace_category_set','duplicate_csv_pair',
            'leading_zero_pair','trimmed_pair','missing_ordering','reordered_headers','empty','query_chunking',
            'event_new_id','event_preserved_id','event_existing_id'] as $name) {
            yield $name => [$name, false];
        }
        foreach (['missing_category_header','missing_event_header','missing_event_record','missing_category_record',
            'replace_with_missing_category','bounded_errors','late_sql_error','query_chunking_missing',
            'event_missing_category','event_invalid_category','event_html_category'] as $name) {
            yield $name => [$name, true];
        }
        foreach (['numeric_prefix','decimal','exponent','negative','zero','empty','plus','overflow','huge','html','nul','text'] as $suffix) {
            foreach (['catid','itemid'] as $field) {
                $name = 'invalid_' . $field . '_' . $suffix;
                yield $name => [$name, true];
            }
        }
    }

    /**
     * Verify all warnings, error propagation and persistence outcomes against the chosen policy.
     *
     * @param string $name Probe scenario name.
     * @param bool $rejected Whether the complete file must be rejected.
     * @return void
     */
    #[DataProvider('scenarios')]
    public function testImportOutcome(string $name, bool $rejected): void
    {
        $case = self::report()['cases'][$name];
        self::assertSame([], $case['warnings']);
        self::assertSame('', $case['output']);
        self::assertSame($rejected ? 'error' : 'message', $case['redirects'][0][2], json_encode($case['logs']));
        if ($rejected) {
            self::assertSame($case['before'], $case['after'], 'Original category assignments must survive rejection.');
            self::assertSame($case['events_before'], $case['events_after'], 'Rejected event imports must roll back too.');
            self::assertTrue($case['journal'][0][0]);
            self::assertStringNotContainsString('completed.', json_encode($case['logs']));
        } elseif ($name !== 'empty') {
            self::assertCompleteResult($case['result']);
            self::assertSame(0, $case['result']['error']);
        }
    }

    /** @param array $result Import result. @return void */
    private static function assertCompleteResult(array $result): void
    {
        self::assertSame(['added','updated','ignored','ignoredids','duplicated','duplicatedids',
            'replaced','replacedids','error','errorids','error_details'], array_keys($result));
        foreach (['added','updated','ignored','duplicated','replaced','error'] as $key) {
            self::assertIsInt($result[$key]);
        }
        self::assertIsArray($result['error_details']);
    }

    /** @return void */
    public function testCountsAndReplaceSemanticsRemainConsistent(): void
    {
        $cases = self::report()['cases'];
        self::assertSame(1, $cases['new']['result']['added']);
        self::assertSame(1, $cases['update']['result']['updated']);
        self::assertSame($cases['update']['before'][0]['id'], $cases['update']['after'][0]['id']);
        self::assertSame(7, (int) $cases['update']['after'][0]['ordering']);
        self::assertSame(2, $cases['two_events']['result']['added']);
        self::assertSame(1, $cases['replace_category_set']['result']['added']);
        self::assertSame(1, $cases['replace_category_set']['result']['updated']);
        self::assertSame([2,5,3], array_map('intval', array_column($cases['replace_category_set']['after'], 'catid')));
        foreach (['duplicate_csv_pair','leading_zero_pair'] as $name) {
            self::assertCount(1, $cases[$name]['after']);
            self::assertSame(1, $cases[$name]['result']['added']);
            self::assertSame(9, (int) $cases[$name]['after'][0]['ordering']);
        }
        self::assertSame($cases['empty']['before'], $cases['empty']['after']);
    }

    /** @return void */
    public function testValidationMessagesIdentifyTheSourceLineAndField(): void
    {
        $cases = self::report()['cases'];
        foreach (['catid','itemid'] as $field) {
            $message = $cases['invalid_' . $field . '_numeric_prefix']['redirects'][0][1];
            self::assertStringContainsString('Row 3:', $message);
            self::assertStringContainsString($field, $message);
        }
        foreach (['missing_category_header','missing_event_header'] as $name) {
            self::assertStringContainsString('Row 1:', $cases[$name]['redirects'][0][1]);
            self::assertStringContainsString('catid and itemid', $cases[$name]['redirects'][0][1]);
        }
        foreach (['missing_category_record'=>'catid','missing_event_record'=>'itemid'] as $name => $field) {
            self::assertStringContainsString('Row 3:', $cases[$name]['redirects'][0][1]);
            self::assertStringContainsString($field, $cases[$name]['redirects'][0][1]);
            self::assertStringContainsString('999999', $cases[$name]['redirects'][0][1]);
        }
        $bounded = $cases['bounded_errors']['redirects'][0][1];
        self::assertStringContainsString('4 records failed', $bounded);
        self::assertSame(3, substr_count($bounded, 'Row '));
        self::assertSame('2xyz', self::report()['category_name'], 'Category names are not ID fields.');
    }

    /** @return void */
    public function testNewAndPreservedEventIdsAreUsedForRelations(): void
    {
        $cases = self::report()['cases'];
        $created = array_values(array_filter($cases['event_new_id']['events_after'], static fn(array $row): bool => $row['title'] === 'New generated event'));
        self::assertCount(1, $created);
        $newId = (int) $created[0]['id'];
        self::assertGreaterThan(102, $newId);
        $relations = array_column($cases['event_new_id']['after'], null, 'itemid');
        self::assertSame(2, (int) $relations[$newId]['catid']);
        $preserved = array_column($cases['event_preserved_id']['after'], null, 'itemid');
        self::assertSame(2, (int) $preserved[808]['catid']);
        self::assertSame(1, $cases['event_existing_id']['result']['updated']);
        self::assertSame(3, (int) $cases['event_existing_id']['after'][0]['catid']);
    }

    /** @return void */
    public function testEmptyResultsAndNonCsvIdsHaveConsistentTypes(): void
    {
        $report = self::report();
        self::assertCompleteResult($report['empty_relations']);
        self::assertCompleteResult($report['empty_types']);
        self::assertSame($report['empty_relations'], $report['empty_types']);
        self::assertSame([true,true,true,true,true], $report['non_csv_invalid_rejected']);
    }

    /** @return void */
    public function testSqlErrorsAndLargeReferenceSetsAreHandled(): void
    {
        $cases = self::report()['cases'];
        self::assertStringContainsString('2474 injected SQL failure', json_encode($cases['late_sql_error']['logs']));
        self::assertSame(501, $cases['query_chunking']['result']['added']);
        self::assertCount(501, $cases['query_chunking']['after']);
        self::assertStringContainsString('Row 504:', $cases['query_chunking_missing']['redirects'][0][1]);
        self::assertStringContainsString('1501', $cases['query_chunking_missing']['redirects'][0][1]);
    }
}
