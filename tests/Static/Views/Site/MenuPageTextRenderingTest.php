<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace JEM\Tests\Static\Views\Site;

use PHPUnit\Framework\TestCase;

/**
 * Keep optional page text consistent across public menu views and modules.
 */
class MenuPageTextRenderingTest extends TestCase
{
    public function testEverySelectableMenuLayoutOffersAndRendersPageText(): void
    {
        $xmlFiles = glob(JEM_TEST_ROOT . '/site/views/*/tmpl/*.xml');

        $this->assertNotEmpty($xmlFiles);

        foreach ($xmlFiles as $xmlPath) {
            $xml = file_get_contents($xmlPath);
            $relativeXml = str_replace(JEM_TEST_ROOT . '/', '', str_replace('\\', '/', $xmlPath));

            $this->assertNotFalse($xml, 'Unable to read ' . $relativeXml);

            if (preg_match('/<layout\b[^>]*\bhidden="true"/', $xml)) {
                continue;
            }

            foreach (array('showintrotext', 'introtext', 'showfootertext', 'footertext') as $field) {
                $this->assertStringContainsString(
                    'name="' . $field . '"',
                    $xml,
                    sprintf('%s must expose the %s menu option.', $relativeXml, $field)
                );
            }

            $layout = pathinfo($xmlPath, PATHINFO_FILENAME);
            $templatePaths = array(dirname($xmlPath) . '/' . $layout . '.php');
            $responsivePath = dirname($xmlPath) . '/responsive/' . $layout . '.php';

            if (is_file($responsivePath)) {
                $templatePaths[] = $responsivePath;
            }

            foreach ($templatePaths as $templatePath) {
                $template = file_get_contents($templatePath);
                $relativeTemplate = str_replace(JEM_TEST_ROOT . '/', '', str_replace('\\', '/', $templatePath));

                $this->assertNotFalse($template, 'Unable to read ' . $relativeTemplate);

                $delegatesToPrimaryTemplate = strpos($template, "/tmpl/{$layout}.php'") !== false
                    || (strpos($template, 'dirname(__DIR__)') !== false
                        && strpos($template, "'/{$layout}.php'") !== false);

                foreach (array('intro', 'footer') as $section) {
                    $this->assertTrue(
                        $delegatesToPrimaryTemplate || strpos($template, "get('show{$section}text')") !== false,
                        sprintf('%s does not honour Show %s Text.', $relativeTemplate, ucfirst($section))
                    );
                    $this->assertTrue(
                        $delegatesToPrimaryTemplate || strpos($template, "get('{$section}text')") !== false,
                        sprintf('%s does not output %s Text.', $relativeTemplate, ucfirst($section))
                    );
                }
            }
        }
    }

    public function testEveryModuleOffersAndRendersPageTextOnce(): void
    {
        $moduleDirectories = glob(JEM_TEST_ROOT . '/modules/mod_*', GLOB_ONLYDIR);

        $this->assertNotEmpty($moduleDirectories);

        foreach ($moduleDirectories as $moduleDirectory) {
            $moduleName = basename($moduleDirectory);
            $xmlPath = $moduleDirectory . '/' . $moduleName . '.xml';
            $entryPath = $moduleDirectory . '/' . $moduleName . '.php';
            $xml = file_get_contents($xmlPath);
            $entry = file_get_contents($entryPath);

            $this->assertNotFalse($xml, 'Unable to read ' . $xmlPath);
            $this->assertNotFalse($entry, 'Unable to read ' . $entryPath);

            foreach (array('showintrotext', 'introtext', 'showfootertext', 'footertext') as $field) {
                $this->assertStringContainsString(
                    'name="' . $field . '"',
                    $xml,
                    sprintf('%s must expose the %s option.', $moduleName, $field)
                );
            }

            foreach (array('intro', 'footer') as $section) {
                $renderCall = "renderModuleText(\$params, '{$section}')";

                $this->assertSame(
                    1,
                    substr_count($entry, $renderCall),
                    sprintf('%s must render %s Text exactly once.', $moduleName, ucfirst($section))
                );
            }

            foreach (glob($moduleDirectory . '/tmpl/*.php') as $templatePath) {
                $template = file_get_contents($templatePath);

                $this->assertStringNotContainsString("\$params->get('introtext'", $template);
                $this->assertStringNotContainsString("\$params->get('footertext'", $template);
            }
        }
    }
}
