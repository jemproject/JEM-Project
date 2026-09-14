<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImportSecuritySettingsTest extends TestCase
{
    public function testImportSecurityControlsUseLabelsAndDescriptions(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/import/tmpl/default.php');

        foreach (array(
            'COM_JEM_SETTINGS_SECURITY_ADDITIONAL_BLOCKED_TAGS',
            'COM_JEM_SETTINGS_SECURITY_ALLOW_TRUSTED_IFRAMES',
            'COM_JEM_SETTINGS_SECURITY_TRUSTED_IFRAME_HOSTS',
        ) as $label) {
            self::assertStringContainsString("Text::_('" . $label . "')", $layout);
            self::assertStringContainsString("Text::_('" . $label . "_DESC')", $layout);
        }
    }

    public function testImportViewRendersSecurityAsFinalTab(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/import/tmpl/default.php');
        $catalogPosition = strpos($layout, "'download-lists', Text::_('COM_JEM_IMPORT_TAB_DOWNLOAD_LISTS')");
        $securityPosition = strpos($layout, "'import-security', Text::_('COM_JEM_SETTINGS_SECURITY')");
        $endPosition = strrpos($layout, "HTMLHelper::_('uitab.endTabSet')");

        self::assertIsInt($catalogPosition);
        self::assertIsInt($securityPosition);
        self::assertIsInt($endPosition);
        self::assertGreaterThan($catalogPosition, $securityPosition);
        self::assertLessThan($endPosition, $securityPosition);
        self::assertStringContainsString('JemImportSecurityHelper::getCoreBlockedTags()', $layout);
        self::assertStringContainsString("JemImportSubmit('import.saveSecuritySettings', 'import-security')", $layout);
    }

    public function testSecurityControlsAreNoLongerPartOfSettings(): void
    {
        $settingsLayout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default.php');
        $settingsXml = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');

        self::assertStringNotContainsString("'security-settings'", $settingsLayout);
        self::assertStringNotContainsString('fieldset name="import_security"', $settingsXml);
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_security.php');
    }

    public function testControllerRequiresSuperUserAndPersistsGlobalPolicy(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');

        self::assertStringContainsString('function saveSecuritySettings()', $controller);
        self::assertStringContainsString("authorise('core.admin')", $controller);
        self::assertStringContainsString("set('import_additional_blocked_tags'", $controller);
        self::assertStringContainsString("set('import_allow_trusted_iframes'", $controller);
        self::assertStringContainsString("set('import_trusted_iframe_hosts'", $controller);
    }
}
