<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinksViewsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function editLinksTemplateProvider(): iterable
    {
        yield 'admin event links' => array(JEM_TEST_ROOT . '/admin/views/event/tmpl/edit_links.php');
        yield 'site event links' => array(JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit_links.php');
        yield 'site responsive event links' => array(JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit_links.php');
    }

    #[DataProvider('editLinksTemplateProvider')]
    public function testEditLinksTemplatesRenderGlobalOptions(string $path): void
    {
        $template = $this->read($path);

        self::assertStringContainsString('jem-links-global-options', $template);
        self::assertStringContainsString("renderField('links_layout', 'attribs')", $template);
        self::assertStringContainsString("renderField('links_order', 'attribs')", $template);
        self::assertStringContainsString("renderField('event_links')", $template);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function editLinksAssetTemplateProvider(): iterable
    {
        yield 'admin event links' => array(JEM_TEST_ROOT . '/admin/views/event/tmpl/edit_links.php', 'media/com_jem/css/jem-links.css');
        yield 'site event links' => array(JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit_links.php', 'media/com_jem/css/jem-links.css');
        yield 'site responsive event links' => array(JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit_links.php', 'media/com_jem/css/jem-link-responsive.css');
    }

    #[DataProvider('editLinksAssetTemplateProvider')]
    public function testEditLinksTemplatesLoadAssets(string $path, string $expectedCss): void
    {
        $template = $this->read($path);

        self::assertStringContainsString($expectedCss, $template);
        self::assertStringContainsString('media/com_jem/js/jem-links.js', $template);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicEventTemplateProvider(): iterable
    {
        yield 'legacy event view' => array(JEM_TEST_ROOT . '/site/views/event/tmpl/default.php');
        yield 'responsive event view' => array(JEM_TEST_ROOT . '/site/views/event/tmpl/responsive/default.php');
    }

    #[DataProvider('publicEventTemplateProvider')]
    public function testPublicEventTemplatesRenderLinksWithLayoutAndOrderClasses(string $path): void
    {
        $template = $this->read($path);

        self::assertStringContainsString('$linksLayout', $template);
        self::assertStringContainsString('$linksOrder', $template);
        self::assertStringContainsString('$orderClass', $template);
        self::assertStringContainsString('jem-event-links jem-event-links-', $template);
    }

    #[DataProvider('publicEventTemplateProvider')]
    public function testPublicEventTemplatesValidateLinksLayoutValues(string $path): void
    {
        $template = $this->read($path);

        foreach (array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform') as $layout) {
            self::assertStringContainsString("'" . $layout . "'", $template);
        }
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
