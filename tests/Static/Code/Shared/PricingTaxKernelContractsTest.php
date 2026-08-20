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

    public function testOneGlobalCurrencyInitialisesEventsAndUsesUniqueSymbolLabels(): void
    {
        $settingsForm = $this->read('/admin/models/forms/settings.xml');
        $eventForm = $this->read('/admin/models/forms/event.xml');
        $settingsModel = $this->read('/admin/models/settings.php');
        $currencyField = $this->read('/admin/models/fields/currencyoptions.php');
        $pricingService = $this->read('/admin/classes/eventpricingcapacity.class.php');
        $pricingView = $this->read('/admin/views/event/tmpl/edit_pricing.php');
        $money = $this->read('/site/classes/money.class.php');
        $install = $this->read('/admin/sql/install.mysql.utf8.sql');
        $update = $this->read('/admin/sql/updates/mysql/5.1.0.sql');

        self::assertMatchesRegularExpression(
            '/name="defaultCurrency"[\s\S]+default="EUR"[\s\S]+required="true"/',
            $settingsForm
        );
        self::assertMatchesRegularExpression('/name="currency" type="hidden"/', $eventForm);
        self::assertStringContainsString('hasStoredEconomicCurrencyData', $settingsModel);
        self::assertStringContainsString("->select('DISTINCT '", $currencyField);
        self::assertStringContainsString('$currencies[$currency] = true', $currencyField);
        self::assertStringContainsString('JemMoney::currencyLabel', $currencyField);
        self::assertStringContainsString('storedOrDefaultCurrency', $pricingService);
        self::assertStringContainsString("classes/config.class.php", $pricingService);
        self::assertStringContainsString('JemConfig::getInstance()', $pricingService);
        self::assertStringNotContainsString('JemHelper::config()', $pricingService);
        self::assertStringNotContainsString('payload.suggested_currency', $pricingView);
        self::assertStringContainsString('Intl.NumberFormat', $pricingView);
        self::assertStringContainsString('formatDecimal', $money);
        self::assertStringContainsString("('defaultCurrency', 'EUR')", $install);
        self::assertStringContainsString("VALUES ('defaultCurrency', 'EUR')", $update);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $path);
        self::assertNotFalse($contents, $path . ' must be readable.');

        return $contents;
    }
}
