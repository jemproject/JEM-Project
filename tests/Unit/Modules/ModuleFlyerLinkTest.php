<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Verify real module helpers and layouts, with Joomla data and image services stubbed. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModuleFlyerLinkTest extends TestCase
{
    /** Load isolated presentation dependencies without an installed Joomla site. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/title_rendering_stubs.php';
        Factory::$application = new class {
            /** Return the fixture timezone used by Jubilee's local-date calculation. */
            public function get(string $key, mixed $default = null): mixed
            {
                return $key === 'offset' ? 'UTC' : $default;
            }

            /** Return a style sink for the real layouts. */
            public function getDocument(): object
            {
                return new class {
                    /** Return a web-asset sink; CSS is outside this link regression. */
                    public function getWebAssetManager(): object
                    {
                        return new class {
                            /** Accept layout CSS without output or asset registration. */
                            public function addInlineStyle(string $css): void
                            {
                            }
                        };
                    }
                };
            }
        };
    }

    /**
     * Exercise destination, title, access and missing-image combinations in each layout.
     *
     * @param string $module Module name suffix.
     * @param string $layout Actual PHP layout name.
     * @return void
     */
    #[DataProvider('layoutProvider')]
    public function testFlyerDestinationIsIndependentOfTheTitle(string $module, string $layout): void
    {
        require_once JEM_TEST_ROOT . '/modules/mod_jem_' . $module . '/helper.php';
        JemHelper::$eventRouteLayoutResolver = static function ($route, $params): string {
            return $route . '?layout=' . $params->get('event_link_event_layout')
                . '&amp;venue_layout=' . $params->get('event_link_venue_layout');
        };
        $eventUrl = 'event/42:demo-event?layout=compact&venue_layout=details';
        $imagePath = 'images/event & "poster".jpg';

        foreach ([0, 1, 2, 3, 99] as $mode) {
            foreach ([0, 1] as $titleLink) {
                foreach ([false, true] as $access) {
                    foreach ([false, true] as $hasImage) {
                        $label = "$module/$layout mode=$mode title=$titleLink access=" . (int) $access
                            . ' image=' . (int) $hasImage;
                        $params = new TitleRenderingParams([
                            'type' => 2, 'show_status_indicators' => 0, 'linkvenue' => 0,
                            'flyer_link_type' => $mode, 'linkevent' => $titleLink,
                            'moduleclass_sfx' => '', 'showcalendar' => 0, 'showdesc' => 0,
                            'showvenue' => 0, 'showcategory' => 0, 'showtype' => 0,
                            'event_link_event_layout' => 'compact', 'event_link_venue_layout' => 'details',
                        ]);
                        BaseDatabaseModel::$items = [$this->event($access)];
                        JemImage::$moduleImage = $hasImage ? [
                            'thumb' => 'images/thumb.jpg', 'original' => $imagePath,
                            'display' => 'images/thumb.jpg', 'display_style' => '',
                            'display_container_style' => '',
                        ] : null;
                        JemOutput::$lightboxCalls = 0;
                        $helper = 'ModJem' . ucfirst($module) . 'Helper';
                        $list = $helper::getList($params);
                        self::assertCount(1, $list, $label);
                        $expectedTitle = $access && $titleLink ? $eventUrl : '';
                        self::assertSame($expectedTitle, html_entity_decode($list[0]->eventlink), $label);

                        $moduleObject = (object) ['id' => 1];
                        $html = $this->render($module, $layout, $params, $list, $moduleObject);
                        $dom = new DOMDocument();
                        $previous = libxml_use_internal_errors(true);
                        try {
                            $dom->loadHTML('<meta charset="UTF-8">' . $html);
                        } finally {
                            libxml_clear_errors();
                            libxml_use_internal_errors($previous);
                        }
                        $xpath = new DOMXPath($dom);
                        self::assertSame(0, $xpath->query('//a[not(@href) or @href=""]')->length, $label);
                        $anchors = $xpath->query('//a[descendant::img or descendant::span[contains(@class,"placeholder")]]');
                        $isCard = in_array($layout, ['cards', 'cards-places'], true);
                        $expectedImage = null;
                        if (($mode === 0 || $mode === 1) && $hasImage) {
                            $expectedImage = '/' . $imagePath;
                        } elseif ($mode === 2 && $access && ($hasImage || $isCard)) {
                            $expectedImage = $eventUrl;
                        }
                        self::assertSame($expectedImage === null ? 0 : 1, $anchors->length, $label);
                        if ($expectedImage !== null) {
                            self::assertSame($expectedImage, $anchors->item(0)->getAttribute('href'), $label);
                            self::assertSame($mode === 1, $anchors->item(0)->getAttribute('rel') === 'lightbox', $label);
                        }
                        self::assertSame($mode === 1 ? 1 : 0, JemOutput::$lightboxCalls, $label);
                        $titleAnchors = $xpath->query('//h2/a | //h3/a');
                        self::assertSame($expectedTitle === '' ? 0 : 1, $titleAnchors->length, $label);
                        if ($expectedTitle !== '') {
                            self::assertSame($eventUrl, $titleAnchors->item(0)->getAttribute('href'), $label);
                        }
                        self::assertSame($hasImage ? 1 : 0, $xpath->query('//img')->length, $label);
                    }
                }
            }
        }
    }

    /**
     * Keep event layout controls available when only the image links to the event.
     *
     * @param string $module Module suffix.
     * @return void
     */
    #[DataProvider('moduleProvider')]
    public function testImageOnlyLinksCanSelectTheEventLayout(string $module): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/modules/mod_jem_' . $module . '/mod_jem_' . $module . '.xml');
        self::assertNotFalse($xml);
        foreach (['event_link_event_layout', 'event_link_venue_layout'] as $name) {
            $fields = $xml->xpath('//field[@name="' . $name . '"]');
            self::assertCount(1, $fields);
            self::assertSame('linkevent:1[OR]flyer_link_type:2', (string) $fields[0]['showon']);
        }
    }

    /** @return iterable<string, array{string, string}> Affected shipped layouts. */
    public static function layoutProvider(): iterable
    {
        foreach (['default', 'responsive', 'table-advanced', 'cards', 'cards-places'] as $layout) {
            yield 'banner-' . $layout => ['banner', $layout];
        }
        foreach (['default', 'responsive'] as $layout) {
            yield 'jubilee-' . $layout => ['jubilee', $layout];
        }
    }

    /** @return iterable<string, array{string}> Modules exposing independent flyer modes. */
    public static function moduleProvider(): iterable
    {
        yield 'banner' => ['banner'];
        yield 'jubilee' => ['jubilee'];
    }

    /**
     * Supply one model result; ID zero bypasses unrelated registration-total SQL.
     *
     * @param bool $access Whether the model grants event access to this visitor.
     * @return object Model row with a valid event route slug.
     */
    private function event(bool $access): object
    {
        $event = (object) [
            'id' => 0, 'title' => 'Demo event', 'slug' => '42:demo-event', 'venueslug' => '',
            'dates' => null, 'enddates' => null, 'times' => null, 'endtimes' => null,
            'categories' => [], 'locimage' => '', 'venue' => '', 'featured' => 0,
            'introtext' => '', 'fulltext' => '', 'locid' => 0, 'l_alias' => '',
            'user_has_access_event' => $access, 'user_has_access_category' => true,
            'user_has_access_venue' => true,
        ];
        for ($index = 1; $index <= 10; ++$index) {
            $event->{'custom' . $index} = '';
        }
        return $event;
    }

    /**
     * Render the unmodified layout entry point with helper-produced item data.
     *
     * @param string $suffix Module suffix.
     * @param string $layout Layout name.
     * @param object $params Module parameters.
     * @param array $list Items produced by the actual helper.
     * @param object $module Module instance metadata.
     * @return string Rendered HTML.
     */
    private function render(string $suffix, string $layout, object $params, array $list, object $module): string
    {
        ob_start();
        try {
            include JEM_TEST_ROOT . '/modules/mod_jem_' . $suffix . '/tmpl/' . $layout . '.php';
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
