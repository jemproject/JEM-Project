<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationFoundationSchemaTest extends TestCase
{
    public function testInstallAndUpgradeSchemasContainRegistrationFoundation(): void
    {
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');

        foreach (array('`reference`', '`created`', '`modified`', '`revision`') as $column) {
            self::assertStringContainsString($column, $install);
            self::assertStringContainsString($column, $update);
        }

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_registration_history`', $install);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_registration_history`', $update);
        self::assertStringContainsString('idx_history_registration_revision', $install);
        self::assertStringContainsString('idx_history_operation_registration', $install);
        self::assertStringContainsString("('registration_schema_ready', '1')", $install);
        self::assertStringContainsString("('registration_schema_ready', '0')", $update);
    }

    public function testInstallerBackfillIsIdempotentAndPreservesLegacyRegistrationColumns(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');

        self::assertStringContainsString('repair510RegistrationSchema()', $script);
        self::assertStringContainsString('backfillRegistrationRow', $script);
        self::assertStringContainsString('registrationLegacyFingerprint', $script);
        self::assertStringContainsString('Registration migration changed legacy registration data.', $script);
        self::assertStringContainsString('registration_schema_ready', $script);
        self::assertStringNotContainsString('DROP TABLE', $update);
        self::assertStringNotContainsString('DELETE FROM `#__jem_register`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `event`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `uid`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `places`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `uregdate`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `uip`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `status`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `waiting`', $update);
        self::assertStringNotContainsString('UPDATE `#__jem_register` SET `comment`', $update);
    }
}
