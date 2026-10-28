<?php

declare(strict_types=1);

namespace Joomla\CMS\MVC\Controller {
    class BaseController
    {
    }
}

namespace {
    use PHPUnit\Framework\Attributes\PreserveGlobalState;
    use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
    use PHPUnit\Framework\TestCase;

    #[RunTestsInSeparateProcesses]
    #[PreserveGlobalState(false)]
    final class FrontendMenuLayoutPolicyTest extends TestCase
    {
        public function testOneLayoutRemainsDirectEvenWithSeveralRecords(): void
        {
            $layouts = $this->collect(array(
                array(array(), 'Events List', 'events-list', 'index.php?option=com_jem&view=eventslist', 11),
                array(array(), 'Another List', 'events-list-2', 'index.php?option=com_jem&view=eventslist&layout=default&id=2', 11),
            ));

            self::assertSame(array('default' => true), $layouts['events:eventslist']);
            self::assertCount(1, $layouts['events:eventslist']);
        }

        public function testSeveralLayoutsAreGroupedWithoutMixingMenuGroups(): void
        {
            $layouts = $this->collect(array(
                array(array(), 'Today', 'today', 'index.php?option=com_jem&view=day', 12),
                array(array(), 'Timetable', 'timetable', 'index.php?option=com_jem&view=day&layout=timetable', 12),
                array(array(), 'Timeline', 'timeline', 'index.php?option=com_jem&view=day&layout=timeline', 12),
                array(array(), 'Other Day', 'other-day', 'index.php?option=com_jem&view=day', 11),
            ));

            self::assertSame(array('default' => true, 'timetable' => true, 'timeline' => true), $layouts['calendars:day']);
            self::assertSame(array('default' => true), $layouts['events:day']);
        }

        public function testExternalAndNonJemItemsDoNotCreateViewGroups(): void
        {
            $layouts = $this->collect(array(
                array(array(), 'External', 'external', 'https://example.test/?option=com_jem&view=day', 11, 'url'),
                array(array(), 'Other component', 'other', 'index.php?option=com_other&view=day', 11),
            ));

            self::assertSame(array(), $layouts);
        }

        private function collect(array $items): array
        {
            require_once JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php';

            $controller = new \JemControllerFrontendmenu();
            $method = new \ReflectionMethod($controller, 'getGeneratedViewLayouts');

            return $method->invoke($controller, $items, array('events' => 11, 'calendars' => 12));
        }
    }
}
