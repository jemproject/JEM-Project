<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousekeepingUnusedFilesTest extends TestCase
{
    public function testExistingCleanupActionsRemainAvailableThroughTheReview(): void
    {
        $controller = $this->read('admin/controllers/housekeeping.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/default.php');

        // Existing action names remain available; image actions now open their review.
        foreach (array('cleaneventimg', 'cleanvenueimg', 'cleancategoryimg') as $task) {
            self::assertStringContainsString("\$this->registerTask('" . $task . "'", $controller);
            self::assertStringContainsString("this.form.task.value='housekeeping." . $task . "'", $layout);
        }
        self::assertStringContainsString('$model->cleanupUnusedAttachmentFiles()', $controller);
        self::assertStringContainsString("this.form.task.value='housekeeping.cleanupUnusedAttachmentFiles'", $layout);

        self::assertStringContainsString("view=housekeeping&tool=files'", $layout);
        self::assertStringContainsString('public function deleteUnusedFiles(): void', $controller);
    }

    public function testDeletionRequiresTokenPermissionNonceAndSelection(): void
    {
        $controller = str_replace("\r\n", "\n", $this->read('admin/controllers/housekeeping.php'));
        $method = substr($controller, (int) strpos($controller, 'public function deleteUnusedFiles(): void'));
        $method = substr($method, 0, (int) strpos($method, "\n    }\n") ?: strlen($method));

        self::assertStringContainsString('JemHelper::requirePostToken();', $method);
        self::assertStringContainsString('$this->allowHousekeeping();', $method);
        self::assertStringContainsString("JemHelper::consumeActionNonce('housekeeping.deleteUnusedFiles'", $method);
        self::assertStringContainsString('JemHousekeepingFiles::areas()', $method);
        self::assertStringContainsString("COM_JEM_HOUSEKEEPING_FILES_NONE_SELECTED", $method);

        $view = $this->read('admin/views/housekeeping/view.html.php');
        self::assertStringContainsString("JemHelper::issueActionNonce('housekeeping.deleteUnusedFiles')", $view);
        self::assertStringContainsString("\$this->setLayout('files');", $view);
    }

    public function testSelectionsAreRevalidatedAndContainedBeforeDeleting(): void
    {
        $files = $this->read('admin/classes/housekeepingfiles.class.php');

        self::assertStringContainsString('public const BATCH_LIMIT = 200;', $files);
        self::assertStringContainsString("preg_match('/^[a-f0-9]{64}\$/D', \$id)", $files);
        // The identifier binds path, size and modification time, so a changed file is skipped.
        self::assertStringContainsString("hash_hmac(\n            'sha256'", str_replace("\r\n", "\n", $files));
        self::assertStringContainsString('@filemtime($path)', $files);
        self::assertStringContainsString('foreach ($this->candidates($area) as $candidate)', $files);
        self::assertStringContainsString("\$result['skipped']++;", $files);
        self::assertStringContainsString('$this->insideBase($base, $relative)', $files);
        self::assertStringContainsString("preg_match('#(^|/)\\.\\.(/|\$)#'", $files);
        self::assertStringContainsString('realpath($base)', $files);
        self::assertStringContainsString('$file->isLink()', $files);
    }

    public function testImageRulesComeFromTheModelAndUsesElsewhereAreMarked(): void
    {
        $files = $this->read('admin/classes/housekeepingfiles.class.php');
        $model = $this->read('admin/models/housekeeping.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/files.php');

        self::assertStringContainsString('$model->getUnusedImagePaths($area)', $files);
        self::assertStringContainsString('$model->getUsedImagePaths($area)', $files);
        self::assertStringContainsString('return $this->getImages($this->typeForArea($area));', $model);
        self::assertStringContainsString('(array) $this->getAssigned($this->typeForArea($area))', $model);

        foreach (array('#__content', '#__modules', '#__menu') as $table) {
            self::assertStringContainsString("array('" . $table . "'", $files);
        }
        self::assertStringContainsString('data-elsewhere="1"', $layout);
        self::assertStringContainsString('!box.dataset.elsewhere', $layout);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $layout);
        self::assertStringContainsString('name="files_nonce"', $layout);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
