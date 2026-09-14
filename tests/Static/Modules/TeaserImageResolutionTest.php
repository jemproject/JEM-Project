<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TeaserImageResolutionTest extends TestCase
{
    public function testManagedJemPathsResolveAndRegenerateTheirThumbnail(): void
    {
        $image = $this->read('site/classes/image.class.php');

        self::assertStringContainsString("\$managedPrefix = 'images/jem/'.\$folder.'/';", $image);
        self::assertStringContainsString("\$managedThumbPrefix = \$managedPrefix.'small/';", $image);
        self::assertStringContainsString('$isManagedSiteImagePath = true;', $image);
        self::assertStringContainsString("\$img_thumb = \$managedThumbPrefix.substr(\$img_orig, strlen(\$managedPrefix));", $image);
        self::assertStringContainsString("(!\$isSiteImagePath || \$isManagedSiteImagePath)", $image);
    }

    public function testExternalSiteImagesFallBackWithoutWritingAThumbnail(): void
    {
        $image = $this->read('site/classes/image.class.php');
        $output = $this->read('site/classes/output.class.php');

        self::assertStringContainsString("\$img_thumb = \$isSiteImagePath ? \$img_orig", $image);
        self::assertStringContainsString("\$dimage['thumb_is_original'] = \$img_thumb === \$img_orig;", $image);
        self::assertStringContainsString("\$dimage['thumbwidth'] = \$dimage['width'];", $image);
        self::assertStringContainsString("\$image['thumbwidth'] ?? \$thumbInfo[0]", $output);
        self::assertStringContainsString("is_file(JPATH_SITE . '/' . \$thumbPath)", $output);
        self::assertStringContainsString("width=\"'.\$thumbwidth.'\" height=\"'.\$thumbheight.'\"", $output);
    }

    public function testTeaserLimitsOriginalFallbackImages(): void
    {
        $helper = $this->read('modules/mod_jem_teaser/helper.php');

        self::assertStringContainsString('eventimagethumbfallback', $helper);
        self::assertStringContainsString('eventimagecontainerstyle', $helper);
        self::assertStringContainsString('venueimagethumbfallback', $helper);

        foreach (array('default.php', 'responsive.php') as $layout) {
            $template = $this->read('modules/mod_jem_teaser/tmpl/' . $layout);

            self::assertStringContainsString('eventImageStyle', $template, $layout);
            self::assertStringContainsString('eventimagecontainerstyle', $template, $layout);
            self::assertStringContainsString('venueImageStyle', $template, $layout);
            self::assertStringContainsString('max-width:100%;height:auto', $template, $layout);
            self::assertStringContainsString('eventimagewidth', $template, $layout);
            self::assertStringContainsString('venueimagewidth', $template, $layout);
        }

        $defaultCss = $this->read('modules/mod_jem_teaser/tmpl/default.css');
        self::assertStringContainsString('.teaser-jem > div:first-child', $defaultCss);
        self::assertStringContainsString('align-items: flex-start;', $defaultCss);
        self::assertStringContainsString('display: flex;', $defaultCss);

        $responsiveCss = $this->read('modules/mod_jem_teaser/tmpl/responsive.css');
        self::assertStringContainsString('align-items: flex-start;', $responsiveCss);
        self::assertStringContainsString('flex: 0 0 100%;', $responsiveCss);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
    }
}
