<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LanguageCatalogViewContractTest extends TestCase
{
    public function testControlPanelPlacesLanguagesBesideSettingsForCoreAdministrators(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default.php');
        $settingsPosition = strpos($layout, "view=settings', 'icon-48-settings.svg'");
        $languagesPosition = strpos($layout, "view=languages', 'icon-48-languages.svg'");

        self::assertNotFalse($settingsPosition);
        self::assertNotFalse($languagesPosition);
        self::assertGreaterThan($settingsPosition, $languagesPosition);
        self::assertStringContainsString("authorise('core.admin')", $layout);
    }

    public function testLanguagesIconUsesTheControlPanelVisualSystem(): void
    {
        $icon = (string) file_get_contents(JEM_TEST_ROOT . '/media/images/icon-48-languages.svg');

        self::assertStringContainsString('viewBox="0 0 96 96"', $icon);
        self::assertStringContainsString('stroke:rgb(114,149,177)', $icon);
        self::assertStringContainsString('stroke:rgb(249,159,0)', $icon);
        self::assertStringContainsString('stroke-width:4.5px', $icon);
        self::assertStringNotContainsString('fill="#1f5f8b"', $icon);
    }

    public function testLanguagesViewRequiresCoreAdmin(): void
    {
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/languages/view.html.php');

        self::assertStringContainsString("authorise('core.admin')", $view);
        self::assertStringContainsString("JERROR_ALERTNOAUTHOR", $view);
    }

    public function testInstallActionUsesTheTokenProtectedJemController(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/languages/tmpl/default.php');

        self::assertStringContainsString('option=com_jem&task=languages.install', $layout);
        self::assertStringContainsString('name="package_id"', $layout);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $layout);
    }

    public function testLanguageRowsShowOnlyTheNewestRelevantPackageWithoutHistoryBox(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/languages/tmpl/default.php');

        self::assertStringContainsString("\$displayPackage = \$package ?: (\$item['packages'][0] ?? null);", $layout);
        self::assertStringContainsString("getVersionMajor(\$displayPackage['jem'])", $layout);
        self::assertStringContainsString("preg_quote(\$item['tag'], '/')", $layout);
        self::assertStringNotContainsString('<details', $layout);
        self::assertStringNotContainsString('COM_JEM_LANGUAGES_PACKAGE_HISTORY', $layout);
        self::assertStringNotContainsString('COM_JEM_LANGUAGES_AVAILABLE_VERSION', $layout);
    }

    public function testControllerVerifiesAndUsesTheNativeJoomlaInstallerEngine(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/languages.php');

        self::assertStringContainsString('JemHelper::requirePostToken()', $controller);
        self::assertStringContainsString("authorise('core.admin')", $controller);
        self::assertStringContainsString('JemRemoteSourceHelper::download', $controller);
        self::assertStringContainsString('hash_equals', $controller);
        self::assertStringContainsString('InstallerHelper::unpack', $controller);
        self::assertStringContainsString('Installer::getInstance()', $controller);
    }

    public function testCompatibilityPolicyDoesNotRequireAContentLanguage(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/languages.php');

        self::assertStringContainsString('LanguageHelper::getInstalledLanguages', $model);
        self::assertStringNotContainsString('getContentLanguages', $model);
        self::assertStringNotContainsString('#__languages', $model);
        self::assertStringNotContainsString('published', $model);
    }

    public function testLanguagesUseTheJoomlaSiteDefaultForOrdering(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/languages.php');

        self::assertStringContainsString("ComponentHelper::getParams('com_languages')->get('site', 'en-GB')", $model);
        self::assertStringContainsString('JemLanguageCatalogHelper::sortLanguageItems', $model);
    }

    public function testCompatibilityUsesTheJemMajorReleaseAndBlocksCrossMajorPackages(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/languagecatalog.php');
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/languages.php');

        self::assertStringContainsString('getVersionMajor($jemVersion)', $helper);
        self::assertStringContainsString("getVersionMajor(\$package['jem'])", $controller);
        self::assertStringContainsString('getVersionMajor($jemVersion)', $controller);
        self::assertStringContainsString("compatibility']) !== 'major'", $helper);
    }

    public function testLocalCatalogOverridesTheRemoteCatalogWithoutBeingBundled(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/languagecatalog.php');
        $manifest = (string) file_get_contents(JEM_TEST_ROOT . '/jem.xml');
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/languages/tmpl/default.php');
        $installer = (string) file_get_contents(JEM_TEST_ROOT . '/package/pkg_install.php');

        self::assertStringContainsString("LOCAL_CATALOG_DIRECTORY = 'media/com_jem/languages'", $helper);
        self::assertStringContainsString('if (self::hasLocalCatalog())', $helper);
        self::assertStringContainsString('self::getLocalCatalogXml()', $helper);
        self::assertStringContainsString('COM_JEM_LANGUAGES_CATALOG_LOCAL', $layout);
        self::assertLessThan(
            strpos($layout, "if (empty(\$this->catalogStatus['available']))"),
            strpos($layout, "if (!empty(\$this->catalogStatus['is_local']))")
        );
        self::assertStringNotContainsString('BUNDLED_CATALOG_SOURCE', $helper);
        self::assertStringNotContainsString('getCacheController', $helper);
        self::assertStringNotContainsString('<folder>languages</folder>', $manifest);
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/media/languages/language_catalog_jem.xml');
        self::assertFileExists(JEM_TEST_ROOT . '/updatecheck/language_catalog_jem.xml');
        self::assertStringContainsString('LEGACY_BUNDLED_LANGUAGE_CATALOG_SHA256', $installer);
        self::assertStringContainsString('removeUnusedCatalogDirectories()', $installer);
    }

    public function testUnavailableCatalogHidesTheLanguageTable(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/languages/tmpl/default.php');

        self::assertStringContainsString("if (!empty(\$this->catalogStatus['available']))", $layout);
        self::assertStringContainsString('COM_JEM_LANGUAGES_CATALOG_UNAVAILABLE', $layout);
    }
}
