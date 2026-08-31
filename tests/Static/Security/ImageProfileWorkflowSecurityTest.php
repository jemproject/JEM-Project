<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImageProfileWorkflowSecurityTest extends TestCase
{
    public function testAllFourGlobalProfilesAndExactRatiosAreConfigured(): void
    {
        $settings = $this->read('admin/models/forms/settings.xml');

        foreach (array('event_intro', 'event_full', 'venue', 'category') as $profile) {
            self::assertStringContainsString('name="image_' . $profile . '_required"', $settings);
            self::assertStringContainsString('name="image_' . $profile . '_dimension_mandatory"', $settings);
            self::assertStringContainsString('name="image_' . $profile . '_mode"', $settings);
            self::assertStringContainsString('name="image_' . $profile . '_ratio"', $settings);
            self::assertStringContainsString('name="image_' . $profile . '_ratio_mandatory"', $settings);
            self::assertStringContainsString('name="image_' . $profile . '_custom_ratio"', $settings);
            self::assertStringContainsString('showon="image_' . $profile . '_mode!:none"', $settings);
            self::assertStringContainsString(
                'showon="image_' . $profile . '_mode!:none[AND]image_' . $profile . '_ratio:custom"',
                $settings
            );
        }

        foreach (array('4_3', '3_4', '8_5', '5_8', '21_9', '9_21', 'custom') as $ratio) {
            self::assertStringContainsString('<option value="' . $ratio . '">', $settings);
        }
        self::assertStringContainsString('name="image_min_dimension"', $settings);
        self::assertStringContainsString('name="image_max_dimension"', $settings);
        self::assertMatchesRegularExpression(
            '/name="image_max_dimension"[\s\S]*?default="2560"/',
            $settings
        );
        self::assertMatchesRegularExpression('/name="sizelimit"[\s\S]*?default="400"/', $settings);
    }

    public function testImageProfileControlsRetainInlineHelpAndConditionalCustomRatioUi(): void
    {
        $settings = $this->read('admin/models/forms/settings.xml');
        $language = $this->read('admin/language/en-GB/com_jem.ini');
        $layout = $this->read('admin/views/settings/tmpl/default_basicimagehandling.php');

        foreach (array(
            'COM_JEM_IMAGE_REQUIRED_TO_PUBLISH_DESC',
            'COM_JEM_IMAGE_ADJUSTMENT_MODE_DESC',
            'COM_JEM_IMAGE_ASPECT_RATIO_DESC',
            'COM_JEM_IMAGE_CUSTOM_RATIO_DESC',
            'COM_JEM_IMAGE_RESOLUTION_MANDATORY_DESC',
            'COM_JEM_IMAGE_RATIO_MANDATORY_DESC',
        ) as $descriptionKey) {
            self::assertStringContainsString('description="' . $descriptionKey . '"', $settings);
            self::assertStringContainsString($descriptionKey . '="', $language);
        }

        self::assertStringContainsString('data-jem-image-profile=', $layout);
        self::assertStringContainsString('data-jem-image-custom-ratio', $layout);
        self::assertStringContainsString("ratio.value === 'custom'", $layout);
        self::assertStringContainsString('input[id$="_custom_ratio"]', $layout);
        self::assertStringContainsString('width: 8rem;', $layout);
        self::assertStringContainsString('input[id$="_default_dimension"]', $layout);
        self::assertStringContainsString("renderfield(\$profile['prefix'] . '_dimension_mandatory')", $layout);
        self::assertStringContainsString("renderfield(\$profile['prefix'] . '_ratio_mandatory')", $layout);
    }

    public function testPublicationBoundaryIsEnforcedInFormsStoresAndDirectPublishActions(): void
    {
        $policy = $this->read('site/classes/imagepublicationpolicy.class.php');
        $event = $this->read('admin/tables/event.php');
        $venue = $this->read('admin/tables/venue.php');
        $category = $this->read('admin/tables/category.php');
        $script = $this->read('media/js/image-publication.js');

        self::assertStringContainsString('is_file($path)', $policy);
        self::assertStringContainsString("missingForRecord('event'", $event);
        self::assertStringContainsString("invalidPublishRecords('event'", $event);
        self::assertStringContainsString("missingForRecord('venue'", $venue);
        self::assertStringContainsString("invalidPublishRecords('venue'", $venue);
        self::assertStringContainsString("missingForRecord('category'", $category);
        self::assertStringContainsString("invalidPublishRecords('category'", $category);
        self::assertStringContainsString('$this->_autocreate === true', $event);
        self::assertStringContainsString("window.Joomla.renderMessages", $script);
        self::assertStringContainsString("String(published.value) === '1'", $script);
    }

    public function testImageUploadProfilesAreWhitelistedAndPublishedAtomically(): void
    {
        $controller = $this->read('admin/controllers/imagehandler.php');
        $image = $this->read('site/classes/image.class.php');

        self::assertStringContainsString('profileForTask', $controller);
        self::assertStringContainsString('JemImageProfilePolicy::EVENT_FULL', $controller);
        self::assertStringContainsString('JemImage::uploadProfileImage', $controller);
        self::assertStringContainsString('temporaryImagePath', $image);
        self::assertStringContainsString('replaceNormalisedImage', $image);
        self::assertStringContainsString('validatePreparedImage', $image);
        self::assertStringContainsString('File::move($source, $sourceBackup)', $image);
    }

    public function testHousekeepingIsManualGuardedSelectableAndBatchLimited(): void
    {
        $controller = $this->read('admin/controllers/housekeeping.php');
        $helper = $this->read('site/helpers/helper.php');
        $model = $this->read('admin/models/housekeeping.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/default.php');

        self::assertStringContainsString('JemHelper::requirePostToken()', $controller);
        self::assertStringContainsString('$this->allowHousekeeping()', $controller);
        self::assertStringContainsString("Session::checkToken('post')", $helper);
        self::assertStringContainsString("post->get('image_candidates'", $controller);
        self::assertStringContainsString('IMAGE_NORMALISE_BATCH_LIMIT', $controller);
        self::assertStringContainsString('normaliseImageProfiles($selected)', $controller);
        self::assertStringContainsString('auditImageProfiles', $model);
        self::assertStringContainsString('IMAGE_NORMALISE_BATCH_LIMIT = 25', $model);
        self::assertStringContainsString("preg_match('/^[a-f0-9]{64}$/D'", $model);
        self::assertStringContainsString("hash_hmac('sha256'", $model);
        self::assertStringContainsString('isImageNormalisationCandidate', $model);
        self::assertStringContainsString("'conflict' => false", $model);
        self::assertStringContainsString("(int) \$analysis['frames'] > 1", $model);
        self::assertStringContainsString('name="image_candidates[]"', $layout);
        self::assertStringContainsString('jem-image-candidate-checkbox', $layout);
        self::assertStringContainsString('JEM path', $this->read('admin/language/en-GB/com_jem.ini'));
        self::assertStringContainsString('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_CONFIRM', $layout);
    }

    public function testUpgradeDefaultsAndPackageValidationCoverTheFeature(): void
    {
        foreach (array('admin/sql/install.mysql.utf8.sql', 'admin/sql/updates/mysql/5.1.0.sql') as $path) {
            $source = $this->read($path);
            self::assertStringContainsString('image_min_dimension', $source);
            self::assertStringContainsString("'image_max_dimension', '2560'", $source);
            self::assertStringContainsString("'sizelimit', '400'", $source);
            self::assertStringContainsString('image_event_intro_required', $source);
            self::assertStringContainsString('image_event_intro_default_dimension', $source);
            self::assertStringContainsString('image_event_intro_dimension_mandatory', $source);
            self::assertStringContainsString('image_event_intro_ratio_mandatory', $source);
            self::assertStringContainsString('image_event_full_custom_ratio', $source);
            self::assertStringContainsString('image_category_ratio', $source);
            self::assertStringContainsString('image_category_default_dimension', $source);
            self::assertStringContainsString('image_category_dimension_mandatory', $source);
            self::assertStringContainsString('image_category_ratio_mandatory', $source);
        }

        $installer = $this->read('script.php');
        self::assertStringContainsString('image_min_dimension', $installer);
        self::assertStringContainsString("'image_max_dimension' => '2560'", $installer);
        self::assertStringContainsString("'sizelimit' => '400'", $installer);
        self::assertStringContainsString('image_event_intro_required', $installer);
        self::assertStringContainsString('image_event_intro_default_dimension', $installer);
        self::assertStringContainsString('image_event_intro_dimension_mandatory', $installer);
        self::assertStringContainsString('image_event_intro_ratio_mandatory', $installer);
        self::assertStringContainsString('image_event_full_custom_ratio', $installer);
        self::assertStringContainsString('image_category_ratio', $installer);
        self::assertStringContainsString('image_category_default_dimension', $installer);
        self::assertStringContainsString('image_category_dimension_mandatory', $installer);
        self::assertStringContainsString('image_category_ratio_mandatory', $installer);

        $builder = $this->read('scripts/build-packages.php');
        self::assertStringContainsString("'site/classes/imageprofilepolicy.class.php'", $builder);
        self::assertStringContainsString("'site/classes/imagepublicationpolicy.class.php'", $builder);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
    }
}
