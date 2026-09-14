<?php

declare(strict_types=1);

use Joomla\Registry\Registry;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class ModuleStatusRibbonScaleTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();

        if (!class_exists('JemHelper')) {
            require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        }

        require_once JEM_TEST_ROOT . '/site/classes/output.class.php';
    }

    public function testThumbnailUsesTheGlobalScale(): void
    {
        $params = new Registry(array(
            'event_image_display' => 'thumbnail',
            'status_ribbon_scale' => 60,
        ));

        self::assertSame(115, JemOutput::moduleStatusRibbonScale(
            $params,
            (object) array('module_status_ribbon_scale' => 115)
        ));
    }

    public function testOriginalLimitedUsesTheModuleDefaultAndOverride(): void
    {
        $settings = (object) array('module_status_ribbon_scale' => 115);

        self::assertSame(60, JemOutput::moduleStatusRibbonScale(
            new Registry(array('event_image_display' => 'original_limited')),
            $settings
        ));
        self::assertSame(75, JemOutput::moduleStatusRibbonScale(
            new Registry(array(
                'event_image_display' => 'original_limited',
                'status_ribbon_scale' => 75,
            )),
            $settings
        ));
    }

    public function testOriginalLimitedScaleIsNormalized(): void
    {
        $settings = (object) array('module_status_ribbon_scale' => 115);

        self::assertSame(50, JemOutput::moduleStatusRibbonScale(
            new Registry(array(
                'event_image_display' => 'original_limited',
                'status_ribbon_scale' => 10,
            )),
            $settings
        ));
        self::assertSame(200, JemOutput::moduleStatusRibbonScale(
            new Registry(array(
                'event_image_display' => 'original_limited',
                'status_ribbon_scale' => 300,
            )),
            $settings
        ));
        self::assertSame(60, JemOutput::moduleStatusRibbonScale(
            new Registry(array(
                'event_image_display' => 'original_limited',
                'status_ribbon_scale' => 'invalid',
            )),
            $settings
        ));
    }
}
