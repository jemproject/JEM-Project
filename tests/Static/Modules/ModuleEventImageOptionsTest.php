<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleEventImageOptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function moduleProvider(): iterable
    {
        yield 'teaser' => array('mod_jem_teaser', 'thumbnail');
        yield 'wide' => array('mod_jem_wide', 'thumbnail');
        yield 'banner' => array('mod_jem_banner', 'original_limited');
        yield 'jubilee' => array('mod_jem_jubilee', 'original_limited');
    }

    #[DataProvider('moduleProvider')]
    public function testManifestsExposeSafeCompatibleImageOptions(string $module, string $displayDefault): void
    {
        $manifestPath = JEM_TEST_ROOT . '/modules/' . $module . '/' . $module . '.xml';
        $xml = simplexml_load_file($manifestPath);

        self::assertNotFalse($xml);
        self::assertSame('intro', $this->fieldAttribute($xml, 'event_image_source', 'default'));
        self::assertSame($displayDefault, $this->fieldAttribute($xml, 'event_image_display', 'default'));

        foreach (array('event_image_options_start', 'event_image_options_end') as $fieldName) {
            self::assertSame('spacer', $this->fieldAttribute($xml, $fieldName, 'type'));
            self::assertSame('true', $this->fieldAttribute($xml, $fieldName, 'hr'));
        }

        $imageFields = array(
            'mod_jem_teaser' => array('showimageevent', 'event_image_source', 'event_image_display', 'event_image_max_width', 'event_image_max_height', 'showimagevenue', 'use_modal'),
            'mod_jem_wide' => array('event_image_source', 'event_image_display', 'event_image_max_width', 'event_image_max_height', 'use_modal'),
            'mod_jem_banner' => array('imageratio', 'showflyer', 'event_image_source', 'event_image_display', 'event_image_max_width', 'event_image_max_height', 'flyer_link_type'),
            'mod_jem_jubilee' => array('showflyer', 'event_image_source', 'event_image_display', 'event_image_max_width', 'event_image_max_height', 'flyer_link_type'),
        );
        $fieldOrder = array_merge(
            array('event_image_options_start'),
            $imageFields[$module],
            array('event_image_options_end')
        );
        $manifest = $this->read('modules/' . $module . '/' . $module . '.xml');
        $positions = array();
        foreach ($fieldOrder as $fieldName) {
            $position = strpos($manifest, 'name="' . $fieldName . '"');
            self::assertNotFalse($position, $fieldName);
            $positions[] = $position;
        }
        $sortedPositions = $positions;
        sort($sortedPositions);
        self::assertSame($sortedPositions, $positions);

        foreach (array('event_image_max_width', 'event_image_max_height') as $fieldName) {
            self::assertSame('number', $this->fieldAttribute($xml, $fieldName, 'type'));
            self::assertSame('800', $this->fieldAttribute($xml, $fieldName, 'default'));
            self::assertSame('1', $this->fieldAttribute($xml, $fieldName, 'min'));
            self::assertSame('4096', $this->fieldAttribute($xml, $fieldName, 'max'));
            self::assertStringContainsString(
                'event_image_display:original_limited',
                $this->fieldAttribute($xml, $fieldName, 'showon')
            );
        }

        $language = $this->read('modules/' . $module . '/language/en-GB/' . $module . '.ini');
        $prefix = strtoupper($module) . '_EVENT_IMAGE_';
        foreach (array('SOURCE', 'DISPLAY', 'MAX_WIDTH', 'MAX_HEIGHT') as $suffix) {
            self::assertStringContainsString($prefix . $suffix . '="', $language);
        }
    }

    public function testBannerReplacesTheLegacyWidthFieldWithoutLosingRuntimeCompatibility(): void
    {
        $manifest = $this->read('modules/mod_jem_banner/mod_jem_banner.xml');
        $layouts = array('default.php', 'responsive.php', 'cards.php', 'cards-places.php', 'table-advanced.php');

        self::assertStringNotContainsString('name="imagewidthmax"', $manifest);
        foreach ($layouts as $layout) {
            self::assertStringContainsString(
                "get('imagewidthmax', 0)",
                $this->read('modules/mod_jem_banner/tmpl/' . $layout),
                $layout
            );
        }
    }

    public function testSharedPolicySelectsFullWithIntroFallbackAndBoundsOriginals(): void
    {
        $image = $this->read('site/classes/image.class.php');
        $eventsList = $this->read('site/models/eventslist.php');

        self::assertStringContainsString('function getModuleEventImageData(', $image);
        self::assertStringContainsString("\$source === 'full' && !empty(\$event->fullimage)", $image);
        self::assertStringContainsString(": (string) (\$event->datimage ?? '')", $image);
        self::assertStringContainsString("array('thumbnail', 'original_limited')", $image);
        self::assertStringContainsString("min(\$maxWidth, 4096)", $image);
        self::assertStringContainsString("min(\$maxHeight, 4096)", $image);
        self::assertStringContainsString("max-width:min(100%,", $image);
        self::assertStringContainsString("\$data['display_container_style'] = 'max-width:min(100%,", $image);
        self::assertStringContainsString("\$configuredDisplay !== null", $image);
        self::assertStringContainsString('a.datimage,a.fullimage', $eventsList);
    }

    #[DataProvider('moduleProvider')]
    public function testHelpersPreserveModuleDefaultsAndExposeDisplayData(string $module, string $displayDefault): void
    {
        $helper = $this->read('modules/' . $module . '/helper.php');

        self::assertStringContainsString(
            "JemImage::getModuleEventImageData(\$row, \$params, '" . $displayDefault . "')",
            $helper
        );
        self::assertStringContainsString('eventimagedisplay', $helper);
        self::assertStringContainsString('eventimagestyle', $helper);
        self::assertStringContainsString('eventimagecontainerstyle', $helper);
    }

    public function testEveryBundledEventImageLayoutUsesTheSelectedDisplayImage(): void
    {
        $layouts = array(
            'modules/mod_jem_teaser/tmpl/default.php',
            'modules/mod_jem_teaser/tmpl/responsive.php',
            'modules/mod_jem_wide/tmpl/default.php',
            'modules/mod_jem_wide/tmpl/default_jem_eventslist.php',
            'modules/mod_jem_banner/tmpl/default.php',
            'modules/mod_jem_banner/tmpl/responsive.php',
            'modules/mod_jem_banner/tmpl/cards.php',
            'modules/mod_jem_banner/tmpl/cards-places.php',
            'modules/mod_jem_banner/tmpl/table-advanced.php',
            'modules/mod_jem_jubilee/tmpl/default.php',
            'modules/mod_jem_jubilee/tmpl/responsive.php',
        );

        foreach ($layouts as $layout) {
            $template = $this->read($layout);
            self::assertStringContainsString('eventimagedisplay', $template, $layout);
            self::assertStringContainsString('eventimagestyle', $template, $layout);
            self::assertStringContainsString('eventimagecontainerstyle', $template, $layout);
        }
    }

    private function fieldAttribute(SimpleXMLElement $xml, string $fieldName, string $attribute): string
    {
        $fields = $xml->xpath('//field[@name="' . $fieldName . '"]');
        self::assertIsArray($fields);
        self::assertCount(1, $fields, $fieldName);

        return (string) $fields[0][$attribute];
    }

    private function read(string $path): string
    {
        $fullPath = JEM_TEST_ROOT . '/' . $path;
        self::assertFileExists($fullPath);

        return (string) file_get_contents($fullPath);
    }
}
