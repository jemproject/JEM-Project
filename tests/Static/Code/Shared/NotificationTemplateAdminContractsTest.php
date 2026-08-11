<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NotificationTemplateAdminContractsTest extends TestCase
{
    public function testNotificationEditorsExposeTheStandardJoomlaAdminForm(): void
    {
        foreach (array('notificationtemplate', 'notificationcontent') as $view) {
            $contents = file_get_contents(
                JEM_TEST_ROOT . '/admin/views/' . $view . '/tmpl/default.php'
            );

            self::assertIsString($contents);
            self::assertStringContainsString('name="adminForm" id="adminForm"', $contents);
        }
    }

    public function testDedicatedPermissionAndControlPanelEntriesExist(): void
    {
        $access = $this->read('/admin/access.xml');
        $helper = $this->read('/admin/helpers/helper.php');
        $dashboard = $this->read('/admin/views/main/tmpl/default.php');
        $manifest = $this->read('/jem.xml');

        self::assertStringContainsString('jem.notifications.templates', $access);
        self::assertStringContainsString("canManage('jem.notifications.templates')", $helper);
        self::assertStringContainsString('view=notifications', $dashboard);
        self::assertStringContainsString('view=notifications', $manifest);
        self::assertStringContainsString('icon-48-notifications.svg', $dashboard);
        self::assertFileExists(JEM_TEST_ROOT . '/media/images/icon-48-notifications.svg');
        self::assertStringContainsString('icon-48-registration.svg', $dashboard);
        self::assertFileExists(JEM_TEST_ROOT . '/media/images/icon-48-registration.svg');
        self::assertFileExists(JEM_TEST_ROOT . '/admin/models/notifications.php');
        self::assertFileExists(JEM_TEST_ROOT . '/admin/views/notifications/view.html.php');
    }

    public function testEditorStoresOnlyLanguageOverridesInJoomlaMailTemplates(): void
    {
        $model = $this->read('/admin/models/notificationtemplate.php');
        $controller = $this->read('/admin/controllers/notificationtemplate.php');

        self::assertStringContainsString("insertObject('#__mail_templates'", $model);
        self::assertStringContainsString("updateObject('#__mail_templates'", $model);
        self::assertStringContainsString('delete($db->quoteName(\'#__mail_templates\'))', $model);
        self::assertStringContainsString('where($db->quoteName(\'language\')', $model);
        self::assertStringContainsString('Session::checkToken()', $controller);
        self::assertStringContainsString("canManage('jem.notifications.templates')", $controller);
    }

    public function testEditorOffersLiteralVariablesAndServerSideValidation(): void
    {
        $layout = $this->read('/admin/views/notificationtemplate/tmpl/default.php');
        $model = $this->read('/admin/models/notificationtemplate.php');

        self::assertStringContainsString('data-token="{', $layout);
        self::assertStringContainsString("['focus', 'click', 'keyup', 'select']", $layout);
        self::assertStringContainsString('activateTokenTarget(id)', $layout);
        self::assertStringContainsString('cursorPositions[id]', $layout);
        self::assertStringContainsString('plainTextToHtml', $layout);
        self::assertStringContainsString('jemNotificationGenerateHtml', $layout);
        self::assertStringContainsString('white-space:pre-wrap', $layout);
        self::assertStringContainsString('JemNotificationTemplateRenderer::validate', $model);
        self::assertStringContainsString('legacy_markers_not_allowed', $this->read('/site/classes/notificationtemplaterenderer.class.php'));
    }

    public function testFooterAndDisclaimerUseTwoIndependentTabsAndActiveLanguages(): void
    {
        $layout = $this->read('/admin/views/notificationcontent/tmpl/default.php');
        $model = $this->read('/admin/models/notificationcontent.php');
        $listModel = $this->read('/admin/models/notificationtemplates.php');

        self::assertStringContainsString("section=footer", $layout);
        self::assertStringContainsString("section=disclaimer", $layout);
        self::assertStringContainsString('view=notifications', $layout);
        $listLayout = $this->read('/admin/views/notificationtemplates/tmpl/default.php');
        self::assertStringContainsString('COM_JEM_NOTIFICATION_TAB_TEMPLATES', $listLayout);
        self::assertStringContainsString('section=footer', $listLayout);
        self::assertStringContainsString('section=disclaimer', $listLayout);
        self::assertStringContainsString("'published') . ' = 1'", $model);
        self::assertStringContainsString('hasMailerLanguage', $model);
        self::assertStringContainsString('$query->where(\'1 = 0\')', $listModel);
        self::assertStringContainsString('disabled', $layout);
        self::assertStringContainsString('white-space:pre-wrap', $layout);
    }

    public function testInstallerRegistersNativeMasterRowsIdempotently(): void
    {
        $installer = $this->read('/script.php');
        $service = $this->read('/site/classes/notificationtemplateservice.class.php');

        self::assertStringContainsString('registerNotificationTemplates()', $installer);
        self::assertStringContainsString('MailTemplate::getTemplate', $service);
        self::assertStringContainsString('MailTemplate::createTemplate', $service);
        self::assertStringContainsString('MailTemplate::updateTemplate', $service);
        self::assertStringContainsString('hasMailerLanguage', $service);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $path);
        self::assertNotFalse($contents, $path . ' must be readable.');

        return $contents;
    }
}
