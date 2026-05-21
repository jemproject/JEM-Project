<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinksCssTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function publicCssProvider(): iterable
    {
        yield 'legacy css' => array(JEM_TEST_ROOT . '/media/css/jem.css');
        yield 'responsive css' => array(JEM_TEST_ROOT . '/media/css/jem-responsive.css');
    }

    #[DataProvider('publicCssProvider')]
    public function testPublicCssContainsAllLinkLayoutClasses(string $path): void
    {
        $css = $this->read($path);

        foreach (array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform') as $layout) {
            self::assertStringContainsString('.jem-event-links.jem-event-links-' . $layout, $css);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function editCssProvider(): iterable
    {
        yield 'backend links css' => array(JEM_TEST_ROOT . '/media/css/jem-links.css');
        yield 'responsive links css' => array(JEM_TEST_ROOT . '/media/css/jem-link-responsive.css');
    }

    #[DataProvider('editCssProvider')]
    public function testEditCssContainsGlobalLinksOptionLayoutRules(string $path): void
    {
        $css = $this->read($path);

        self::assertStringContainsString('.jem-links-tab .jem-links-global-options', $css);
        self::assertStringContainsString('flex-wrap: wrap', $css);
        self::assertStringContainsString('max-width: min(18rem, 100%)', $css);
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
