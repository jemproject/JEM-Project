<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GlobalLanguagePolicyTest extends TestCase
{
    public function testEveryJemManifestInstallsLanguageFilesGlobally(): void
    {
        $manifests = $this->manifestPaths();

        self::assertCount(19, $manifests);

        foreach ($manifests as $relativePath) {
            $path = JEM_TEST_ROOT . '/' . $relativePath;
            self::assertFileExists($path);

            $xml = new DOMDocument();
            self::assertTrue($xml->load($path), $relativePath . ' must be valid XML.');

            $xpath = new DOMXPath($xml);
            self::assertSame(
                0,
                $xpath->query('//files/folder[normalize-space(.) = "language"]')->length,
                $relativePath . ' must not install a local language folder.'
            );

            $languageGroups = $xpath->query('//languages');
            self::assertGreaterThan(0, $languageGroups->length, $relativePath . ' must declare global language files.');

            foreach ($languageGroups as $languageGroup) {
                self::assertInstanceOf(DOMElement::class, $languageGroup);
                $sourceFolder = trim($languageGroup->getAttribute('folder'));

                foreach ($xpath->query('./language', $languageGroup) as $languageFile) {
                    self::assertInstanceOf(DOMElement::class, $languageFile);
                    self::assertSame('en-GB', $languageFile->getAttribute('tag'));

                    $source = dirname($path) . '/';
                    if ($sourceFolder !== '') {
                        $source .= trim($sourceFolder, '/') . '/';
                    }
                    $source .= trim($languageFile->textContent);

                    self::assertFileExists(
                        str_replace('/', DIRECTORY_SEPARATOR, $source),
                        $relativePath . ' declares a missing language file.'
                    );
                }
            }
        }
    }

    public function testComponentDeclaresSeparateGlobalSiteAndAdministratorLanguages(): void
    {
        $xml = new DOMDocument();
        self::assertTrue($xml->load(JEM_TEST_ROOT . '/jem.xml'));

        $xpath = new DOMXPath($xml);

        self::assertSame(1, $xpath->query('/extension/languages[@folder = "site/language"]')->length);
        self::assertSame(
            1,
            $xpath->query('/extension/administration/languages[@folder = "admin/language"]')->length
        );
    }

    public function testRuntimeUsesGlobalLanguagesBeforeLegacyLocalFallbacks(): void
    {
        $helper = $this->read('site/helpers/helper.php');
        self::assertStringContainsString('function loadExtensionLanguage(', $helper);
        self::assertStringContainsString('function loadComponentLanguage(', $helper);
        self::assertLessThan(
            strpos($helper, '$paths[] = $legacyPath;'),
            strpos($helper, '$paths = array($globalPath);')
        );

        foreach (array(
            'site/jem.php',
            'modules/mod_jem/mod_jem.php',
            'modules/mod_jem_banner/mod_jem_banner.php',
            'modules/mod_jem_jubilee/mod_jem_jubilee.php',
            'modules/mod_jem_teaser/mod_jem_teaser.php',
            'modules/mod_jem_types/mod_jem_types.php',
            'modules/mod_jem_wide/mod_jem_wide.php',
            'plugins/plg_content_jemembed/jemembed.php',
            'plugins/plg_content_jemlistevents/jemlistevents.php',
        ) as $relativePath) {
            $contents = $this->read($relativePath);
            self::assertStringContainsString('JemHelper::loadComponentLanguage(', $contents, $relativePath);
            self::assertStringNotContainsString("->load('com_jem', JPATH_SITE . '/components/com_jem'", $contents);
            self::assertStringNotContainsString("->load('com_jem', JPATH_SITE.'/components/com_jem'", $contents);
        }

        self::assertStringContainsString(
            "JemHelper::loadExtensionLanguage('mod_jem_map', JPATH_SITE",
            $this->read('site/views/venuesmap/view.html.php')
        );

        $notificationService = $this->read('site/classes/notificationtemplateservice.class.php');
        self::assertLessThan(
            strpos($notificationService, "JPATH_PLUGINS . '/jem/mailer/language/'"),
            strpos($notificationService, "JPATH_ADMINISTRATOR . '/language/'")
        );

        $customFields = $this->read('site/classes/customfields.class.php');
        self::assertMatchesRegularExpression(
            '#getPackageLanguageValue.*JPATH_ADMINISTRATOR . \'/language/\'.*JPATH_SITE . \'/language/\'#s',
            $customFields
        );
    }

    public function testPackageUpdateCleansOnlyBundledLocalEnglishFiles(): void
    {
        $installer = $this->read('package/pkg_install.php');
        self::assertStringContainsString('$this->removeLegacyLocalEnglishLanguageFiles();', $installer);
        self::assertSame(19, substr_count($installer, "/language/en-GB' =>"));
        self::assertStringContainsString('Other language tags are owned by separately installed JEM language', $installer);

        preg_match('#public function postflight\(.*?function enablePlugin#s', $installer, $postflight);
        self::assertNotEmpty($postflight);
        self::assertLessThan(
            strpos($postflight[0], '$this->removeLegacyLocalEnglishLanguageFiles();'),
            strpos($postflight[0], '$this->ensureJemNotificationTask();')
        );
    }

    /**
     * @return list<string>
     */
    private function manifestPaths(): array
    {
        return array(
            'jem.xml',
            'package/pkg_jem.xml',
            'modules/mod_jem/mod_jem.xml',
            'modules/mod_jem_banner/mod_jem_banner.xml',
            'modules/mod_jem_cal/mod_jem_cal.xml',
            'modules/mod_jem_jubilee/mod_jem_jubilee.xml',
            'modules/mod_jem_map/mod_jem_map.xml',
            'modules/mod_jem_teaser/mod_jem_teaser.xml',
            'modules/mod_jem_types/mod_jem_types.xml',
            'modules/mod_jem_wide/mod_jem_wide.xml',
            'plugins/plg_actionlog_jem/jem.xml',
            'plugins/plg_content_jemembed/jemembed.xml',
            'plugins/plg_content_jemlistevents/jemlistevents.xml',
            'plugins/plg_finder_jem/jem.xml',
            'plugins/plg_jem_comments/comments.xml',
            'plugins/plg_jem_mailer/mailer.xml',
            'plugins/plg_quickicon_jem/jem.xml',
            'plugins/plg_task_jem/jem.xml',
            'plugins/plg_user_jem/jem.xml',
        );
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
