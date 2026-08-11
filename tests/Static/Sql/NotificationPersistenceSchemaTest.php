<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NotificationPersistenceSchemaTest extends TestCase
{
    public function testInstallAndUpgradeSchemasContainImmutableNotificationsAndAttempts(): void
    {
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');

        foreach (array($install, $update) as $sql) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_notifications`', $sql);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_notification_attempts`', $sql);
            self::assertStringContainsString('idx_notification_idempotency', $sql);
            self::assertStringContainsString('idx_attempt_notification_number', $sql);
            self::assertStringContainsString('`subject`', $sql);
            self::assertStringContainsString('`body`', $sql);
            self::assertStringContainsString('`htmlbody`', $sql);
            self::assertStringContainsString('`requested_language`', $sql);
            self::assertStringContainsString('`resolved_language`', $sql);
            self::assertStringContainsString('`registration_revision`', $sql);
            self::assertStringContainsString('`resend_of`', $sql);
            self::assertStringContainsString('`ticket_id`', $sql);
            self::assertStringContainsString('`attachments_json`', $sql);
        }

        self::assertStringNotContainsString('DROP TABLE', $update);
        self::assertStringContainsString("('notification_retention_years', '4')", $install);
        self::assertStringContainsString("('notification_user_resend_limit', '2')", $install);
        self::assertStringContainsString("('notification_user_resend_cooldown_minutes', '10')", $install);
    }

    public function testInstallerRepairIsAdditiveAndIdempotent(): void
    {
        $installer = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');
        self::assertStringContainsString('repair510NotificationSchema()', $installer);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_notifications`', $installer);
        self::assertStringContainsString('notification_schema_ready', $installer);
        self::assertStringNotContainsString('DELETE FROM #__jem_notifications', $installer);
    }
}
