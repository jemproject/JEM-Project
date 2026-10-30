<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute production menu/title/link preparation without a Joomla application or database. */
final class VenuePrintLinkTest extends TestCase
{
    /**
     * Exercise matching, absent and unrelated menus in ordinary, archive and print requests.
     *
     * @return iterable<string, array{string, string, bool, string}> Request contexts.
     */
    public static function contexts(): iterable
    {
        foreach (['venueslist', 'venuesmap'] as $view) {
            foreach (['matching', 'missing', 'other-component', 'other-view'] as $menu) {
                foreach ([false, true] as $print) {
                    foreach (['', 'archive'] as $task) {
                        yield $view . ':' . $menu . ':' . (int) $print . ':' . $task => [$view, $menu, $print, $task];
                    }
                }
            }
        }
    }

    /**
     * Require a usable print target while retaining menu-dependent presentation.
     *
     * @param string $view Requested venue view.
     * @param string $menu Active-menu scenario.
     * @param bool $print Whether the request is already in print mode.
     * @param string $task Normal or archive request.
     * @return void
     */
    #[DataProvider('contexts')]
    public function testPrintLinkDoesNotDependOnAnActiveMenu(string $view, string $menu, bool $print, string $task): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
        self::assertSame(1, preg_match('/\$useMenuItemParams\s*=.*?;/s', $source, $selection));
        self::assertSame(1, preg_match('/\/\/ Set Page title\R.*?(?=\/\/ Add site name to title)/s', $source, $preparation));
        self::assertSame(1, preg_match('/\$this->print_link\s*=\s*\$print_link;/', $source, $assignment));

        // Only external translation/routing/registry services are substituted; the decision is production code.
        $prepare = eval(
            'namespace ' . __NAMESPACE__ . ';'
            . 'use ' . __NAMESPACE__ . '\\VenuePrintLinkRoute as Route;'
            . 'use ' . __NAMESPACE__ . '\\VenuePrintLinkText as Text;'
            . 'return static function ($menuitem, $params, $print, $task) {'
            . $selection[0] . $preparation[0]
            . '$viewData = new \\stdClass();'
            . str_replace('$this->', '$viewData->', $assignment[0])
            . 'return [$viewData->print_link, $pagetitle, $pageheading, $pageclass_sfx]; };'
        );
        $menuitem = $menu === 'missing' ? null : (object) [
            'title' => 'Menu title',
            'query' => [
                'option' => $menu === 'other-component' ? 'com_content' : 'com_jem',
                'view' => $menu === 'other-view' ? 'eventslist' : $view,
            ],
        ];
        $params = new VenuePrintLinkParams([
            'page_title' => 'Custom title',
            'page_heading' => 'Custom heading',
            'pageclass_sfx' => 'custom-class',
        ]);
        VenuePrintLinkRoute::$calls = [];
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            [$link, $title, $heading, $pageClass] = $prepare($menuitem, $params, $print, $task);
        } finally {
            restore_error_handler();
        }

        $url = 'index.php?option=com_jem&view=' . $view . '&print=1&tmpl=component';
        self::assertSame([$url], VenuePrintLinkRoute::$calls);
        self::assertSame('/site/' . $url, $link, 'Never substitute an empty print URL.');
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        self::assertSame(['option' => 'com_jem', 'view' => $view, 'print' => '1', 'tmpl' => 'component'], $query);
        $defaultTitle = 'COM_JEM_' . strtoupper($view) . '_PAGETITLE';
        self::assertSame($menu === 'matching' ? 'Custom title' : $defaultTitle, $title);
        self::assertSame($menu === 'matching' ? 'Custom heading' : $defaultTitle, $heading);
        self::assertSame($menu === 'matching' ? 'custom-class' : '', $pageClass);
        self::assertSame($heading, $params->get('page_heading'));
    }
}

/** Minimal registry boundary for the production page-title block. */
final class VenuePrintLinkParams
{
    /** @param array<string, mixed> $values Initial menu/component parameters. */
    public function __construct(private array $values)
    {
    }

    /** @param string $key Parameter. @param mixed $default Fallback. @return mixed Effective value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Parameter. @param mixed $value New value. @return void */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /** @param string $key Parameter. @param mixed $value Fallback. @return void */
    public function def(string $key, mixed $value): void
    {
        $this->values[$key] ??= $value;
    }
}

/** Routing boundary; verifies delegation, not Joomla SEF routing itself. */
final class VenuePrintLinkRoute
{
    public static array $calls = [];

    /** @param string $url Internal URL. @return string Distinct routed test URL. */
    public static function _(string $url): string
    {
        self::$calls[] = $url;

        return '/site/' . $url;
    }
}

/** Translation boundary: preserve keys so default titles can be asserted. */
final class VenuePrintLinkText
{
    /** @param string $key Language key. @return string Untranslated test key. */
    public static function _(string $key): string
    {
        return $key;
    }
}
