<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModulePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/module_preview_stubs.php';
        require_once JEM_TEST_ROOT . '/site/views/modulepreview/view.html.php';
    }

    public function testOnlyTheAssignedJemModuleIsRendered(): void
    {
        Factory::$application = new ModulePreviewApplication(17, 17);
        ModuleHelper::$modules = array(
            (object) array('id' => 16, 'module' => 'mod_jem', 'title' => 'Other layout'),
            (object) array('id' => 17, 'module' => 'mod_jem_cal', 'title' => 'Calendar'),
        );

        $view = new JemViewModulepreview();
        $view->display();

        self::assertTrue($view->displayed);
        self::assertSame(17, $view->module->id);
        self::assertSame('Module layout', Factory::$application->document->title);
    }

    public function testAChangedModuleIdIsRejected(): void
    {
        Factory::$application = new ModulePreviewApplication(16, 17);
        ModuleHelper::$modules = array((object) array('id' => 16, 'module' => 'mod_jem', 'title' => 'Other'));

        $this->expectExceptionCode(404);
        (new JemViewModulepreview())->display();
    }

    public function testUnassignedOrNonJemModulesAreRejected(): void
    {
        Factory::$application = new ModulePreviewApplication(17, 17);
        ModuleHelper::$modules = array((object) array('id' => 17, 'module' => 'mod_custom', 'title' => 'Other'));

        $this->expectExceptionCode(404);
        (new JemViewModulepreview())->display();
    }
}
