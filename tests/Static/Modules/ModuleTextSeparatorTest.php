<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleTextSeparatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function moduleProvider(): iterable
    {
        yield 'basic' => array('mod_jem');
        yield 'teaser' => array('mod_jem_teaser');
        yield 'wide' => array('mod_jem_wide');
        yield 'banner' => array('mod_jem_banner');
        yield 'jubilee' => array('mod_jem_jubilee');
        yield 'calendar' => array('mod_jem_cal');
        yield 'map' => array('mod_jem_map');
        yield 'types' => array('mod_jem_types');
    }

    #[DataProvider('moduleProvider')]
    public function testIntroAndFooterFieldsFollowAnUnlabelledSeparator(string $module): void
    {
        $manifestPath = JEM_TEST_ROOT . '/modules/' . $module . '/' . $module . '.xml';
        $xml = simplexml_load_file($manifestPath);

        self::assertNotFalse($xml);
        $separators = $xml->xpath('//field[@name="module_text_spacer"]');
        self::assertIsArray($separators);
        self::assertCount(1, $separators);
        self::assertSame('spacer', (string) $separators[0]['type']);
        self::assertSame('', (string) $separators[0]['label']);
        self::assertSame('true', (string) $separators[0]['hr']);
        self::assertSame('', (string) $separators[0]['class']);

        $manifest = $this->read('modules/' . $module . '/' . $module . '.xml');
        $positions = array();
        foreach (array('module_text_spacer', 'showintrotext', 'introtext', 'showfootertext', 'footertext') as $fieldName) {
            $position = strpos($manifest, 'name="' . $fieldName . '"');
            self::assertNotFalse($position, $fieldName);
            $positions[] = $position;
        }
        $sortedPositions = $positions;
        sort($sortedPositions);
        self::assertSame($sortedPositions, $positions);
    }

    private function read(string $path): string
    {
        $fullPath = JEM_TEST_ROOT . '/' . $path;
        self::assertFileExists($fullPath);

        return (string) file_get_contents($fullPath);
    }
}
