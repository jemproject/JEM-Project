<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SqlConfigDefaultsTest extends TestCase
{
    public function testInstallSqlContainsAttachmentConfigDefaults(): void
    {
        $sql = $this->read(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');

        self::assertStringContainsString("('attachments_path', 'media/com_jem/attachments')", $sql);
        self::assertStringContainsString("('attachments_maxsize', '1000')", $sql);
        self::assertStringContainsString("('attachments_layout', 'column')", $sql);
        self::assertStringContainsString("('attachments_icon_size', 'normal')", $sql);
        self::assertStringContainsString('txt,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,ics,jpg,jpeg,gif,png,webp,zip,tar.gz', $sql);
    }

    public function testUpdateSqlContainsAttachmentConfigDefaults(): void
    {
        $sql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/4.5.0.sql');

        self::assertStringContainsString("INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('attachments_layout', 'column')", $sql);
        self::assertStringContainsString("INSERT IGNORE INTO `#__jem_config` (`keyname`, `value`) VALUES ('attachments_icon_size', 'normal')", $sql);
        self::assertStringContainsString("UPDATE `#__jem_config` SET `value` = 'txt,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,ics,jpg,jpeg,gif,png,webp,zip,tar.gz'", $sql);
    }

    public function testLinksTableSchemaContainsOwnershipColumns(): void
    {
        $installSql = $this->read(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $updateSql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/4.5.0.sql');

        foreach (array($installSql, $updateSql) as $sql) {
            self::assertStringContainsString('`created` DATETIME', $sql);
            self::assertStringContainsString('`created_by` INT(11) NOT NULL', $sql);
            self::assertStringContainsString('`modified` DATETIME DEFAULT NULL', $sql);
            self::assertStringContainsString('`modified_by` INT(11) DEFAULT NULL', $sql);
        }
    }

    public function testAttachmentsSchemaMigrationKeepsCreatedByColumn(): void
    {
        $sql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/4.5.0.sql');

        self::assertStringContainsString('ALTER TABLE `#__jem_attachments` CHANGE `added` `created` DATETIME NULL DEFAULT NULL', $sql);
        self::assertStringContainsString('ALTER TABLE `#__jem_attachments` CHANGE `added_by` `created_by` INT(11) NOT NULL DEFAULT 0', $sql);
    }

    public function testFreshInstallSchemaMatchesCurrentUpdateSchema(): void
    {
        $sql = $this->read(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');

        self::assertStringContainsString("`online_meeting_url` varchar(2048) NOT NULL DEFAULT ''", $sql);
        self::assertStringContainsString("`online_meeting_label` varchar(255) NOT NULL DEFAULT ''", $sql);
        self::assertStringContainsString('KEY `idx_type` (`type_id`)', $sql);
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
