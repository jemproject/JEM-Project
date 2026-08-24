<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/imageprofilepolicy.class.php';

final class ImageProfilePolicyTest extends TestCase
{
    public function testCustomRatiosAreValidatedAndReducedWithoutChangingOrientation(): void
    {
        self::assertSame(array(16, 9), JemImageProfilePolicy::parseRatio(' 1920 : 1080 '));
        self::assertSame(array(4, 3), JemImageProfilePolicy::parseRatio('4:3'));
        self::assertSame(array(3, 4), JemImageProfilePolicy::parseRatio('3:4'));
        self::assertSame(array(35, 3), JemImageProfilePolicy::parseRatio('35:3'));
        self::assertNull(JemImageProfilePolicy::parseRatio('0:3'));
        self::assertNull(JemImageProfilePolicy::parseRatio('10000:3'));
        self::assertSame('16:9', JemImageProfilePolicy::normaliseCustomRatio('1920:1080'));
    }

    public function testRatioMustFitBothConfiguredDimensionBoundaries(): void
    {
        self::assertTrue(JemImageProfilePolicy::ratioFitsBounds(64, 3840, 35, 3));
        self::assertTrue(JemImageProfilePolicy::ratioFitsBounds(64, 3840, 9, 21));
        self::assertFalse(JemImageProfilePolicy::ratioFitsBounds(64, 8192, 9999, 1));
        self::assertFalse(JemImageProfilePolicy::ratioFitsBounds(200, 320, 4, 1));
    }

    public function testCropGeometryProducesAnExactBoundedRatioWithoutUpscaling(): void
    {
        $geometry = JemImageProfilePolicy::geometry(1600, 1000, 1200, 'crop', 16, 9);

        self::assertSame('crop', $geometry['method']);
        self::assertTrue(JemImageProfilePolicy::isExactRatio($geometry['width'], $geometry['height'], 16, 9));
        self::assertLessThanOrEqual(1200, $geometry['width']);
        self::assertLessThanOrEqual(1000, $geometry['height']);
    }

    public function testPadGeometryProducesAnExactCanvasWithinTheMaximum(): void
    {
        $geometry = JemImageProfilePolicy::geometry(800, 1200, 1600, 'pad', 16, 9);

        self::assertSame('pad', $geometry['method']);
        self::assertTrue(JemImageProfilePolicy::isExactRatio($geometry['width'], $geometry['height'], 16, 9));
        self::assertLessThanOrEqual(1600, $geometry['width']);
        self::assertLessThanOrEqual(1600, $geometry['height']);
    }

    public function testNoAdjustmentOnlyReducesImagesAboveTheMaximum(): void
    {
        self::assertSame(
            array('width' => 800, 'height' => 600, 'method' => 'contain', 'changed' => false),
            JemImageProfilePolicy::geometry(800, 600, 3840, 'none', 16, 9)
        );

        $bounded = JemImageProfilePolicy::geometry(6000, 3000, 3840, 'none', 16, 9);
        self::assertSame(array('width' => 3840, 'height' => 1920, 'method' => 'contain', 'changed' => true), $bounded);
    }

    public function testRatioAdjustmentIsCalculatedAfterProportionalMaximumReduction(): void
    {
        self::assertSame(
            array('width' => 640, 'height' => 480, 'method' => 'crop', 'changed' => true),
            JemImageProfilePolicy::geometry(4000, 1000, 1920, 'crop', 4, 3)
        );
        self::assertSame(
            array('width' => 1920, 'height' => 1440, 'method' => 'pad', 'changed' => true),
            JemImageProfilePolicy::geometry(4000, 1000, 1920, 'pad', 4, 3)
        );
    }

    public function testRequiredProfilesAndCompatibilityDisplayLimitUseGlobalSettings(): void
    {
        $settings = array(
            'image_max_dimension' => 1200,
            'image_min_dimension' => 80,
            'image_event_intro_required' => 1,
            'image_event_intro_mode' => 'none',
            'image_event_intro_ratio' => '16_9',
            'image_event_full_mode' => 'none',
            'image_event_full_ratio' => '9_16',
        );

        self::assertSame(1200, JemImageProfilePolicy::maxDimension($settings));
        self::assertSame(80, JemImageProfilePolicy::minDimension($settings));
        self::assertSame(3840, JemImageProfilePolicy::displayMaxDimension($settings));
        self::assertTrue(JemImageProfilePolicy::isRequired($settings, JemImageProfilePolicy::EVENT_INTRO));
        self::assertFalse(JemImageProfilePolicy::isRequired($settings, JemImageProfilePolicy::EVENT_FULL));
        self::assertSame(
            JemImageProfilePolicy::signature($settings, JemImageProfilePolicy::EVENT_INTRO),
            JemImageProfilePolicy::signature($settings, JemImageProfilePolicy::EVENT_FULL)
        );
    }
}
