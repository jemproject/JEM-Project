<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImageStoragePolicyContractsTest extends TestCase
{
    public function testImagesUseTheirOwnSettingsTab(): void
    {
        $layout = $this->read('admin/views/settings/tmpl/default.php');
        $imageSettings = $this->read('admin/views/settings/tmpl/default_basicimagehandling.php');

        self::assertStringContainsString("'image-settings', Text::_('COM_JEM_IMAGE_SETTINGS')", $layout);
        self::assertSame(1, substr_count($layout, "loadTemplate('basicimagehandling')"));
        self::assertLessThan(
            strpos($layout, "'event-settings'"),
            strpos($layout, "'image-settings'")
        );
        self::assertStringContainsString("'event_image_subfolder'", $imageSettings);
        self::assertStringContainsString("'venue_image_subfolder'", $imageSettings);
        self::assertStringContainsString("'category_image_subfolder'", $imageSettings);
        self::assertStringContainsString(
            'jem-image-settings-column jem-image-settings-left',
            $imageSettings
        );
        self::assertStringContainsString(
            'jem-image-settings-column jem-image-settings-right',
            $imageSettings
        );
        self::assertMatchesRegularExpression(
            '/jem-image-settings-left[\s\S]+jem-image-settings-general[\s\S]+foreach \(\$storageProfiles/',
            $imageSettings
        );
        self::assertMatchesRegularExpression(
            '/jem-image-settings-right[\s\S]+foreach \(\$profiles/',
            $imageSettings
        );
        self::assertStringContainsString(
            '.jem-image-settings-storage input[id$="_pattern"]',
            $imageSettings
        );
        self::assertStringNotContainsString('_max_depth', $imageSettings);
        self::assertStringContainsString('data-jem-image-custom-pattern', $imageSettings);
        self::assertStringContainsString("preset.value !== 'custom'", $imageSettings);

        $language = $this->read('admin/language/en-GB/com_jem.ini');
        self::assertSame(3, substr_count($language, 'IMAGE_SUBFOLDER_PRESET="Folder structure"'));
        self::assertSame(3, substr_count($language, 'IMAGE_SUBFOLDER_PATTERN="Custom pattern"'));
        self::assertStringContainsString(
            'COM_JEM_EVENT_IMAGE_SUBFOLDER_PRESET_ROOT="Root events image folder"',
            $language
        );
        self::assertStringContainsString(
            'COM_JEM_VENUE_IMAGE_SUBFOLDER_PRESET_ROOT="Root venues image folder"',
            $language
        );
        self::assertStringContainsString(
            'COM_JEM_CATEGORY_IMAGE_SUBFOLDER_PRESET_ROOT="Root categories image folder"',
            $language
        );
    }

    public function testSettingsDefineOnlyManagedImageFileObjects(): void
    {
        $settings = $this->read('admin/models/forms/settings.xml');

        foreach (array('event', 'venue', 'category') as $object) {
            foreach (array('enabled', 'preset', 'pattern') as $field) {
                self::assertStringContainsString(
                    'name="' . $object . '_image_subfolder_' . $field . '"',
                    $settings
                );
            }

            self::assertStringNotContainsString(
                'name="' . $object . '_image_subfolder_max_depth"',
                $settings
            );
        }

        self::assertStringNotContainsString('type_image_subfolder_', $settings);
        self::assertStringNotContainsString('link_image_subfolder_', $settings);
        self::assertStringContainsString(
            '<option value="root">COM_JEM_EVENT_IMAGE_SUBFOLDER_PRESET_ROOT</option>',
            $settings
        );
        self::assertStringContainsString(
            '<option value="root">COM_JEM_VENUE_IMAGE_SUBFOLDER_PRESET_ROOT</option>',
            $settings
        );
        self::assertStringContainsString(
            '<option value="root">COM_JEM_CATEGORY_IMAGE_SUBFOLDER_PRESET_ROOT</option>',
            $settings
        );

        foreach (array('event', 'venue', 'category') as $object) {
            self::assertMatchesRegularExpression(
                '/name="' . $object . '_image_subfolder_enabled"[\s\S]+?default="0"/',
                $settings,
                $object
            );
            self::assertMatchesRegularExpression(
                '/name="' . $object . '_image_subfolder_preset"[\s\S]+?default="root"/',
                $settings,
                $object
            );
        }

        $settingsModel = $this->read('admin/models/settings.php');
        $folderPolicy = $this->read('site/classes/imagefolderpolicy.class.php');

        self::assertStringContainsString('public const MAX_DEPTH = 8;', $folderPolicy);
        self::assertStringContainsString('validateImageFolderPatterns($data)', $settingsModel);
        self::assertStringContainsString(
            'JemImageFolderPolicy::isWithinMaximumDepth($pattern)',
            $settingsModel
        );

        foreach (array('event', 'venue', 'category') as $object) {
            $resolver = $this->read('site/classes/' . $object . 'imagepath.class.php');
            self::assertStringContainsString(
                "get('{$object}_image_subfolder_enabled', 0)",
                $resolver,
                $object
            );
            self::assertStringContainsString(
                "get('{$object}_image_subfolder_preset', 'root')",
                $resolver,
                $object
            );
            self::assertStringNotContainsString("{$object}_image_subfolder_max_depth", $resolver, $object);
            self::assertStringContainsString('JemImageFolderPolicy::resolvedFolderOrRoot', $resolver, $object);
        }
    }

    public function testCategorySchemaAndConsumersPersistRelativeFolders(): void
    {
        $install = $this->read('admin/sql/install.mysql.utf8.sql');
        $upgrade = $this->read('admin/sql/updates/mysql/5.1.0.sql');
        $installer = $this->read('script.php');
        $model = $this->read('admin/models/category.php');
        $image = $this->read('site/classes/image.class.php');
        $housekeeping = $this->read('admin/models/housekeeping.php');

        self::assertMatchesRegularExpression(
            '/CREATE TABLE IF NOT EXISTS `#__jem_categories`[\s\S]+?`image_path` varchar\(255\) NOT NULL DEFAULT \'\'/i',
            $install
        );
        self::assertStringContainsString(
            'ALTER TABLE `#__jem_categories` ADD COLUMN `image_path`',
            $upgrade
        );
        self::assertStringContainsString("'image_path' => \"VARCHAR(255) NOT NULL DEFAULT '' AFTER `image`\"", $installer);
        self::assertStringContainsString('syncCategoryImageStorage(', $model);
        self::assertStringContainsString('JemCategoryImagePath::configuredFolderFromCategory', $model);
        self::assertStringContainsString("\$type === 'category'", $image);
        self::assertStringContainsString('JemCategoryImagePath::absoluteImageFolder', $housekeeping);
    }

    public function testPathsStayInsideFixedObjectRoots(): void
    {
        $event = $this->read('site/classes/eventimagepath.class.php');
        $venue = $this->read('site/classes/venueimagepath.class.php');
        $category = $this->read('site/classes/categoryimagepath.class.php');

        self::assertStringContainsString("public const BASE = 'images/jem/events'", $event);
        self::assertStringContainsString("public const BASE = 'images/jem/venues'", $venue);
        self::assertStringContainsString("public const BASE = 'images/jem/categories'", $category);
        self::assertStringContainsString("return '{venue_id}/venue';", $venue);
        self::assertStringContainsString('configuredFolderFromCategory', $category);
        self::assertStringContainsString("\$segment === '..'", $venue);
        self::assertStringContainsString("\$segment === '..'", $category);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
    }
}
