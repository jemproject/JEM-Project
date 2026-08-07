<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SqlConfigDefaultsTest extends TestCase
{
    public function testImportSecurityDefaultsAreMigratedWithoutOverwritingSavedValues(): void
    {
        $sql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');

        self::assertStringContainsString("JSON_INSERT(", $sql);
        self::assertStringContainsString("'$.import_additional_blocked_tags', ''", $sql);
        self::assertStringContainsString("'$.import_allow_trusted_iframes', '0'", $sql);
        self::assertStringContainsString("'$.import_trusted_iframe_hosts', ''", $sql);
        self::assertStringNotContainsString('JSON_SET(', $sql);
    }

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

    public function testAttachmentsSchemaTracksSuccessfulDownloads(): void
    {
        $installSql = $this->read(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $updateSql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');

        foreach (array($installSql, $updateSql) as $sql) {
            self::assertStringContainsString('`downloads`', $sql);
            self::assertStringContainsString('`last_download`', $sql);
        }

        self::assertStringContainsString('ALTER TABLE `#__jem_attachments`', $updateSql);
    }

    public function testEventsSchemaTracksLastVisitWithoutResettingHits(): void
    {
        $installSql = $this->read(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $updateSql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');

        self::assertStringContainsString('`hits` int(11) unsigned NOT NULL DEFAULT', $installSql);
        self::assertStringContainsString('`last_visit` datetime NULL DEFAULT NULL', $installSql);
        self::assertStringContainsString('ALTER TABLE `#__jem_events` ADD COLUMN `last_visit`', $updateSql);
        self::assertStringNotContainsString('UPDATE `#__jem_events` SET `hits`', $updateSql);
    }

    public function test501SchemaChangesUseIndividualJoomlaDetectableStatements(): void
    {
        $sql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');
        $lines = preg_grep('/^ALTER TABLE /', preg_split('/\R/', $sql) ?: array()) ?: array();

        self::assertGreaterThanOrEqual(
            19,
            count($lines),
            'The 5.0.1 migration must retain every previously shipped schema operation.'
        );

        foreach ($lines as $line) {
            self::assertMatchesRegularExpression(
                '/^ALTER TABLE `#__jem_[a-z_]+` ADD (?:COLUMN|INDEX) .+ \\/\\*\\* CAN FAIL \\*\\*\\/;$/',
                $line,
                'Each 5.0.1 schema change must be one Joomla-detectable statement on one line.'
            );
            self::assertStringNotContainsString(
                ', ADD ',
                $line,
                'Joomla schema changes must not group multiple ADD operations.'
            );
        }
    }

    public function test501InstallAndUpdateSqlContainEveryNewTableField(): void
    {
        $installSql = $this->read(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $updateSql = $this->read(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');
        $fieldsByTable = array(
            '#__jem_events' => array(
                'timezone_mode',
                'timezone',
                'start_utc',
                'end_utc',
                'last_visit',
                'series_id',
                'series_order',
            ),
            '#__jem_venues' => array('district', 'level', 'capacity', 'timezone', 'email', 'phone', 'mobile'),
            '#__jem_attachments' => array('downloads', 'last_download'),
        );

        foreach ($fieldsByTable as $table => $fields) {
            foreach ($fields as $field) {
                self::assertStringContainsString('`' . $field . '`', $installSql);
                self::assertStringContainsString(
                    'ALTER TABLE `' . $table . '` ADD COLUMN `' . $field . '`',
                    $updateSql,
                    $table . '.' . $field . ' must be managed by Joomla update SQL.'
                );
            }
        }

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_event_series`', $updateSql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_event_series`', $installSql);
    }

    public function test501PhpSchemaRepairRemainsACompleteSecondaryFallback(): void
    {
        $script = $this->read(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString('Joomla owns the normal schema lifecycle', $script);
        self::assertStringContainsString('$this->repair501SchemaFallback();', $script);

        foreach (array(
            'timezone_mode',
            'timezone',
            'start_utc',
            'end_utc',
            'last_visit',
            'series_id',
            'series_order',
            'district',
            'level',
            'capacity',
            'email',
            'phone',
            'mobile',
            'downloads',
            'last_download',
        ) as $field) {
            self::assertStringContainsString("'" . $field . "'", $script);
        }

        self::assertStringContainsString("'idx_series'", $script);
        self::assertStringContainsString("'#__jem_event_series'", $script);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS', $script);
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
