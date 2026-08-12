<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class TaxRateCatalogueJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 after installing the package to verify the tax catalogue.');
        }
        self::bootJoomlaSite();
    }

    public function testInstalledCatalogueIsCompleteInactiveEditableReferenceData(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $catalogue = json_decode(
            (string) file_get_contents(JPATH_ADMINISTRATOR . '/components/com_jem/data/taxrates/eu-vat-rates.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $expectedRates = array_sum(array_map(
            static fn (array $country): int => count($country['rates']),
            $catalogue['countries']
        ));

        $db->setQuery("SELECT COUNT(*) FROM `#__jem_tax_rates` WHERE LEFT(`code`, 3) = 'EU_'");
        self::assertSame($expectedRates, (int) $db->loadResult());

        $db->setQuery("SELECT COUNT(*) FROM `#__jem_tax_rates` WHERE LEFT(`code`, 3) = 'EU_' AND `published` <> 0");
        self::assertSame(0, (int) $db->loadResult());

        $db->setQuery("SELECT COUNT(*) FROM `#__jem_tax_rates` WHERE LEFT(`code`, 3) = 'EU_' AND (`description` LIKE '%http%' OR `description` LIKE '%TEDB%')");
        self::assertSame(0, (int) $db->loadResult());

        $db->setQuery("SELECT `code`, `rate` FROM `#__jem_tax_rates` WHERE `country_code` = 'ES' ORDER BY `rate` DESC");
        self::assertSame(
            array(
                array('code' => 'EU_ES_STANDARD', 'rate' => '21.00'),
                array('code' => 'EU_ES_REDUCED', 'rate' => '10.00'),
                array('code' => 'EU_ES_SUPER_REDUCED', 'rate' => '4.00'),
            ),
            $db->loadAssocList()
        );

        $db->setQuery("SELECT `currency` FROM `#__jem_countries` WHERE `iso2` = 'ES'");
        self::assertSame('EUR', (string) $db->loadResult());

        $db->setQuery("SELECT `value` FROM `#__jem_config` WHERE `keyname` = 'tax_rates_eu_catalog_version'");
        self::assertSame($catalogue['version'], (string) $db->loadResult());
    }
}
