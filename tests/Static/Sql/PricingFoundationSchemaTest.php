<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PricingFoundationSchemaTest extends TestCase
{
    private const TABLES = array(
        '#__jem_tax_rates',
        '#__jem_capacity_pools',
        '#__jem_event_prices',
        '#__jem_register_items',
    );

    public function testFreshInstallAndUpgradeContainTheAdditivePricingSchema(): void
    {
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');

        foreach (self::TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . $table . '`', $install);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . $table . '`', $update);
        }

        foreach (array(
            '`pricing_mode`',
            '`pricing_revision`',
            '`currency`',
            '`default_tax_rate_id`',
            '`prices_include_tax`',
            '`management_fee_mode`',
            '`management_fee_value`',
            '`management_fee_basis`',
            '`management_fee_tax_rate_id`',
            '`management_fee_refundable`',
        ) as $column) {
            self::assertStringContainsString($column, $install);
            self::assertStringContainsString($column, $update);
        }

        foreach (array(
            '`subtotal_net`',
            '`discount_total`',
            '`tax_total`',
            '`management_fee_net`',
            '`management_fee_tax`',
            '`management_fee_gross`',
            '`grand_total`',
            '`payment_state`',
            '`price_locked_at`',
            '`external_payment_reference`',
        ) as $column) {
            self::assertStringContainsString($column, $install);
            self::assertStringContainsString($column, $update);
        }

        self::assertStringContainsString('idx_register_item_revision_line', $install);
        self::assertStringContainsString('idx_capacity_pool_event_code', $install);
        self::assertStringContainsString('idx_event_price_event_code', $install);
        self::assertStringContainsString('idx_tax_rate_code', $install);
        self::assertStringContainsString('idx_tax_rate_country', $install);
        self::assertStringContainsString('idx_tax_rate_country', $update);
        self::assertStringContainsString("('pricing_schema_ready', '1')", $install);
        self::assertStringContainsString("('pricing_schema_ready', '0')", $update);
        self::assertMatchesRegularExpression('/CREATE TABLE IF NOT EXISTS `#__jem_countries`[\s\S]+`currency` char\(3\)/i', $install);
        self::assertStringContainsString('ADD COLUMN `currency` CHAR(3)', $update);
    }

    public function testLegacyDefaultsDoNotEnablePricingOrInventCommercialValues(): void
    {
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');

        foreach (array($install, $update) as $sql) {
            self::assertMatchesRegularExpression(
                '/`pricing_mode`\s+varchar\(16\).*DEFAULT [\'\"]classic[\'\"]/i',
                $sql
            );
            self::assertMatchesRegularExpression(
                '/`management_fee_value`\s+decimal\(15,2\).*DEFAULT [\'\"]0\.00[\'\"]/i',
                $sql
            );
            self::assertMatchesRegularExpression(
                '/`management_fee_refundable`\s+tinyint\(1\).*DEFAULT [\'\"]0[\'\"]/i',
                $sql
            );
            self::assertStringContainsString('DECIMAL(15,2)', strtoupper($sql));
            self::assertStringContainsString('DECIMAL(7,2)', strtoupper($sql));
        }

        foreach (array(
            'subtotal_net',
            'discount_total',
            'tax_total',
            'management_fee_net',
            'management_fee_tax',
            'management_fee_gross',
            'grand_total',
            'payment_state',
            'price_locked_at',
            'external_payment_reference',
        ) as $column) {
            self::assertMatchesRegularExpression(
                '/ADD COLUMN `' . preg_quote($column, '/') . '`[^;]+NULL DEFAULT NULL/i',
                $update
            );
        }

        self::assertStringNotContainsString('UPDATE `#__jem_events`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register`', $update);
        self::assertStringNotContainsString('INSERT INTO `#__jem_event_prices`', $update);
        self::assertStringNotContainsString('INSERT INTO `#__jem_register_items`', $update);
        self::assertStringNotContainsString('#__jem_discounts', $install);
        self::assertStringNotContainsString('`discount_amount`', $install);
    }

    public function testUpgradeModifyStatementsAreCompatibleWithJoomlaSchemaChecker(): void
    {
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString(
            "MODIFY `min_quantity` INT(10) UNSIGNED NOT NULL DEFAULT '1'",
            $update
        );
        self::assertStringContainsString(
            "MODIFY `access_level_id` INT(10) UNSIGNED NULL DEFAULT '1'",
            $update
        );
        self::assertStringNotContainsString('MODIFY COLUMN', $update);
        self::assertStringNotContainsString('MODIFY COLUMN', $script);
    }

    public function testInstallerRepairIsIdempotentAndFeatureNeutral(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString('repair510PricingSchema()', $script);
        self::assertStringContainsString('setPricingSchemaReady($db, false)', $script);
        self::assertStringContainsString('setPricingSchemaReady($db, true)', $script);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_tax_rates`', $script);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_capacity_pools`', $script);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_event_prices`', $script);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_register_items`', $script);
        self::assertStringContainsString("DEFAULT 'classic'", $script);
        self::assertStringNotContainsString('INSERT INTO `#__jem_event_prices`', $script);
        self::assertStringNotContainsString('INSERT INTO `#__jem_register_items`', $script);

        foreach (self::TABLES as $table) {
            self::assertStringContainsString("'" . $table . "'", $script);
        }
    }
}
