<?php

declare(strict_types=1);

namespace JemProject\Tests\Joomla\Environment\TypeTitle;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Document\Document;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Pathway\Pathway;
use Joomla\Module\Breadcrumbs\Site\Helper\BreadcrumbsHelper;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Production title blocks/templates and Joomla encoding boundaries; no HTTP, database or site writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TypeTitleEncodingTest extends TestCase
{
    private string $joomlaRoot;

    /** @return void Load read-only framework libraries without bootstrapping the installed site. */
    protected function setUp(): void
    {
        $this->joomlaRoot = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($this->joomlaRoot . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT pointing to Joomla libraries.');
        }
        require_once $this->joomlaRoot . '/libraries/vendor/autoload.php';
        $root = $this->joomlaRoot;
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once $root . '/modules/mod_breadcrumbs/src/Helper/BreadcrumbsHelper.php';
        $language = str_replace("\r", '', file_get_contents(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini'));
        foreach ([
            'COM_JEM_TYPEEVENTS_TITLE', 'COM_JEM_TYPEVENUES_TITLE', 'COM_JEM_TYPECATEGORIES_TITLE',
            'COM_JEM_TYPEEVENTS_VIEW_DEFAULT_TITLE', 'COM_JEM_TYPEVENUES_VIEW_DEFAULT_TITLE',
        ] as $key) {
            self::assertSame(1, preg_match('/^' . $key . '="(.*)"$/m', $language, $match));
            Text::$strings[$key] = $match[1];
        }
    }

    /** @return iterable<string, array{string, string, int, bool}> Layouts and title/menu settings. */
    public static function titleCases(): iterable
    {
        foreach (['typeevents', 'typevenues', 'categories'] as $view) {
            foreach ($view === 'categories' ? ['default', 'responsive/default'] : ['default'] as $layout) {
                foreach ([0, 1, 2] as $mode) {
                    foreach ([false, true] as $customMenu) {
                        yield "$view:$layout:$mode:" . (int) $customMenu => [$view, $layout, $mode, $customMenu];
                    }
                }
            }
        }
    }

    /**
     * Extract actual title preparation, including site-name decoration.
     *
     * @param string $view View directory.
     * @param bool $typed Resolved-type or fallback branch.
     * @return \Closure Production statements with explicit service boundaries.
     */
    private static function titleBlock(string $view, bool $typed): \Closure
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
        $pattern = $view === 'categories'
            ? '/if \(\$isTypeCategoryView && \$categoryType\).*?\$document->setMetaData\([^\n]+;/s'
            : ($typed
                ? '/\$typeName\s*=.*?\$document->setMetaData\([^\n]+;/s'
                : '/if \(!\$typeObj\) \{.*?\$document->setMetaData\([^\n]+;/s');
        self::assertSame(1, preg_match($pattern, $source, $block));
        $code = $block[0] . (!$typed && $view !== 'categories' ? '}' : '');
        return eval('namespace ' . __NAMESPACE__ . ';
            return static function ($app, $params, $document, $typeObj, $isTypeCategoryView = true) {
                $categoryType = $typeObj;
                $pathway = $app->getPathway();
                $pagetitle = $params->get("page_title", "Menu title");
                $pageheading = $params->get("page_heading", "Menu heading");
                ' . $code . '
            };');
    }

    /**
     * Construct minimal application state for the real breadcrumb helper.
     *
     * @param int $mode Site-name placement.
     * @return SiteApplication Application without services, sessions or database initialization.
     */
    private static function application(int $mode): SiteApplication
    {
        // SiteApplication is final in Joomla 6; inject only state required by these read-only methods.
        $reflection = new \ReflectionClass(SiteApplication::class);
        $app = $reflection->newInstanceWithoutConstructor();
        foreach ([
            'config' => new Registry(['sitename_pagetitles' => $mode, 'sitename' => 'Site & Test']),
            'pathway' => new Pathway(),
            'input' => new \Joomla\Input\Input([]),
        ] as $property => $value) {
            $reflection->getProperty($property)->setValue($app, $value);
        }
        return $app;
    }

    /**
     * Render actual template fragments using Joomla HtmlView::escape.
     *
     * @param string $view View directory.
     * @param string $layout Template path.
     * @param Registry $params Prepared parameters.
     * @param object $type Type record.
     * @return array{string, string} H1 inner HTML and H2 name HTML.
     */
    private static function renderHeadings(string $view, string $layout, Registry $params, object $type): array
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/tmpl/' . $layout . '.php');
        self::assertSame(1, preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $source, $h1));
        self::assertSame(1, preg_match(
            '/<\?php echo htmlspecialchars\(\$this->(?:type|categoryType)->name,[^;]+; \?>/',
            $source,
            $h2
        ));
        $renderView = new class ($params, $type) extends HtmlView {
            public Registry $params;
            public object $type;
            public object $categoryType;

            /** @param Registry $params Prepared parameters. @param object $type Type record. */
            public function __construct(Registry $params, object $type)
            {
                $this->params = $params;
                $this->type = $this->categoryType = $type;
            }
        };
        $render = (function (string $markup): string {
            ob_start();
            try {
                eval('?>' . $markup);
                return trim(ob_get_contents());
            } finally {
                ob_end_clean();
            }
        })->bindTo($renderView);
        return [$render($h1[1]), $render($h2[0])];
    }

    /**
     * Execute Joomla's title-render expression without unrelated document assets.
     *
     * @param Document $document Prepared document.
     * @return string Encoded title content.
     */
    private function renderTitle(Document $document): string
    {
        $source = file_get_contents($this->joomlaRoot . '/libraries/src/Document/Renderer/Html/MetasRenderer.php');
        self::assertSame(1, preg_match(
            '/htmlspecialchars\(\$this->_doc->getTitle\(\),[^;]+?(?= \. \'<\/title>)/',
            $source,
            $expression
        ));
        return eval('return ' . str_replace('$this->_doc', '$document', $expression[0]) . ';');
    }

    /**
     * Preserve literal text safely without changing existing menu-title selection.
     *
     * @param string $view View directory.
     * @param string $layout Template path.
     * @param int $mode Site-name placement.
     * @param bool $customMenu Explicit menu titles configured.
     * @return void
     */
    #[DataProvider('titleCases')]
    public function testTypeNamesAreEncodedAtTheirOutputBoundary(
        string $view,
        string $layout,
        int $mode,
        bool $customMenu
    ): void {
        $run = self::titleBlock($view, true);
        foreach ([
            'Arts & Culture', 'Plain', 'Música y café', 'Rock "Live"', "Children's Arts",
            'Literal &amp; text', 'Literal &#039; text', '<b>"Arts" & more</b>', '',
        ] as $name) {
            $app = self::application($mode);
            $params = new Registry($customMenu
                ? ['page_title' => 'Custom & title', 'page_heading' => 'Custom & heading'] : []);
            $document = (new \ReflectionClass(Document::class))->newInstanceWithoutConstructor();
            $type = (object) ['name' => $name];
            $run($app, $params, $document, $type);
            $expected = match ($view) {
                'typeevents' => 'Events of type: ',
                'typevenues' => 'Venues of type: ',
                default => 'Categories of type: ',
            } . $name;
            $title = match ($mode) {
                1 => 'Site & Test - ' . $expected,
                2 => $expected . ' - Site & Test',
                default => $expected,
            };
            self::assertSame($expected, $params->get('page_heading'));
            self::assertSame($title, $document->getTitle());
            self::assertSame($title, $document->getMetaData('title'));
            self::assertSame($name, $type->name, 'Do not decode or mutate stored type text.');
            [$h1, $h2] = self::renderHeadings($view, $layout, $params, $type);
            self::assertSame(trim(htmlspecialchars($expected, ENT_QUOTES, 'UTF-8')), $h1);
            self::assertSame(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $h2);
            self::assertSame(htmlspecialchars($title, ENT_COMPAT, 'UTF-8'), $this->renderTitle($document));
            $pathway = $app->getPathway()->getPathway();
            if ($view === 'categories') {
                self::assertSame([], $pathway, 'Do not add a new breadcrumb to Categories.');
            } else {
                self::assertCount(1, $pathway);
                self::assertSame($expected, $pathway[0]->name);
                $crumbs = (new BreadcrumbsHelper())->getBreadcrumbs(new Registry(['showHome' => 0]), $app);
                self::assertCount(1, $crumbs);
                self::assertSame(htmlspecialchars($expected, ENT_COMPAT, 'UTF-8'), $crumbs[0]->name);
            }
        }
    }

    /** @return iterable<string, array{string, int}> Fallback views and site-name modes. */
    public static function fallbackCases(): iterable
    {
        foreach (['typeevents', 'typevenues', 'categories'] as $view) {
            foreach ([0, 1, 2] as $mode) {
                yield "$view:$mode" => [$view, $mode];
            }
        }
    }

    /**
     * Preserve existing fallback title and menu-heading decisions.
     *
     * @param string $view View directory.
     * @param int $mode Site-name placement.
     * @return void
     */
    #[DataProvider('fallbackCases')]
    public function testUntypedTitlePolicyIsUnchanged(string $view, int $mode): void
    {
        $run = self::titleBlock($view, false);
        $app = self::application($mode);
        $params = new Registry(['page_heading' => 'Custom & heading', 'page_title' => 'Custom & title']);
        $document = (new \ReflectionClass(Document::class))->newInstanceWithoutConstructor();
        $run($app, $params, $document, null, false);
        $baseTitle = match ($view) {
            'typeevents' => Text::_('COM_JEM_TYPEEVENTS_VIEW_DEFAULT_TITLE'),
            'typevenues' => Text::_('COM_JEM_TYPEVENUES_VIEW_DEFAULT_TITLE'),
            default => 'Custom & title',
        };
        self::assertSame(match ($mode) {
            1 => 'Site & Test - ' . $baseTitle,
            2 => $baseTitle . ' - Site & Test',
            default => $baseTitle,
        }, $document->getTitle());
        self::assertSame($view === 'typevenues' ? $baseTitle : 'Custom & heading', $params->get('page_heading'));
        self::assertSame($view === 'categories' ? [] : [$baseTitle], $app->getPathway()->getPathwayNames());
    }
}

/** Translation boundary using JEM's actual language strings. */
final class Text
{
    public static array $strings = ['JPAGETITLE' => '%s - %s'];

    /** @param string $key Language key. @return string Source translation. */
    public static function _(string $key): string
    {
        return self::$strings[$key];
    }

    /** @param string $key Language key. @param mixed ...$args Format arguments. @return string Formatted text. */
    public static function sprintf(string $key, mixed ...$args): string
    {
        return sprintf(self::_($key), ...$args);
    }
}
