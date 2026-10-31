<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Controllers;

use JemProject\Tests\Support\FrontendDispatch\Application;
use JemProject\Tests\Support\FrontendDispatch\ControllerBoundary;
use JemProject\Tests\Support\FrontendDispatch\Factory;
use JemProject\Tests\Support\FrontendDispatch\Helper;
use JemProject\Tests\Support\FrontendDispatch\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise the complete production display method, including model-to-view wiring. */
final class FrontendModelDispatchTest extends TestCase
{
    /** @return void Load the real controller against isolated Joomla services. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/frontend_dispatch_environment.php';
        class_alias(Factory::class, 'Joomla\\CMS\\Factory');
        class_alias(ControllerBoundary::class, 'Joomla\\CMS\\MVC\\Controller\\BaseController');
        class_alias(UserFactory::class, 'JemFactory');
        class_alias(Helper::class, 'JemHelper');
        require_once JEM_TEST_ROOT . '/site/controller.php';
    }

    /** @return iterable<string, array{string, string, string, string, string}> Dispatch cases. */
    public static function routes(): iterable
    {
        foreach (['', 'archive'] as $task) {
            foreach (['html', 'raw'] as $format) {
                foreach (['default', 'edit', 'pdf'] as $layout) {
                    yield 'annual:' . $task . ':' . $format . ':' . $layout
                        => ['annualcalendar', $format, $layout, $task, 'annualcalendar'];
                }
            }
        }
        foreach (['calendar', 'weekcal', 'day', 'eventslist', 'eventsblog', 'specialdays'] as $view) {
            yield $view => [$view, 'html', 'default', '', $view];
        }
        yield 'eventsmap' => ['eventsmap', 'html', 'default', '', 'eventslist'];
        yield 'venuesmap' => ['venuesmap', 'html', 'default', '', 'venueslist'];
        yield 'fallback' => ['unknown', 'html', 'default', '', 'eventslist'];
    }

    /**
     * Check model selection, default attachment, document/layout and rendering.
     *
     * @param string $view Requested view.
     * @param string $format Document format.
     * @param string $layout View layout.
     * @param string $task Normal or archive request.
     * @param string $expected Expected default model name.
     * @return void
     */
    #[DataProvider('routes')]
    public function testDispatchUsesTheExpectedDefaultModel(
        string $view,
        string $format,
        string $layout,
        string $task,
        string $expected
    ): void {
        Factory::$app = new Application(['view' => $view, 'layout' => $layout, 'task' => $task], $format);
        Helper::$iconLoads = 0;
        $controller = new \JemController();
        $controller->display();

        self::assertSame([$expected], $controller->modelRequests);
        self::assertSame($expected, $controller->view->model->name);
        self::assertSame([$view, $format], $controller->viewRequest);
        self::assertSame($layout, $controller->view->layout);
        self::assertSame(Factory::$app->document, $controller->view->document);
        self::assertTrue($controller->view->displayed);
        self::assertSame(1, Helper::$iconLoads);
    }
}
