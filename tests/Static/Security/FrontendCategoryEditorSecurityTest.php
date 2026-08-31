<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FrontendCategoryEditorSecurityTest extends TestCase
{
    public function testEditorUsesDedicatedMvcFilesAndRestrictedForm(): void
    {
        foreach (array(
            'site/controllers/category.php',
            'site/models/editcategory.php',
            'site/models/forms/category.xml',
            'site/views/editcategory/view.html.php',
            'site/views/editcategory/tmpl/edit.php',
            'site/views/editcategory/tmpl/responsive/edit.php',
        ) as $path) {
            self::assertFileExists(JEM_TEST_ROOT . '/' . $path);
        }

        $form = $this->read('site/models/forms/category.xml');

        foreach (array('catname', 'parent_id', 'type_id', 'description', 'image', 'userfile', 'published', 'access') as $field) {
            self::assertStringContainsString('name="' . $field . '"', $form);
        }

        foreach (array('rules', 'groupid', 'created_user_id', 'modified_user_id', 'checked_out') as $field) {
            self::assertStringNotContainsString('name="' . $field . '"', $form);
        }
    }

    public function testControllerAndDisplayBoundaryRequireLoginTokenAndStoredRecordAcl(): void
    {
        $controller = $this->read('site/controllers/category.php');
        $display = $this->read('site/controller.php');
        $policy = $this->read('site/classes/frontendcategoryaccess.class.php');

        self::assertStringContainsString('$this->checkToken()', $controller);
        self::assertStringContainsString('$this->requireFrontendUser()', $controller);
        self::assertStringContainsString('getFrontendItemOrFail', $controller);
        self::assertStringContainsString('JemFrontendCategoryAccess::canEdit', $controller);
        self::assertStringContainsString("if (\$viewName === 'editcategory')", $display);
        self::assertStringContainsString("checkEditId('com_jem.edit.category'", $display);
        self::assertStringContainsString("authorise('core.create', 'com_jem')", $policy);
        self::assertStringContainsString("authorise('core.edit.own', 'com_jem')", $policy);
        self::assertStringContainsString('created_user_id', $policy);
        self::assertStringContainsString('getAuthorisedViewLevels', $policy);
    }

    public function testModelRevalidatesHierarchyFieldsStateAndImages(): void
    {
        $model = $this->read('site/models/editcategory.php');

        self::assertStringContainsString('$allowed = array_flip(array(', $model);
        self::assertStringContainsString('validateParent(', $model);
        self::assertStringContainsString('$parentId === $recordId', $model);
        self::assertStringContainsString('$parent->lft >', $model);
        self::assertStringContainsString('validateType(', $model);
        self::assertStringContainsString('canAssignAccess', $model);
        self::assertStringContainsString('canEditState', $model);
        self::assertStringContainsString("JemImageProfilePolicy::CATEGORY", $model);
        self::assertStringContainsString('JemImage::uploadProfileImage', $model);
        self::assertStringContainsString("array('jpg', 'jpeg', 'png', 'gif', 'webp')", $model);
        self::assertStringContainsString('$db->transactionStart()', $model);
        self::assertStringContainsString('$db->transactionRollback()', $model);
        self::assertStringContainsString('removeUploadedPaths', $model);
        self::assertStringNotContainsString("submittedData['rules']", $model);
    }

    public function testImageTabRoutesAndCategoryListEditIconsAreIntegrated(): void
    {
        $layout = $this->read('site/views/editcategory/tmpl/edit.php');
        $router = $this->read('site/router.php');
        $noMenu = $this->read('site/services/JemNomenuRules.php');
        $listView = $this->read('site/views/categories/view.html.php');
        $responsiveList = $this->read('site/views/categories/tmpl/responsive/default.php');
        $legacyList = $this->read('site/views/categories/tmpl/default.php');
        $categoryView = $this->read('site/views/category/view.html.php');
        $responsiveCategory = $this->read('site/views/category/tmpl/responsive/default.php');
        $legacyCategory = $this->read('site/views/category/tmpl/default.php');

        self::assertStringContainsString("Text::_('COM_JEM_IMAGE')", $layout);
        self::assertStringContainsString("JemImageCamera::resolutionControl", $layout);
        self::assertStringContainsString('jem-image-upload-panel', $layout);
        self::assertStringContainsString("array('editcategory', 'editevent', 'editvenue')", $router);
        self::assertStringContainsString("case 'editcategory':", $noMenu);
        self::assertStringContainsString('canEditCategory', $listView);
        self::assertStringContainsString("'editcategory'", $responsiveList);
        self::assertStringContainsString("'editcategory'", $legacyList);
        self::assertStringContainsString('JemFrontendCategoryAccess::canEdit($user, $category)', $categoryView);
        self::assertStringContainsString("'editcategory'", $responsiveCategory);
        self::assertStringContainsString("'editcategory'", $legacyCategory);
    }

    public function testSettingsAndGeneratedMenuCloseTheFeature(): void
    {
        $settings = $this->read('admin/models/forms/settings.xml');
        $settingsLayout = $this->read('admin/views/settings/tmpl/default_basicimagehandling.php');
        $menu = $this->read('admin/controllers/frontendmenu.php');
        $upgrade = $this->read('admin/sql/updates/mysql/5.1.0.sql');

        foreach (array('event_intro', 'event_full', 'venue', 'category') as $profile) {
            foreach (array('default_dimension', 'dimension_mandatory', 'mode', 'ratio', 'ratio_mandatory') as $field) {
                self::assertStringContainsString('name="image_' . $profile . '_' . $field . '"', $settings);
            }
        }

        self::assertStringContainsString('<li data-jem-image-ratio>', $settingsLayout);
        self::assertStringContainsString("'submit-category', 'index.php?option=com_jem&view=editcategory'", $menu);
        self::assertStringContainsString("SET `value` = '400' WHERE `keyname` = 'sizelimit' AND `value` = '200'", $upgrade);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
    }
}
