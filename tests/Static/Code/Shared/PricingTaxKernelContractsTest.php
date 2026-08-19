<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PricingTaxKernelContractsTest extends TestCase
{
    public function testFactoryLoadsExactMoneyAndTaxKernel(): void
    {
        $factory = $this->read('/site/factory.php');

        foreach (array('money', 'taxpolicy', 'taxcalculation', 'taxcalculator') as $class) {
            self::assertStringContainsString('/classes/' . $class . '.class.php', $factory);
        }
    }

    public function testTaxCatalogueHasCompleteAdministratorCrud(): void
    {
        foreach (array(
            '/admin/controllers/taxrate.php',
            '/admin/controllers/taxrates.php',
            '/admin/models/taxrate.php',
            '/admin/models/taxrates.php',
            '/admin/models/forms/taxrate.xml',
            '/admin/tables/jem_tax_rates.php',
            '/admin/views/taxrate/view.html.php',
            '/admin/views/taxrate/tmpl/edit.php',
            '/admin/views/taxrates/view.html.php',
            '/admin/views/taxrates/tmpl/default.php',
            '/media/images/icon-48-taxrates.svg',
        ) as $path) {
            self::assertFileExists(JEM_TEST_ROOT . $path, $path);
        }

        self::assertStringNotContainsString('view=taxrates', $this->read('/jem.xml'));
        $helper = $this->read('/admin/helpers/helper.php');
        self::assertStringContainsString('view=taxrates', $helper);
        self::assertStringContainsString('FEATURE_PRICING', $helper);
        $dashboard = $this->read('/admin/views/main/tmpl/default.php');
        self::assertStringContainsString('COM_JEM_MAIN_GROUP_SYSTEM', $dashboard);
        self::assertStringContainsString('if ($canConfigure)', $dashboard);
        self::assertStringContainsString('FEATURE_PRICING', $dashboard);
        self::assertStringContainsString('icon-48-taxrates.svg', $dashboard);
        self::assertStringContainsString("canManage('core.options')", $this->read('/admin/views/taxrates/view.html.php'));
        self::assertStringContainsString('type="countryoptions"', $this->read('/admin/models/forms/taxrate.xml'));
        self::assertStringContainsString('defaultCountry', $this->read('/admin/models/taxrate.php'));
        self::assertStringContainsString('filter.country_code', $this->read('/admin/models/taxrates.php'));
        self::assertStringContainsString('COM_JEM_TAX_RATE_CATALOG_SOURCE', $this->read('/admin/views/taxrates/tmpl/default.php'));
        self::assertStringContainsString('jem-taxrate-form', $this->read('/admin/views/taxrate/tmpl/edit.php'));
        self::assertStringContainsString('.jem-taxrate-form .jem-taxrate-fields > .control-group', $this->read('/media/css/backend.css'));
    }

    public function testTaxCatalogueValidatesSemanticTypesRatesAndValidity(): void
    {
        $table = $this->read('/admin/tables/jem_tax_rates.php');

        foreach (array('standard', 'reduced', 'zero', 'exempt', 'outside_scope') as $type) {
            self::assertStringContainsString("'" . $type . "'", $table);
        }
        self::assertStringContainsString('$basisPoints > 10000', $table);
        self::assertStringContainsString('$this->valid_until < $this->valid_from', $table);
        self::assertStringNotContainsString('(float)', $this->read('/site/classes/taxcalculator.class.php'));
        self::assertStringNotContainsString('(float)', $this->read('/admin/views/taxrates/tmpl/default.php'));
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $path);
        self::assertNotFalse($contents, $path . ' must be readable.');

        return $contents;
    }
}
