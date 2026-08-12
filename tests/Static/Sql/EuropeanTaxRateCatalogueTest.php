<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EuropeanTaxRateCatalogueTest extends TestCase
{
    public function testBundledCatalogueContainsValidEditableEuReferenceRates(): void
    {
        $path = JEM_TEST_ROOT . '/admin/data/taxrates/eu-vat-rates.json';
        self::assertFileExists($path);

        $catalogue = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('eu-vat-rates', $catalogue['catalog']);
        self::assertFalse($catalogue['default_published']);
        self::assertCount(27, $catalogue['countries']);
        self::assertStringContainsString('taxation_customs/tedb/', $catalogue['source_url']);
        self::assertStringContainsString('iso-currrency/lists/list-one.xml', $catalogue['currency_source_url']);

        $countries = array();
        $codes = array();
        foreach ($catalogue['countries'] as $country) {
            self::assertMatchesRegularExpression('/^[A-Z]{2}$/D', $country['code']);
            self::assertMatchesRegularExpression('/^[A-Z]{3}$/D', $country['currency']);
            self::assertArrayNotHasKey($country['code'], $countries);
            $countries[$country['code']] = $country;

            foreach ($country['rates'] as $rate) {
                self::assertCount(3, $rate);
                self::assertContains($rate[1], array('standard', 'reduced', 'zero', 'exempt', 'outside_scope'));
                self::assertMatchesRegularExpression('/^\d{1,3}\.\d{2}$/D', $rate[2]);
                $code = 'EU_' . $country['code'] . '_' . strtoupper($rate[0]);
                self::assertNotContains($code, $codes);
                $codes[] = $code;
            }
        }

        self::assertSame('EUR', $countries['ES']['currency']);
        self::assertSame(
            array(
                array('standard', 'standard', '21.00'),
                array('reduced', 'reduced', '10.00'),
                array('super_reduced', 'reduced', '4.00'),
            ),
            $countries['ES']['rates']
        );
    }

    public function testInstallerDoesNotStoreCatalogueSourceUrlsInTaxRows(): void
    {
        $installer = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString('installEuropeanTaxRateCatalogue()', $installer);
        self::assertStringContainsString("'currency' =>", $installer);
        self::assertMatchesRegularExpression('/\$db->quote\(\$countryCode\),\s+\'0\',/', $installer);
        self::assertStringNotContainsString('$sourceUrl =', $installer);
        self::assertStringNotContainsString('$sourceName =', $installer);
        self::assertStringNotContainsString("'country_code', 'description'", $installer);
    }
}
