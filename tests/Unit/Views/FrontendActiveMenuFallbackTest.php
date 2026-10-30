<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use ErrorException;
use JemProject\Tests\MenuFallback\Pathway;
use JemProject\Tests\MenuFallback\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute actual title, menu-ID and breadcrumb preparation with isolated Joomla service boundaries. */
final class FrontendActiveMenuFallbackTest extends TestCase
{
    /** @return void Load independent test services without bootstrapping a Joomla site. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/frontend_menu_environment.php';
    }

    /**
     * Run a production fragment and make every PHP warning fail the test.
     *
     * @param string $code Reviewed preparation fragment.
     * @param array<string, mixed> $variables Request/view dependencies.
     * @return mixed Fragment result.
     */
    private function execute(string $code, array $variables): mixed
    {
        $run = eval('namespace JemProject\\Tests\\MenuFallback;'
            . 'return static function (array $variables) { extract($variables, EXTR_SKIP); ' . $code . ' };');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            return $run($variables);
        } finally {
            restore_error_handler();
        }
    }

    /** @return iterable<string, array{string, string, bool, int}> View fallback, active menu and title overrides. */
    public static function titleCases(): iterable
    {
        $views = [
            'venues' => 'COM_JEM_VENUES',
            'categories' => 'COM_JEM_CATEGORIES',
            'calendar' => 'COM_JEM_CALENDAR',
            'weekcal' => 'COM_JEM_WEEKCALENDAR',
            'category' => 'Accessible category',
            'venue' => 'Accessible venue',
            'eventsmap' => 'COM_JEM_EVENTSMAP_PAGETITLE',
        ];
        foreach ($views as $view => $fallback) {
            foreach ([false, true] as $active) {
                foreach ([0, 1, 2] as $overrides) {
                    yield "$view:$active:$overrides" => [$view, $fallback, $active, $overrides];
                }
            }
        }
    }

    /**
     * Verify menu/default/entity titles while retaining configured title, heading and page class.
     *
     * @param string $view View name; Category/Venue exercise their calendar branch.
     * @param string $fallback Expected default label.
     * @param bool $active Whether a Joomla menu is active.
     * @param int $overrides Zero, title only, or title and heading.
     * @return void
     */
    #[DataProvider('titleCases')]
    public function testTitlesWithoutAnActiveMenu(string $view, string $fallback, bool $active, int $overrides): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
        self::assertSame(1, preg_match(
            '/^[ \\t]*(?:\\$menuTitle[^\\n]*\\R[ \\t]*)?(?:\\$pagetitle\\s*=\\s*)?'
            . '\\$params->def\\(\\x27page_title\\x27,.*?'
            . '\\$pageclass_sfx\\s*=\\s*\\$params->get\\(\\x27pageclass_sfx\\x27\\);/ms',
            $source,
            $match
        ));
        $values = ['pageclass_sfx' => 'preserved-class'];
        if ($overrides >= 1) {
            $values['page_title'] = 'Configured title';
        }
        if ($overrides === 2) {
            $values['page_heading'] = 'Configured heading';
        }
        [$title, $heading, $pageClass] = $this->execute(
            $match[0] . ' return [$pagetitle, $pageheading ?? $params->get("page_heading"), $pageclass_sfx];',
            [
                'params' => new Registry($values),
                'menuitem' => $active ? (object) ['title' => 'Menu title', 'id' => 321] : null,
                'category' => (object) ['catname' => 'Accessible category'],
                'venue' => (object) ['venue' => 'Accessible venue'],
            ]
        );
        $expectedTitle = $overrides >= 1 ? 'Configured title' : ($active ? 'Menu title' : $fallback);
        self::assertSame($expectedTitle, $title);
        self::assertSame($overrides === 2 ? 'Configured heading' : $expectedTitle, $heading);
        self::assertSame('preserved-class', $pageClass);
    }

    /** @return iterable<string, array{string, bool}> Menu-ID assignments with/without active menu. */
    public static function idCases(): iterable
    {
        foreach (['myevents', 'eventslist'] as $view) {
            foreach ([false, true] as $active) {
                yield "$view:$active" => [$view, $active];
            }
        }
    }

    /** @param string $view Affected view. @param bool $active Menu exists. @return void */
    #[DataProvider('idCases')]
    public function testMissingMenuUsesZeroId(string $view, bool $active): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
        $pattern = $view === 'myevents' ? '/\\$this->itemid\\s*=.*?;/' : '/\\$model->setState\\(\\x27Itemid\\x27,.*?;/';
        self::assertSame(1, preg_match($pattern, $source, $match));
        $model = new class {
            public mixed $id = null;

            /** @param string $key State name. @param mixed $value ID. @return void */
            public function setState(string $key, mixed $value): void
            {
                $this->id = $value;
            }
        };
        $menu = $active ? (object) ['id' => 321] : null;
        $result = $this->execute(
            str_replace('$this->', '$viewData->', $match[0])
                . ($view === 'myevents' ? 'return $viewData->itemid;' : 'return $model->id;'),
            ['model' => $model, 'viewData' => new \stdClass(), 'menuitem' => $menu, 'menuactive' => $menu]
        );
        self::assertSame($active ? 321 : 0, $result);
    }

    /** @return iterable<string, array{bool, bool, bool, bool}> Menu, pathway, archive and custom heading. */
    public static function breadcrumbCases(): iterable
    {
        foreach ([false, true] as $active) {
            foreach ([false, true] as $populated) {
                foreach ([false, true] as $archive) {
                    foreach ([false, true] as $custom) {
                        yield "$active:$populated:$archive:$custom" => [$active, $populated, $archive, $custom];
                    }
                }
            }
        }
    }

    /**
     * Keep unrelated/empty breadcrumbs untouched and preserve the archive suffix and destination.
     *
     * @param bool $active Menu exists.
     * @param bool $populated Pathway contains a leaf.
     * @param bool $archive Archive request.
     * @param bool $custom Explicit page title/heading.
     * @return void
     */
    #[DataProvider('breadcrumbCases')]
    public function testVenueBreadcrumbs(bool $active, bool $populated, bool $archive, bool $custom): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/venues/view.html.php');
        self::assertSame(1, preg_match(
            '/(?:\\$menuTitle[^\\n]*\\R[ \\t]*)?\\$pagetitle = \\$params->def\\(\\x27page_title\\x27.*?'
            . '(?=\\s*\/\/ Add site name to title)/s',
            $source,
            $match
        ));
        $pathway = new Pathway($populated ? [(object) ['name' => 'Existing leaf', 'link' => '/existing']] : []);
        $app = new class ($pathway) {
            /** @param Pathway $pathway Current breadcrumbs. */
            public function __construct(private Pathway $pathway)
            {
            }

            /** @return Pathway Current breadcrumbs. */
            public function getPathWay(): Pathway
            {
                return $this->pathway;
            }
        };
        $params = new Registry($custom ? ['page_title' => 'Custom title', 'page_heading' => 'Custom heading'] : []);
        [$title, $heading] = $this->execute($match[0] . 'return [$pagetitle, $pageheading];', [
            'params' => $params, 'app' => $app,
            'menuitem' => $active ? (object) ['title' => 'Menu title'] : null,
            'task' => $archive ? 'archive' : '',
        ]);
        $suffix = $archive ? ' - COM_JEM_ARCHIVE' : '';
        $defaultTitle = $active ? 'Menu title' : 'COM_JEM_VENUES';
        self::assertSame(($custom ? 'Custom title' : $defaultTitle) . $suffix, $title);
        self::assertSame(($custom ? 'Custom heading' : $defaultTitle) . $suffix, $heading);
        self::assertSame($heading, $params->get('page_heading'));
        self::assertSame($active && $populated ? [0] : [], $pathway->renamed);
        $expected = $populated ? [$active ? 'Menu title' : 'Existing leaf'] : [];
        if ($archive) {
            $expected[] = 'COM_JEM_ARCHIVE';
        }
        self::assertSame($expected, array_column($pathway->getPathway(), 'name'));
        if ($archive) {
            $items = $pathway->getPathway();
            self::assertSame('index.php?option=com_jem&view=venues&task=archive', end($items)->link);
        }
    }
}
