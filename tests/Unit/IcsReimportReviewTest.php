<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Actual parser, recurrence adapter, review and add-only filter; no persistence substitutes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class IcsReimportReviewTest extends TestCase
{
    private object $controller;

    /** @return void Load production code against Joomla libraries without a database. */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to the local Joomla libraries.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        $this->controller = new class extends JemControllerImport {
            public function __construct() {}
            protected function addImportLogEntry($key, $message, $priority = \Joomla\CMS\Log\Log::INFO, $normaliseHeader = true) {}
        };
    }

    /** @param string $properties VEVENT properties. @param string $uid Source identity. @return string ICS event. */
    private function event(string $properties, string $uid = 'partner@example.test'): string
    {
        return "BEGIN:VEVENT\r\nUID:$uid\r\nSUMMARY:Lecture\r\n" . str_replace("\n", "\r\n", $properties) . "\r\nEND:VEVENT\r\n";
    }

    /** @param string $events ICS content. @param array $options Overrides. @return array Actual normalised preview. */
    private function preview(string $events, array $options = []): array
    {
        $file = tempnam(sys_get_temp_dir(), 'ics2540');
        file_put_contents($file, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" . $events . "END:VCALENDAR\r\n");
        try {
            return (new ReflectionMethod($this->controller, 'buildExternalIcsPreview'))->invoke($this->controller,
                ['tmp_name' => $file], $options + ['mode' => 'standard', 'published' => 1, 'publish_up' => '',
                    'locid' => 0, 'type_id' => 0, 'language' => '*', 'catid' => 1, 'category_label' => 'Tests',
                    'type_label' => '', 'venue_label' => '', 'language_label' => 'All', 'review_changes' => true,
                    'ics_source' => 'Partner calendar']);
        } finally {
            unlink($file);
        }
    }

    /** @param array $preview Normalised rows. @return array Previously stored baseline values. */
    private function stored(array $preview): array
    {
        return array_map(static fn (array $row, int $i): array => JemImportIcsReview::incoming($row)
            + ['id' => $i + 100, 'metadata' => $row[7], 'version' => 1, 'modified' => null],
            $preview['records'], array_keys($preview['records']));
    }

    /** @return void Exact duplicates remain unchanged; changed values are explicitly reviewed. */
    public function testChangedTimeAndDefaultImportDoNotOverwriteOrDuplicate(): void
    {
        $first = $this->preview($this->event("DTSTART:20261206T193000Z\nSEQUENCE:1"));
        $index = JemImportIcsReview::index($this->stored($first));
        $same = JemImportIcsReview::preview($first, $index, true);
        self::assertSame('unchanged', $same['records'][0]['_jem_ics_review']['action']);
        self::assertSame(0, $same['valid_count']);
        $revised = $this->preview($this->event("DTSTART:20261206T200000Z\nSEQUENCE:2"));
        $review = JemImportIcsReview::preview($revised, $index, true);
        self::assertTrue($review['records'][0]['_jem_ics_review']['eligible']);
        self::assertSame(['19:30:00', '20:00:00'], $review['records'][0]['_jem_ics_review']['changes']['times']);
        $known = []; $skipped = 0;
        $args = [$review['records'], &$known, &$skipped];
        $filtered = (new ReflectionMethod($this->controller, 'filterImportedIcsRecords'))->invokeArgs($this->controller, $args);
        self::assertSame([], $filtered);
        self::assertSame(1, $skipped);
        self::assertFalse(JemImportIcsReview::preview($revised, $index)['records'][0]['_jem_ics_review']['eligible']);
    }

    /** @return void UID-only cancellation is visible, never created, and matched only to known imports. */
    public function testCancellationWithoutDatesAndUnknownUid(): void
    {
        $first = $this->preview($this->event('DTSTART:20261206T193000Z'));
        $cancel = $this->preview($this->event("STATUS:CANCELLED\nSEQUENCE:2"));
        self::assertSame(0, $cancel['error_count']);
        self::assertSame(0, $cancel['valid_count']);
        $review = JemImportIcsReview::preview($cancel, JemImportIcsReview::index($this->stored($first)), true);
        $change = $review['records'][0]['_jem_ics_review'];
        self::assertSame('cancelled', $change['action']);
        self::assertSame(['event_status' => ['scheduled', 'cancelled']], $change['changes']);
        self::assertTrue($change['eligible']);
        $unknown = JemImportIcsReview::preview($cancel, JemImportIcsReview::index([]), true);
        self::assertSame('ignored', $unknown['records'][0]['_jem_ics_review']['action']);
        self::assertFalse($unknown['records'][0]['_jem_ics_review']['eligible']);
    }

    /** @return iterable Local and provenance conflicts must block updates. */
    public static function conflicts(): iterable
    {
        yield 'local title' => ['title', 'Local description', 'LOCAL'];
        yield 'local time' => ['times', '21:00:00', 'LOCAL'];
        yield 'local status' => ['event_status', 'postponed', 'LOCAL'];
        yield 'native recurrence' => ['recurrence_first_id', 50, 'LOCAL_MANAGEMENT'];
        yield 'price areas' => ['pricing_mode', 'multiple', 'LOCAL_MANAGEMENT'];
        yield 'venue profile' => ['venue_profile_id', 8, 'LOCAL_MANAGEMENT'];
        yield 'per day schedule' => ['attribs', '{"event_schedule":{"version":1,"mode":"per_day","days":[]}}', 'LOCAL_MANAGEMENT'];
        yield 'physical layout' => ['venue_allocation_mode', 'layout', 'LOCAL_MANAGEMENT'];
    }

    /** @param string $field Stored field. @param mixed $value Local edit. @param string $reason Expected conflict. @return void */
    #[DataProvider('conflicts')]
    public function testLocalChangesAreProtected(string $field, $value, string $reason): void
    {
        $first = $this->preview($this->event('DTSTART:20261206T193000Z'));
        $stored = $this->stored($first);
        $stored[0][$field] = $value;
        $revised = $this->preview($this->event('DTSTART:20261206T200000Z'));
        $review = JemImportIcsReview::preview($revised, JemImportIcsReview::index($stored), true)['records'][0]['_jem_ics_review'];
        self::assertFalse($review['eligible']);
        self::assertSame($reason, $review['reason']);
    }

    /** @return void Legacy imports, different sources and older revisions cannot be overwritten. */
    public function testProvenanceAndRevisionGuards(): void
    {
        $first = $this->preview($this->event("DTSTART:20261206T193000Z\nSEQUENCE:2"));
        $stored = $this->stored($first);
        foreach ([['', 3, 'LEGACY'], ['Another calendar', 3, 'SOURCE'], ['Partner calendar', 1, 'STALE']] as [$source, $sequence, $reason]) {
            $revised = $this->preview($this->event("DTSTART:20261206T200000Z\nSEQUENCE:$sequence"), ['ics_source' => $source]);
            $review = JemImportIcsReview::preview($revised, JemImportIcsReview::index($stored), true)['records'][0]['_jem_ics_review'];
            self::assertFalse($review['eligible']);
            self::assertSame($reason, $review['reason']);
        }
        $meta = JemImportIcsReview::metadata($stored[0]['metadata']);
        unset($meta['jem_ics_import']);
        $stored[0]['metadata'] = json_encode($meta);
        $review = JemImportIcsReview::preview($revised, JemImportIcsReview::index($stored), true)['records'][0]['_jem_ics_review'];
        self::assertSame('LEGACY', $review['reason']);
    }

    /** @return void A moved recurrence master must not create replacement slots with new IDs. */
    public function testMovedRecurringMasterCannotCreateDuplicates(): void
    {
        $first = $this->preview($this->event("DTSTART:20261206T193000Z\nRRULE:FREQ=WEEKLY;COUNT=3"));
        $revised = $this->preview($this->event("DTSTART:20261206T200000Z\nRRULE:FREQ=WEEKLY;COUNT=3"));
        $review = JemImportIcsReview::preview($revised, JemImportIcsReview::index($this->stored($first)), true);
        self::assertSame(0, $review['valid_count']);
        self::assertCount(3, $review['records']);
        foreach ($review['records'] as $record) {
            self::assertSame('SERIES', $record['_jem_ics_review']['reason']);
            self::assertFalse($record['_jem_ics_review']['eligible']);
        }
    }

    /** @return void Detached first-slot cancellation and whole-series cancellations retain exact identities. */
    public function testSeriesCancellationsRespectOriginalSlotsAndWindow(): void
    {
        $first = $this->preview($this->event("DTSTART:20261206T193000Z\nRRULE:FREQ=WEEKLY;COUNT=3"));
        $index = JemImportIcsReview::index($this->stored($first));
        $detached = $this->preview($this->event("RECURRENCE-ID:20261206T193000Z\nSTATUS:CANCELLED\nSEQUENCE:2"));
        $review = JemImportIcsReview::preview($detached, $index, true);
        self::assertCount(1, $review['records']);
        self::assertSame(100, $review['records'][0]['_jem_ics_review']['id']);
        self::assertTrue($review['records'][0]['_jem_ics_review']['eligible']);
        $whole = $this->preview($this->event("STATUS:CANCELLED\nSEQUENCE:2"));
        $review = JemImportIcsReview::preview($whole, $index, true);
        self::assertCount(3, $review['records']);
        self::assertSame([100, 101, 102], array_column(array_column($review['records'], '_jem_ics_review'), 'id'));
        $whole['ics_from'] = '2026-12-13'; $whole['ics_to'] = '2026-12-13';
        self::assertCount(1, JemImportIcsReview::preview($whole, $index, true)['records']);
    }

    /** @return void Duplicate stored identities are conflicts, including cancellation messages. */
    public function testAmbiguousStoredIdentityIsNeverSelectedAutomatically(): void
    {
        $first = $this->preview($this->event('DTSTART:20261206T193000Z'));
        $stored = $this->stored($first); $stored[] = array_replace($stored[0], ['id' => 101]);
        foreach (['DTSTART:20261206T200000Z', 'STATUS:CANCELLED'] as $properties) {
            $review = JemImportIcsReview::preview($this->preview($this->event($properties)), JemImportIcsReview::index($stored), true);
            self::assertSame('DUPLICATE', $review['records'][0]['_jem_ics_review']['reason']);
            self::assertFalse($review['records'][0]['_jem_ics_review']['eligible']);
        }
    }

    /** @return void A series imported after preview cannot make changed slots look new at commit. */
    public function testNewOnlyCommitRechecksCurrentImportedSeries(): void
    {
        $first = $this->preview($this->event("DTSTART:20261206T193000Z\nRRULE:FREQ=WEEKLY;COUNT=2"));
        $later = $this->preview($this->event("DTSTART:20261206T200000Z\nRRULE:FREQ=WEEKLY;COUNT=2"));
        $earlyPreview = JemImportIcsReview::preview($later, JemImportIcsReview::index([]));
        self::assertSame(2, $earlyPreview['valid_count']);
        $known = []; $skipped = 0;
        $args = [$earlyPreview['records'], &$known, &$skipped, JemImportIcsReview::index($this->stored($first))];
        $filtered = (new ReflectionMethod($this->controller, 'filterImportedIcsRecords'))->invokeArgs($this->controller, $args);
        self::assertSame([], $filtered);
        self::assertSame(2, $skipped);
    }

    /** @return void A moved detached instance matches its original date, even for the first series occurrence. */
    public function testDetachedMovedInstancePreservesItsOriginalTarget(): void
    {
        $first = $this->preview($this->event("DTSTART:20261206T193000Z\nRRULE:FREQ=WEEKLY;COUNT=3"));
        $moved = $this->preview($this->event("DTSTART:20261207T203000Z\nRECURRENCE-ID:20261206T193000Z\nSEQUENCE:2"));
        $review = JemImportIcsReview::preview($moved, JemImportIcsReview::index($this->stored($first)), true);
        self::assertSame(100, $review['records'][0]['_jem_ics_review']['id']);
        self::assertTrue($review['records'][0]['_jem_ics_review']['eligible']);
        self::assertSame(['2026-12-06', '2026-12-07'], $review['records'][0]['_jem_ics_review']['changes']['dates']);
    }

    /** @return void Invalid input remains visible beside a valid cancellation. */
    public function testValidationFailuresAreNotHiddenByReview(): void
    {
        $invalid = str_replace('SUMMARY:Lecture', 'SUMMARY:', $this->event('DTSTART:20261206T193000Z', 'invalid@example.test'));
        $preview = $this->preview($invalid . $this->event('STATUS:CANCELLED'));
        $review = JemImportIcsReview::preview($preview, JemImportIcsReview::index([]), true);
        self::assertSame(1, $review['error_count']);
        self::assertCount(2, $review['rows']);
        self::assertCount(1, $review['records']);
    }

    /** @return void Review permissions hide the old private content as well as blocking the update. */
    public function testDeniedReviewDoesNotDiscloseStoredContent(): void
    {
        $first = $this->preview($this->event('DTSTART:20261206T193000Z'));
        $stored = $this->stored($first);
        $stored[0]['title'] = 'PRIVATE_LOCAL_CONTENT';
        $next = $this->preview($this->event('DTSTART:20261206T200000Z'));
        $review = JemImportIcsReview::preview($next, JemImportIcsReview::index($stored), true, static fn (): bool => false);
        self::assertSame('PERMISSION', $review['records'][0]['_jem_ics_review']['reason']);
        self::assertFalse($review['records'][0]['_jem_ics_review']['eligible']);
        self::assertStringNotContainsString('PRIVATE_LOCAL_CONTENT', json_encode($review['rows']));
    }

    /** @return void Long descriptions must not overflow the metadata TEXT column. */
    public function testBaselineDoesNotDuplicateDescriptionContent(): void
    {
        $first = $this->preview($this->event("DTSTART:20261206T193000Z\nDESCRIPTION:" . str_repeat('Long description ', 4000)));
        self::assertLessThan(1500, strlen($first['records'][0][7]));
        self::assertSame(64, strlen(JemImportIcsReview::metadata($first['records'][0][7])['jem_ics_import']['baseline']));
    }
}
