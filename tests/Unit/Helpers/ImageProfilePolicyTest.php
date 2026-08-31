<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/imageprofilepolicy.class.php';

final class ImageProfilePolicyTest extends TestCase
{
    public function testConfiguredDefaultUses2kUploadsWithoutReducingTheLegacyDisplayBoundary(): void
    {
        self::assertSame(2560, JemImageProfilePolicy::maxDimension(array()));
        self::assertSame(3840, JemImageProfilePolicy::displayMaxDimension(array()));
        self::assertSame(400, JemImageProfilePolicy::DEFAULT_MAX_FILE_SIZE_KB);
    }

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

    public function testPerUploadResolutionIsClampedToTheProfileAndGlobalSettings(): void
    {
        $settings = array(
            'image_max_dimension' => 3840,
            'image_min_dimension' => 64,
            'image_event_intro_mode' => 'crop',
            'image_event_intro_ratio' => '16_9',
        );

        self::assertSame(
            128,
            JemImageProfilePolicy::minimumOutputMaxDimension(
                $settings,
                JemImageProfilePolicy::EVENT_INTRO
            )
        );
        self::assertSame(
            128,
            JemImageProfilePolicy::requestedMaxDimension(
                $settings,
                JemImageProfilePolicy::EVENT_INTRO,
                64
            )
        );
        self::assertSame(
            1200,
            JemImageProfilePolicy::requestedMaxDimension(
                $settings,
                JemImageProfilePolicy::EVENT_INTRO,
                1200
            )
        );
        self::assertSame(
            3840,
            JemImageProfilePolicy::requestedMaxDimension(
                $settings,
                JemImageProfilePolicy::EVENT_INTRO,
                8000
            )
        );
        self::assertSame(
            3840,
            JemImageProfilePolicy::requestedMaxDimension(
                $settings,
                JemImageProfilePolicy::EVENT_INTRO,
                'invalid'
            )
        );
        $settings['image_event_intro_mode'] = 'none';
        self::assertSame(
            114,
            JemImageProfilePolicy::requestedMaxDimension(
                $settings,
                JemImageProfilePolicy::EVENT_INTRO,
                64,
                1920,
                1080
            )
        );
    }

    public function testPerUploadRatioUsesAValidatedOverrideWithoutChangingSettings(): void
    {
        $settings = array(
            'image_max_dimension' => 3840,
            'image_min_dimension' => 64,
            'image_venue_mode' => 'crop',
            'image_venue_ratio' => '4_3',
        );

        $original = JemImageProfilePolicy::resolveUpload(
            $settings,
            JemImageProfilePolicy::VENUE,
            'original'
        );
        $configured = JemImageProfilePolicy::resolveUpload(
            $settings,
            JemImageProfilePolicy::VENUE,
            '4_3'
        );
        $unconfigured = JemImageProfilePolicy::resolveUpload(
            $settings,
            JemImageProfilePolicy::VENUE,
            '9_16'
        );

        self::assertSame('none', $original['mode']);
        self::assertSame(array(1, 1), array($original['ratio_width'], $original['ratio_height']));
        self::assertSame('crop', $configured['mode']);
        self::assertSame(array(4, 3), array($configured['ratio_width'], $configured['ratio_height']));
        self::assertSame('crop', $unconfigured['mode']);
        self::assertSame('9_16', $unconfigured['preset']);
        self::assertSame(array(9, 16), array($unconfigured['ratio_width'], $unconfigured['ratio_height']));
        self::assertSame('crop', $settings['image_venue_mode']);
    }

    public function testMandatoryUploadSettingsIgnorePerUploadOverrides(): void
    {
        $settings = array(
            'image_max_dimension' => 3840,
            'image_min_dimension' => 64,
            'image_event_intro_default_dimension' => 1200,
            'image_event_intro_dimension_mandatory' => 1,
            'image_event_intro_mode' => 'crop',
            'image_event_intro_ratio' => '16_9',
            'image_event_intro_ratio_mandatory' => 1,
        );

        self::assertTrue(JemImageProfilePolicy::isDimensionMandatory(
            $settings,
            JemImageProfilePolicy::EVENT_INTRO
        ));
        self::assertTrue(JemImageProfilePolicy::isRatioMandatory(
            $settings,
            JemImageProfilePolicy::EVENT_INTRO
        ));
        self::assertSame(1200, JemImageProfilePolicy::requestedMaxDimension(
            $settings,
            JemImageProfilePolicy::EVENT_INTRO,
            2560,
            0,
            0,
            '9_16'
        ));

        $resolved = JemImageProfilePolicy::resolveUpload(
            $settings,
            JemImageProfilePolicy::EVENT_INTRO,
            '9_16'
        );

        self::assertSame('crop', $resolved['mode']);
        self::assertSame('16_9', $resolved['preset']);
        self::assertSame(array(16, 9), array($resolved['ratio_width'], $resolved['ratio_height']));
    }

    public function testMissingOrDisabledMandatorySettingsKeepCurrentOverrides(): void
    {
        $settings = array(
            'image_max_dimension' => 3840,
            'image_min_dimension' => 64,
            'image_venue_default_dimension' => 1280,
            'image_venue_dimension_mandatory' => 0,
            'image_venue_mode' => 'crop',
            'image_venue_ratio' => '4_3',
            'image_venue_ratio_mandatory' => 0,
        );

        self::assertFalse(JemImageProfilePolicy::isDimensionMandatory(
            $settings,
            JemImageProfilePolicy::VENUE
        ));
        self::assertFalse(JemImageProfilePolicy::isRatioMandatory(
            $settings,
            JemImageProfilePolicy::VENUE
        ));
        self::assertSame(1920, JemImageProfilePolicy::requestedMaxDimension(
            $settings,
            JemImageProfilePolicy::VENUE,
            1920
        ));
        self::assertSame('9_16', JemImageProfilePolicy::resolveUpload(
            $settings,
            JemImageProfilePolicy::VENUE,
            '9_16'
        )['preset']);

        unset($settings['image_venue_dimension_mandatory'], $settings['image_venue_ratio_mandatory']);

        self::assertFalse(JemImageProfilePolicy::isDimensionMandatory(
            $settings,
            JemImageProfilePolicy::VENUE
        ));
        self::assertFalse(JemImageProfilePolicy::isRatioMandatory(
            $settings,
            JemImageProfilePolicy::VENUE
        ));
    }

    public function testPerUploadRatioKeepsConfiguredPaddingAndAdjustsTheMinimum(): void
    {
        $settings = array(
            'image_max_dimension' => 3840,
            'image_min_dimension' => 64,
            'image_category_mode' => 'pad',
            'image_category_ratio' => '21_9',
        );

        $config = JemImageProfilePolicy::resolveUpload(
            $settings,
            JemImageProfilePolicy::CATEGORY,
            '21_9'
        );

        self::assertSame('pad', $config['mode']);
        self::assertSame(array(21, 9), array($config['ratio_width'], $config['ratio_height']));
        self::assertSame(
            168,
            JemImageProfilePolicy::minimumOutputMaxDimension(
                $settings,
                JemImageProfilePolicy::CATEGORY,
                '21_9'
            )
        );
        self::assertSame(
            array('original', '1_1', '4_3', '3_4', '3_2', '2_3', '8_5', '5_8', '16_9', '9_16', '21_9', '9_21'),
            array_keys(JemImageProfilePolicy::uploadRatioOptions($settings, JemImageProfilePolicy::CATEGORY))
        );

        $settings['image_category_mode'] = 'none';
        self::assertSame(
            array('original', '1_1', '4_3', '3_4', '3_2', '2_3', '8_5', '5_8', '16_9', '9_16', '21_9', '9_21'),
            array_keys(JemImageProfilePolicy::uploadRatioOptions($settings, JemImageProfilePolicy::CATEGORY))
        );
        self::assertSame('crop', JemImageProfilePolicy::resolveUpload(
            $settings,
            JemImageProfilePolicy::CATEGORY,
            '16_9'
        )['mode']);
    }

    public function testEachProfileHasAClampedDefaultUploadDimension(): void
    {
        $settings = array(
            'image_max_dimension' => 3840,
            'image_min_dimension' => 64,
            'image_event_intro_mode' => 'none',
            'image_event_intro_default_dimension' => 1200,
            'image_event_full_mode' => 'none',
            'image_event_full_default_dimension' => 8000,
            'image_venue_mode' => 'none',
            'image_venue_default_dimension' => 64,
            'image_category_mode' => 'none',
        );

        self::assertSame(1200, JemImageProfilePolicy::defaultUploadMaxDimension(
            $settings,
            JemImageProfilePolicy::EVENT_INTRO
        ));
        self::assertSame(3840, JemImageProfilePolicy::defaultUploadMaxDimension(
            $settings,
            JemImageProfilePolicy::EVENT_FULL
        ));
        self::assertSame(64, JemImageProfilePolicy::defaultUploadMaxDimension(
            $settings,
            JemImageProfilePolicy::VENUE
        ));
        self::assertSame(800, JemImageProfilePolicy::defaultUploadMaxDimension(
            $settings,
            JemImageProfilePolicy::CATEGORY
        ));
    }
}
