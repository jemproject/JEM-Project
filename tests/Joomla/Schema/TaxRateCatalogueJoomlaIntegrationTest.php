<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class TaxRateCatalogueJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootJoomlaSite();
        require_once JEM_TEST_ROOT . '/admin/tables/jem_tax_rates.php';
    }

    public function testValidSemanticTaxRateNormalisesWithoutFloatInput(): void
    {
        $table = $this->table();
        $table->code = ' es_iva-21 ';
        $table->name = 'IVA general';
        $table->tax_type = 'STANDARD';
        $table->rate = '21';
        $table->country_code = 'es';
        $table->valid_from = '2026-01-01';
        $table->valid_until = '2026-12-31';

        self::assertTrue($table->check(), (string) $table->getError());
        self::assertSame('ES_IVA-21', $table->code);
        self::assertSame('standard', $table->tax_type);
        self::assertSame('21.00', $table->rate);
        self::assertSame('ES', $table->country_code);
    }

    /** @dataProvider invalidTaxRateProvider */
    public function testInvalidTaxCatalogueRowsAreRejected(array $values): void
    {
        $table = $this->table();
        $table->code = $values['code'] ?? 'TEST';
        $table->name = $values['name'] ?? 'Test';
        $table->tax_type = $values['tax_type'] ?? 'standard';
        $table->rate = $values['rate'] ?? '21.00';
        $table->country_code = $values['country_code'] ?? '';
        $table->valid_from = $values['valid_from'] ?? null;
        $table->valid_until = $values['valid_until'] ?? null;

        self::assertFalse($table->check());
    }

    public static function invalidTaxRateProvider(): iterable
    {
        yield 'invalid code' => array(array('code' => 'IVA 21'));
        yield 'empty name' => array(array('name' => ''));
        yield 'unknown semantic type' => array(array('tax_type' => 'other'));
        yield 'exempt with non-zero rate' => array(array('tax_type' => 'exempt'));
        yield 'too many percentage decimals' => array(array('rate' => '21.001'));
        yield 'invalid country' => array(array('country_code' => 'ESP'));
        yield 'invalid calendar date' => array(array('valid_from' => '2026-02-30'));
        yield 'reversed validity' => array(array('valid_from' => '2026-12-31', 'valid_until' => '2026-01-01'));
    }

    private function table(): jem_tax_rates
    {
        $database = Factory::getContainer()->get(DatabaseDriver::class);

        return new jem_tax_rates($database);
    }
}
