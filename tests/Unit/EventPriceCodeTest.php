<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Admission-code validation and persistence using real services and isolated SQL. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventPriceCodeTest extends TestCase
{
    private object $db;

    /** @return void Load read-only Joomla libraries and create disposable price fixtures. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('sqlite3')) {
            self::markTestSkipped('Requires Joomla libraries and SQLite.');
        }
        require JEM_TEST_ROOT . '/tests/Support/invitation_publication_environment.php';
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->db->setQuery('CREATE TABLE #__jem_capacity_pools (id INTEGER,event_id INTEGER,ordering INTEGER)')->execute();
        $this->db->setQuery('CREATE TABLE #__jem_tax_rates (
            id INTEGER,country_code TEXT,published INTEGER,valid_from TEXT,valid_until TEXT
        )')->execute();
        $this->db->setQuery("INSERT INTO #__jem_tax_rates VALUES (1,'ES',1,NULL,NULL)")->execute();
        $this->db->setQuery('CREATE TABLE #__jem_event_prices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER,capacity_pool_id INTEGER,
            code TEXT,name TEXT,description TEXT,amount TEXT,tax_rate_id INTEGER,quota INTEGER,
            min_quantity INTEGER,max_quantity INTEGER,available_from TEXT,available_until TEXT,
            min_age INTEGER,max_age INTEGER,access_level_id INTEGER,user_group_id INTEGER,
            verification_mode TEXT,published INTEGER,ordering INTEGER,created TEXT,created_by INTEGER,
            modified TEXT,modified_by INTEGER,UNIQUE(event_id,code)
        )')->execute();
        $this->db->setQuery('CREATE TABLE #__jem_register_items (id INTEGER,event_price_id INTEGER)')->execute();
    }

    /** @param array $changes Overrides. @return array Complete new-price input. */
    private function price(array $changes = []): array
    {
        return array_replace([
            'id' => 0, 'name' => 'Child Ticket', 'code' => '', 'amount' => '7.50',
            'tax_rate_id' => 1, 'published' => 1,
        ], $changes);
    }

    /** @param array $rows Submitted prices. @param int $eventId Owner. @return array Validated prices. */
    private function normalise(array $rows, int $eventId = 7): array
    {
        return (new ReflectionMethod(JemEventPricingCapacityService::class, 'normalisePriceRows'))
            ->invoke(null, $rows, $eventId, 'EUR', 'ES', '2026-11-13');
    }

    /** @param array $rows Validated prices. @return void Exercise the production write/removal order. */
    private function save(array $rows): void
    {
        (new ReflectionMethod(JemEventPricingCapacityService::class, 'savePriceRows'))->invoke(null, 7, $rows);
    }

    /** @return iterable Inputs submitted by new forms, legacy forms and advanced-code editing. */
    public static function newCodes(): iterable
    {
        yield 'empty' => ['Child Ticket', '', 'child-ticket'];
        yield 'whitespace' => ['Child Ticket', '  ', 'child-ticket'];
        yield 'null' => ['Child Ticket', null, 'child-ticket'];
        yield 'non-Latin name' => ['儿童', '', 'admission'];
        yield 'numeric name' => ['0', '', '0'];
        yield 'explicit' => ['Child Ticket', ' CHILD_ticket! ', 'child_ticket'];
    }

    /** @param string $name Price name. @param ?string $code Input. @param string $expected Code. @return void */
    #[DataProvider('newCodes')]
    public function testNewCode(string $name, ?string $code, string $expected): void
    {
        $rows = $this->normalise([$this->price(['name' => $name, 'code' => $code])]);
        self::assertSame($expected, $rows[0]['code']);
        self::assertSame('7.50', $rows[0]['amount']);
        self::assertSame(1, $rows[0]['tax_rate_id']);
    }

    /** @return void Forms omitting the code retain the name-derived fallback. */
    public function testMissingCode(): void
    {
        $row = $this->price();
        unset($row['code']);
        self::assertSame('child-ticket', $this->normalise([$row])[0]['code']);
    }

    /** @return void Later explicit codes take precedence over automatically generated codes. */
    public function testBatchReservesExplicitCodesBeforeGenerating(): void
    {
        $auto = $this->price(['name' => 'Admission']);
        $rows = $this->normalise([
            $auto, $this->price(['code' => 'ADMISSION!']), $auto, $this->price(['code' => 'admission-2']),
        ]);
        self::assertSame(['admission-3', 'admission', 'admission-4', 'admission-2'], array_column($rows, 'code'));
    }

    /** @return void Suffixes stay inside the storage limit and survive server normalisation. */
    public function testMaximumLengthGeneratedCodes(): void
    {
        $row = $this->price(['name' => str_repeat('a', 64)]);
        $codes = array_column($this->normalise([$row, $row, $row]), 'code');
        self::assertSame([str_repeat('a', 64), str_repeat('a', 62) . '-2', str_repeat('a', 62) . '-3'], $codes);
        self::assertSame([64, 64, 64], array_map('strlen', $codes));
    }

    /** @return void Updating a price never changes its stored code, ID or booking reference. */
    public function testExistingCodeAndReferencesRemainStable(): void
    {
        $this->save($this->normalise([$this->price(['code' => 'adult'])]));
        $this->db->setQuery('INSERT INTO #__jem_register_items VALUES (9,1)')->execute();
        $rows = $this->normalise([$this->price(['id' => 1, 'code' => 'changed', 'name' => 'Adult renamed'])]);
        $this->save($rows);
        $stored = $this->db->setQuery('SELECT * FROM #__jem_event_prices WHERE id=1')->loadAssoc();
        self::assertSame('adult', $stored['code']);
        self::assertSame('Adult renamed', $stored['name']);
        self::assertSame(1, (int) $this->db->setQuery('SELECT event_price_id FROM #__jem_register_items WHERE id=9')->loadResult());
    }

    /** @return iterable Both unreferenced and historically booked removed tariffs. */
    public static function removedPrices(): iterable
    {
        yield 'unbooked' => [false];
        yield 'booked' => [true];
    }

    /** @param bool $booked Whether the removed price has a booking. @return void */
    #[DataProvider('removedPrices')]
    public function testRemovalAndAdditionDoNotReuseStoredCode(bool $booked): void
    {
        $this->save($this->normalise([$this->price(['code' => 'admission'])]));
        if ($booked) {
            $this->db->setQuery('INSERT INTO #__jem_register_items VALUES (9,1)')->execute();
        }
        $rows = $this->normalise([$this->price(['name' => 'Admission'])]);
        self::assertSame('admission-2', $rows[0]['code']);
        $this->save($rows);
        $new = $this->db->setQuery("SELECT * FROM #__jem_event_prices WHERE code='admission-2'")->loadAssoc();
        self::assertGreaterThan(1, (int) $new['id']);
        self::assertSame('7.50', $new['amount']);
        $old = $this->db->setQuery('SELECT * FROM #__jem_event_prices WHERE id=1')->loadAssoc();
        if ($booked) {
            self::assertSame('admission', $old['code']);
            self::assertSame(0, (int) $old['published']);
            self::assertSame(1, (int) $this->db->setQuery('SELECT event_price_id FROM #__jem_register_items WHERE id=9')->loadResult());
        } else {
            self::assertNull($old);
        }
    }

    /** @return void Unpublished prices still reserve codes; another event can use the same code. */
    public function testStoredCodesAreScopedToTheirEvent(): void
    {
        $this->save($this->normalise([$this->price(['code' => 'admission', 'published' => 0])]));
        $new = $this->price(['name' => 'Admission']);
        self::assertSame('admission-2', $this->normalise([$new])[0]['code']);
        self::assertSame('admission', $this->normalise([$new], 8)[0]['code']);
    }

    /** @return iterable Explicit invalid/colliding codes must still be rejected, not silently renamed. */
    public static function invalidCodes(): iterable
    {
        yield 'invalid characters' => [['!!!']];
        yield 'stored code' => [['admission']];
        yield 'normalised stored code' => [[' ADMISSION! ']];
        yield 'duplicate normalisation' => [['ADULT!', 'adult']];
        yield 'duplicate truncation' => [[str_repeat('a', 64), str_repeat('a', 64) . '-copy']];
    }

    /** @param array $codes Explicit input codes. @return void */
    #[DataProvider('invalidCodes')]
    public function testExplicitInvalidCodesAreRejected(array $codes): void
    {
        $this->save($this->normalise([$this->price(['code' => 'admission'])]));
        $rows = array_map(fn (string $code): array => $this->price(['code' => $code]), $codes);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COM_JEM_EVENT_PRICING_ERROR_PRICE_CODE');
        $this->normalise($rows);
    }

    /** @return void Adding the code fallback does not relax row ownership validation. */
    public function testForeignPriceIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COM_JEM_EVENT_PRICING_ERROR_PRICE_OWNERSHIP');
        $this->normalise([$this->price(['id' => 99])]);
    }
}
