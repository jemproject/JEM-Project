<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogResponseSecurityTest extends TestCase
{
    public static function controllerProvider(): array
    {
        return [
            'settings logs' => ['admin/controllers/settings.php'],
            'import logs'   => ['admin/controllers/import.php'],
        ];
    }

    #[DataProvider('controllerProvider')]
    public function testLogResponsesSendHeadersBeforeClosing(string $relativePath): void
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

        self::assertGreaterThanOrEqual(2, substr_count($contents, '->sendHeaders();'));
        self::assertStringContainsString("'Content-Type', 'application/octet-stream'", $contents);
        self::assertStringContainsString("'Content-Disposition', 'attachment; filename=\"'", $contents);
        self::assertStringContainsString("'X-Content-Type-Options', 'nosniff'", $contents);
        self::assertStringContainsString('while (ob_get_level())', $contents);
    }

    public function testSettingsLogPreviewInitialisesTheApplication(): void
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/settings.php');
        $viewLog = substr($contents, strpos($contents, 'public function viewLog()'));
        $viewLog = substr($viewLog, 0, strpos($viewLog, 'public function downloadLog()'));

        self::assertStringContainsString('$app = Factory::getApplication();', $viewLog);
        self::assertStringContainsString("->setHeader('Content-Type', 'text/html; charset=utf-8', true)", $viewLog);
        self::assertStringContainsString('->sendHeaders();', $viewLog);
    }

    public static function downloadViewProvider(): array
    {
        return [
            'settings logs' => ['admin/views/settings/tmpl/default_configinfo.php'],
            'import logs'   => ['admin/views/import/tmpl/default.php'],
        ];
    }

    #[DataProvider('downloadViewProvider')]
    public function testLogDownloadLinksRequestBrowserDownload(string $relativePath): void
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

        self::assertStringContainsString(' download="<?php echo htmlspecialchars($logFile[\'file\']', $contents);
    }
}
