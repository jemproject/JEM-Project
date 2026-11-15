<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DayTimelineResponsiveLayoutTest extends TestCase
{
    public function testPhoneLayoutUsesTheAvailableWidth(): void
    {
        foreach (array(
            'site/views/day/tmpl/timeline.php',
            'site/views/day/tmpl/responsive/timeline.php',
        ) as $relativePath) {
            $template = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertStringContainsString('@media (max-width: 720px)', $template, $relativePath);
            self::assertStringContainsString('flex-wrap: wrap;', $template, $relativePath);
            self::assertStringContainsString('flex: 1 0 100%;', $template, $relativePath);
            self::assertStringContainsString(
                '.jem-day-timeline-right .jem-day-timeline-row,',
                $template,
                $relativePath
            );
            self::assertStringContainsString(
                'grid-template-columns: 4rem 1.5rem minmax(0, 1fr);',
                $template,
                $relativePath
            );
            self::assertStringContainsString('left: 4.75rem;', $template, $relativePath);
            self::assertStringContainsString('overflow-wrap: anywhere;', $template, $relativePath);
        }
    }

    public function testDefaultAndResponsiveTemplatesKeepTheSameLayoutRules(): void
    {
        $default = file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/timeline.php');
        $responsive = file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/responsive/timeline.php');
        // Installation-root includes also work when Joomla loads a template override.
        foreach ([$default, $responsive] as $template) {
            self::assertStringContainsString("JPATH_SITE . '/components/com_jem/helpers/calendardaylink.php'", $template);
        }
        self::assertSame(str_replace("\r\n", "\n", $default), str_replace("\r\n", "\n", $responsive));
    }
}
