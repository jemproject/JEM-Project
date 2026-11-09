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
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/housekeeping.php');
        $review = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/housekeeping/tmpl/files.php');
        foreach (['cleaneventimg', 'cleanvenueimg', 'cleancategoryimg'] as $task) {
            self::assertStringContainsString('housekeeping.' . $task, $layout);
        }
        self::assertStringContainsString('view=housekeeping&tool=files&area=', $controller);
        self::assertStringContainsString("consumeActionNonce('housekeeping.deleteUnusedFiles'", $controller);
        self::assertStringContainsString("getInt('confirm_references', 0) === 1", $controller);
        self::assertStringContainsString('if (!window.confirm(message))', $review);
        self::assertStringContainsString('COM_JEM_HOUSEKEEPING_CLEANUP_IMAGES_CONFIRM=', $language);
    }
}
