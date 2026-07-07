<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinksAssetsTest extends TestCase
{
    public function testLinksJavascriptKeepsIconPreviewBehaviour(): void
    {
        $script = $this->read(JEM_TEST_ROOT . '/media/js/jem-links.js');

        foreach (array(
            'jem-link-type-select',
            'jem-link-type-field',
            'jem-link-type-icon-preview',
            'data-icons',
            'JSON.parse',
            'name$=\\"[icon]\\"',
            'normalizeJemLinksInlineHelp',
        ) as $contract) {
            self::assertStringContainsString($contract, $script);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function linksCssProvider(): iterable
    {
        yield 'backend links css' => array(JEM_TEST_ROOT . '/media/css/jem-links.css');
        yield 'responsive links css' => array(JEM_TEST_ROOT . '/media/css/jem-link-responsive.css');
    }

    #[DataProvider('linksCssProvider')]
    public function testLinksCssKeepsResponsiveEditorContracts(string $path): void
    {
        $css = $this->read($path);

        foreach (array(
            '.jem-links-tab',
            'container-type: inline-size',
            '.jem-links-global-options',
            '.subform-repeatable-group',
            '.jem-link-type-icon-preview',
            'max-width: min(18rem, 100%)',
            '@container (max-width: 520px)',
        ) as $contract) {
            self::assertStringContainsString($contract, $css);
        }
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
