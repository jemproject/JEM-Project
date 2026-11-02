<?php

declare(strict_types=1);

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Run actual module getList paths with isolated model dependencies and production parsing. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModuleOffsetInputTest extends TestCase
{
    /**
     * Load the real module helpers without bootstrapping an installed Joomla site.
     *
     * @return void
     */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/title_rendering_stubs.php';
        foreach (['mod_jem', 'mod_jem_banner', 'mod_jem_wide', 'mod_jem_teaser'] as $module) {
            require_once JEM_TEST_ROOT . '/modules/' . $module . '/helper.php';
        }
    }

    /**
     * Cover active/archived/Today modes with valid, invalid and fractional offsets.
     *
     * @return iterable Helper, selection mode, input and expected minutes.
     */
    public static function moduleInputs(): iterable
    {
        foreach (['ModJemHelper', 'ModJemBannerHelper', 'ModJemWideHelper', 'ModJemTeaserHelper'] as $helper) {
            $modes = $helper === 'ModJemHelper' ? [0, 1, 2] : [0, 1, 2, 3];
            foreach ($modes as $mode) {
                foreach ([
                    ['abc', 0], ['', 0], ['1.5', 90], ['1,5', 90],
                    ['-1,5', -90], ['2abc', 0], ['1,2,3', 0], [[], 0],
                    ['1e309', 0], [null, 0],
                ] as $index => [$raw, $minutes]) {
                    yield $helper . '-' . $mode . '-' . $index => [$helper, $mode, $raw, $minutes];
                }
            }
        }
    }

    /**
     * Assert malformed input cannot prevent model loading, including an empty result set.
     *
     * @param string $helper Real module helper class.
     * @param int $mode Selection mode.
     * @param mixed $raw Stored input.
     * @param int $minutes Expected parsed offset.
     * @return void
     */
    #[DataProvider('moduleInputs')]
    public function testModuleOffset(string $helper, int $mode, $raw, int $minutes): void
    {
        $params = new TitleRenderingParams(['type' => $mode, 'offset_hours' => $raw, 'show_status_indicators' => 0]);
        self::assertSame([], $helper::getList($params));
        self::assertSame($mode === 2 ? 2 : 1, BaseDatabaseModel::$states['filter.published']);
        if ($mode === 3) {
            self::assertSame([$raw ?? 0], JemHelper::$hourOffsets);
            self::assertSame([], JemHelper::$minuteOffsets);
        } elseif ($mode === 2) {
            self::assertSame([], JemHelper::$minuteOffsets);
            self::assertSame([], JemHelper::$hourOffsets);
        } else {
            self::assertNotEmpty(JemHelper::$minuteOffsets);
            self::assertSame([$minutes], array_values(array_unique(JemHelper::$minuteOffsets)));
        }
    }
}
