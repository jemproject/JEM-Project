<?php
/** Regression coverage for rendering Housekeeping with and without core.admin. */
declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HousekeepingTemplateTest extends TestCase
{
    /** @return iterable<string, array{bool}> Users already allowed to access Housekeeping. */
    public static function identities(): iterable
    {
        yield 'Super User' => [true];
        yield 'tools operator without core.admin' => [false];
    }

    /**
     * Render the real template without global class aliases or housekeeping actions.
     * @param bool $administrator Whether the fixture identity has core.admin.
     * @return void
     */
    #[DataProvider('identities')]
    public function testToolsRenderWithPermissionScopedRecoveryLink(bool $administrator): void
    {
        require JEM_TEST_ROOT . '/tests/Support/housekeeping_template_environment.php';
        \Joomla\CMS\Factory::$administrator = $administrator;
        $view = new class {
            public int $totalcats = 0;
            public string $truncateNonce = 'fixture';

            /** @return string Real template markup; always unwind the output buffer on failure. */
            public function render(): string
            {
                ob_start();
                try {
                    require JEM_TEST_ROOT . '/admin/views/housekeeping/tmpl/default.php';
                    return (string) ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        $html = $view->render();
        self::assertSame($administrator, str_contains($html, 'tool=attachmentrecovery'));
        self::assertStringContainsString('housekeeping.cleaneventimg', $html);
        self::assertStringContainsString('housekeeping.cleanvenueimg', $html);
        self::assertStringContainsString('name="csrf"', $html);
    }
}
