<?php

declare(strict_types=1);

use Joomla\CMS\Language\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Uses native Joomla language loading with disposable files, never an installed application. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JubileeIntroLanguageLoadingTest extends TestCase
{
    private string $scratch = '';

    /**
     * Load Joomla libraries and isolate language files, overrides and caches.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        $this->scratch = sys_get_temp_dir() . '/jem-jubilee-language-' . bin2hex(random_bytes(8));
        mkdir($this->scratch . '/language/en-GB', 0777, true);
        define('JPATH_BASE', $this->scratch);
        define('JPATH_ROOT', $this->scratch);
        define('JPATH_CACHE', $this->scratch . '/cache');
        define('JPATH_LIBRARIES', $root . '/libraries');
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        foreach (['mod_jem_jubilee.ini', 'mod_jem_jubilee.sys.ini'] as $filename) {
            copy(
                JEM_TEST_ROOT . '/modules/mod_jem_jubilee/language/en-GB/' . $filename,
                $this->scratch . '/language/en-GB/' . $filename
            );
        }
    }

    /**
     * Remove only this test's generated directory and its cache files.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->scratch === '' || !is_dir($this->scratch)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->scratch, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->scratch);
    }

    /**
     * Exercise each language file alone and both possible load orders.
     *
     * @return iterable<string, array{0: array<string>}>
     */
    public static function loadOrders(): iterable
    {
        yield 'module only' => [['mod_jem_jubilee']];
        yield 'system only' => [['mod_jem_jubilee.sys']];
        yield 'module then system' => [['mod_jem_jubilee', 'mod_jem_jubilee.sys']];
        yield 'system then module' => [['mod_jem_jubilee.sys', 'mod_jem_jubilee']];
    }

    /**
     * Keep all placeholder guidance available regardless of language file order.
     *
     * @param array<string> $extensions Extension language files to load.
     *
     * @return void
     */
    #[DataProvider('loadOrders')]
    public function testNativeLoadingRetainsGuidance(array $extensions): void
    {
        $language = new Language('en-GB', false);
        foreach ($extensions as $extension) {
            self::assertTrue($language->load($extension, $this->scratch, 'en-GB', true, false));
            self::assertSame('Intro text', $language->_('MOD_JEM_JUBILEE_INTROTEXT'));
            $description = $language->_('MOD_JEM_JUBILEE_INTROTEXT_DESC');
            foreach (['[day]', '[month]', '[year]'] as $token) {
                self::assertStringContainsString($token, $description);
            }
            self::assertStringContainsString('above the module content', $description);
            self::assertStringContainsString('Leave empty', $description);
            self::assertStringNotContainsString('beside the calendar', $description);
        }
    }
}
