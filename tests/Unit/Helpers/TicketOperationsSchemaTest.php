<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

final class TicketOperationsSchemaTest extends TestCase
{
    public function testInstallUpgradeAndBetaRepairUseTheSameLedger(): void
    {
        $definitions = [];
        foreach (['admin/sql/install.mysql.utf8.sql', 'admin/sql/updates/mysql/5.1.0.sql', 'script.php'] as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
            self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_ticket_operations`.*?ENGINE=InnoDB;/s', $source, $match));
            $definitions[] = preg_replace('/\s+/', ' ', $match[0]);
        }
        self::assertSame($definitions[0], $definitions[1]);
        self::assertSame($definitions[0], $definitions[2]);
        self::assertStringContainsString('UNIQUE KEY `idx_ticket_operation` (`operation_reference`)', $definitions[0]);
        self::assertStringContainsString("'#__jem_ticket_operations',", file_get_contents(JEM_TEST_ROOT . '/script.php'));
        self::assertStringContainsString("'ticket_operations',", file_get_contents(JEM_TEST_ROOT . '/admin/models/housekeeping.php'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPdfLibraryLoadsOutsideTheJemComponentContext(): void
    {
        define('JPATH_COMPONENT_SITE', '/not-the-jem-component');
        require_once __DIR__ . '/../../../site/classes/pdf.class.php';
        self::assertTrue(JemPdf::isAvailable());
        self::assertTrue(class_exists('TCPDF', false));
    }
}
