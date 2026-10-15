<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ToolbarIconPresentationTest extends TestCase
{
    public function testIconFontDefaultsAgreeWithoutAnUpgradeOverride(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $fields = $xml->xpath('//field[@name="useiconfont"]');
        self::assertCount(1, $fields);
        self::assertSame('1', (string) $fields[0]['default']);
        self::assertSame(['1', '0'], array_map(
            static fn ($option): string => (string) $option['value'],
            $fields[0]->xpath('option')
        ));
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        self::assertStringContainsString("('useiconfont', '1')", $install);
        foreach (glob(JEM_TEST_ROOT . '/admin/sql/updates/mysql/*.sql') as $migration) {
            self::assertStringNotContainsString('useiconfont', (string) file_get_contents($migration));
        }
        self::assertStringNotContainsString('useiconfont', (string) file_get_contents(JEM_TEST_ROOT . '/script.php'));
    }

    public function testEventLayoutToggleRemainsAfterTheSharedButtonBar(): void
    {
        foreach (['default.php', 'responsive/default.php'] as $layout) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/event/tmpl/' . $layout);
            $toolbar = strpos($source, 'echo JemOutput::createButtonBar(');
            $toggle = strpos($source, '<a class="jem-layout-toggle"');
            self::assertNotFalse($toolbar);
            self::assertNotFalse($toggle);
            self::assertGreaterThan($toolbar, $toggle);
            self::assertStringContainsString('data-jem-layout-target=', $source);
        }
    }

    public function testBothLayoutsShareScopedOpticalSizing(): void
    {
        $blocks = [];
        foreach (['jem.css', 'jem-responsive.css'] as $file) {
            $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/' . $file);
            $start = strpos($css, '/* Shared visual size for action icons');
            self::assertNotFalse($start);
            $end = strpos($css, "\n}", strpos($css, '/* Retain legacy image mode', $start));
            self::assertNotFalse($end);
            $block = substr($css, $start, $end + 2 - $start);
            $blocks[] = $block;
            self::assertStringContainsString('--jem-toolbar-icon-size: 1.125rem;', $block);
            self::assertStringContainsString('#jem .buttons a > img', $block);
            self::assertStringContainsString('width: auto;', $block);
            self::assertStringContainsString('align-items: center;', $block);
            self::assertStringContainsString('font-size: var(--jem-toolbar-icon-size);', $block);
            self::assertStringContainsString('#jem .buttons a > .jem-mailbutton', $block);
            self::assertStringContainsString('font-size: calc(var(--jem-toolbar-icon-size) * 5 / 4);', $block);
            self::assertStringContainsString('height: calc(var(--jem-toolbar-icon-size) * 15 / 16);', $block);
            self::assertStringNotContainsString('!important', $block);
        }
        self::assertSame($blocks[0], $blocks[1]);
    }

    public function testCopyLinkUsesALinkGlyphWithoutChangingTheCopyContract(): void
    {
        $output = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        self::assertStringContainsString('fa fa-fw fa-lg fa-link jem-sharebutton', $output);
        self::assertStringNotContainsString('fa-share-alt jem-sharebutton', $output);
        self::assertStringContainsString('com_jem/emailButton.svg', $output);
        self::assertStringContainsString('data-jem-share-link=', $output);
        self::assertStringContainsString('data-jem-share-status aria-live="polite"', $output);
        self::assertStringContainsString("Text::_('COM_JEM_COPY_LINK')", $output);
        // Duplicating an event remains a separate action and icon.
        self::assertStringContainsString('fas fa-fw fa-copy jem-copybutton', $output);
    }

    public function testLocalSvgFallbacksHaveTheSameIntrinsicHeight(): void
    {
        foreach (['emailButton.svg', 'shareButton.svg'] as $file) {
            $svg = simplexml_load_file(JEM_TEST_ROOT . '/media/images/' . $file);
            self::assertNotFalse($svg);
            self::assertSame('18', (string) $svg['height']);
            self::assertNotEmpty((string) $svg['viewBox']);
            self::assertSame([], $svg->xpath('//*[local-name()="script" or local-name()="image"]'));
        }
        // Existing template overrides may still reference the original bitmap.
        self::assertFileExists(JEM_TEST_ROOT . '/media/images/emailButton.webp');
    }

    public function testTimelineToggleUsesTheSameSizeVariable(): void
    {
        foreach (['timeline.php', 'responsive/timeline.php'] as $layout) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/' . $layout);
            self::assertStringContainsString(
                'font-size: calc(var(--jem-toolbar-icon-size, 1.125rem) * 8 / 7);',
                $source
            );
        }
    }
}
