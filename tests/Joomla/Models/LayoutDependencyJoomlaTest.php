<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\MVC\View\HtmlView;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

/** Native Joomla template lookup, using isolated test partials rather than installed overrides. */
final class LayoutDependencyJoomlaTest extends JoomlaTestCase
{
    private static mixed $previousSession;
    private string $directory;

    /** @return void Start Joomla without changing its installed files or database records. */
    public static function setUpBeforeClass(): void
    {
        self::bootJoomlaSite();
        self::$previousSession = Factory::getApplication()->getSession();
        Factory::getApplication()->setSession(Factory::getContainer()->get('session.cli'));
        Factory::getApplication()->loadDocument();
    }

    /** @return void Restore the session used before this test class. */
    public static function tearDownAfterClass(): void
    {
        Factory::getApplication()->setSession(self::$previousSession);
    }

    /** @return void Prepare an isolated layout search directory. */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/jem-legend-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    /** @return void Remove only the fixture files created for this test. */
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.php') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /** @return iterable<string,array{string}> Both shipped monthly calendar styles. */
    public static function calendarStyles(): iterable
    {
        yield 'standard' => [''];
        yield 'responsive' => ['/responsive'];
    }

    /**
     * Render the actual legend call through Joomla, with and without a custom legend.
     *
     * @param string $style Calendar style directory.
     * @return void
     */
    #[DataProvider('calendarStyles')]
    public function testCalendarLegendHasCoreFallbackAndCustomPriority(string $style): void
    {
        $base = JEM_TEST_ROOT . '/site/views/calendar/tmpl' . $style;
        $source = (string) file_get_contents($base . '/default.php');
        self::assertSame(2, preg_match_all('/echo \$this->loadTemplate\(\x27legend\x27\);/', $source, $calls));
        file_put_contents($this->directory . '/default.php', '<?php ' . $calls[0][0]);
        $view = new class (['name' => 'calendar', 'option' => 'com_jem', 'base_path' => JEM_TEST_ROOT . '/site', 'template_path' => $base]) extends HtmlView {
            public array $rows = [];
        };
        $view->addTemplatePath($this->directory);
        self::assertStringContainsString('calendarLegends', $view->loadTemplate());
        file_put_contents($this->directory . '/default_legend.php', '<?php echo "custom legend";');
        self::assertSame('custom legend', $view->loadTemplate());
        $view->setLayout('alternative');
        self::assertSame('custom legend', $view->loadTemplate());
        file_put_contents($this->directory . '/alternative_legend.php', '<?php echo "alternative legend";');
        self::assertSame('alternative legend', $view->loadTemplate());
    }

    /** @return void Resolve both Wide module partials through Joomla's module layout loader. */
    public function testWideModulePartialsResolveThroughNativeLoader(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_wide/tmpl/responsive.php');
        self::assertStringContainsString('use Joomla\\CMS\\Helper\\ModuleHelper;', $source);
        self::assertSame(2, preg_match_all('/include (ModuleHelper::getLayoutPath\([^;]+\));/', $source, $calls));
        foreach ($calls[1] as $expression) {
            $path = eval('return ' . str_replace('ModuleHelper::', '\\' . ModuleHelper::class . '::', $expression) . ';');
            self::assertFileExists($path);
            self::assertContains(basename($path), ['default_jem_eventslist.php', 'default_jem_eventslist_small.php']);
        }
    }
}
