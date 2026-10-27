<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousekeepingImageCleanupTest extends TestCase
{
    public function testCleanupProtectsLinkReferencesAndConfirmsEveryFolderAction(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/housekeeping.php');
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/housekeeping/tmpl/default.php');
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');

        self::assertStringContainsString('array_merge((array) $assigned, $this->getLinkImages($type))', $model);
        self::assertStringContainsString("->from(\$this->_db->quoteName('#__jem_links'))", $model);
        self::assertStringContainsString('self::isImageCandidate($relative)', $model);
        self::assertStringContainsString('self::containedExistingFile($basePath, $relative)', $model);
        self::assertStringContainsString('if (!File::delete($fullPath))', $model);
        self::assertSame(3, substr_count($layout, "Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_IMAGES_CONFIRM')"));
        self::assertStringContainsString('COM_JEM_HOUSEKEEPING_CLEANUP_IMAGES_CONFIRM=', $language);
    }
}
