<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventDescriptionPluginsTest extends TestCase
{
    #[DataProvider('descriptions')]
    public function testPreparedDescriptionReachesBothTemplates(
        string $intro,
        string $full,
        array $parameters,
        array $settings,
        array $request,
        string $expected,
        bool $hasReadmore
    ): void {
        class_alias(EventDescriptionPluginStub::class, 'Joomla\\CMS\\Plugin\\PluginHelper');
        $registry = static fn (array $values) => new class ($values) {
            public function __construct(private array $values)
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
            public function getCmd(string $key, string $default = ''): string
            {
                return (string) $this->get($key, $default);
            }
        };
        $params = $registry($parameters);
        $view = new class {
            public $params;
            public $settings;
            public $item;
        };
        $view->params = $params;
        $view->settings = $registry($settings);
        $view->item = (object) ['introtext' => $intro, 'fulltext' => $full, 'params' => $params];
        $app = (object) ['input' => $registry($request)];
        $dispatcher = new class {
            public array $calls = [];
            public function triggerEvent(string $name, array $arguments): array
            {
                $this->calls[] = [$name, $arguments[0], $arguments[3]];
                // A content plugin changes text, not the stored intro/full fields.
                $arguments[1]->text = str_replace(
                    ['{gallery}intro{/gallery}', '{gallery}full{/gallery}', '{gallery}empty{/gallery}'],
                    ['<div>Intro gallery</div>', '<div>Full gallery</div>', ''],
                    $arguments[1]->text
                );

                return [];
            }
        };
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/event/view.html.php');
        $marker = '// Select the visible description before preparing content plugins.';
        $start = strpos($source, $marker);
        // Also run against the old implementation to demonstrate the regression.
        if ($start === false) {
            $start = strpos($source, "if (\$item->params->get('show_intro', '1')");
        }
        self::assertNotFalse($start);
        $end = strpos($source, '$item->event = new stdClass();', $start);
        self::assertNotFalse($end);
        $prepare = substr($source, $start, $end - $start);
        $run = function () use ($prepare, $app, $dispatcher): void {
            $item = $this->item;
            $offset = 0;
            eval('use Joomla\\CMS\\Plugin\\PluginHelper;' . $prepare);
        };
        $run->call($view);
        self::assertSame([['onContentPrepare', 'com_jem.event', 0]], $dispatcher->calls);
        self::assertSame(['content'], EventDescriptionPluginStub::$imports);
        self::assertSame($intro, $view->item->introtext);
        self::assertSame($full, $view->item->fulltext);

        foreach (['default.php', 'responsive/default.php'] as $template) {
            $code = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/event/tmpl/' . $template);
            $start = strpos($code, '$eventLayout = (string)');
            $end = strpos($code, '$venueLayoutOverride =', $start);
            self::assertNotFalse($start);
            self::assertNotFalse($end);
            $layout = substr($code, $start, $end - $start);
            $start = strpos($code, '$eventFullHtml =');
            $end = strpos($code, '$showEventDescription =', $start);
            self::assertNotFalse($start);
            self::assertNotFalse($end);
            $description = substr($code, $start, $end - $start);
            $render = function () use ($layout, $description, $app): array {
                $params = $this->item->params;
                eval($layout . $description);

                return [$eventDescriptionHtml, $eventCompactHasMore];
            };
            self::assertSame([$expected, $hasReadmore], $render->call($view), $template);
        }
    }

    public static function descriptions(): iterable
    {
        $intro = '{gallery}intro{/gallery}';
        $full = '{gallery}full{/gallery}';
        $preparedIntro = '<div>Intro gallery</div>';
        $preparedFull = '<div>Full gallery</div>';
        yield 'gallery after readmore' => ['<p>Intro</p>', $full, [], [], [], $preparedFull, false];
        yield 'both sides with intro' => [$intro, $full, ['event_show_intro' => 1], [], [], $preparedIntro . $preparedFull, false];
        yield 'hidden intro' => [$intro, $full, ['event_show_intro' => 0, 'show_intro' => 1], [], [], $preparedFull, false];
        yield 'JEM intro setting wins' => [$intro, $full, ['event_show_intro' => 1, 'show_intro' => 0], [], [], $preparedIntro . $preparedFull, false];
        yield 'intro only' => [$intro, '', [], [], [], $preparedIntro, false];
        yield 'empty full placeholder' => [$intro, '<br>', [], [], [], $preparedIntro, false];
        yield 'empty description' => ['', '', [], [], [], '', false];
        yield 'empty plugin result is preserved' => ['<p>Intro</p>', '{gallery}empty{/gallery}', [], [], [], '', false];
        yield 'plain HTML' => ['<p>Intro</p>', '<p>Full</p>', ['event_show_intro' => 1], [], [], '<p>Intro</p><p>Full</p>', false];
        yield 'compact' => [$intro, $full, ['event_details_layout' => 'compact'], [], [], $preparedIntro, true];
        yield 'compact empty intro' => ['', $full, ['event_details_layout' => 'compact'], [], [], '', true];
        yield 'compact without full' => [$intro, '', ['event_details_layout' => 'compact'], [], [], $preparedIntro, false];
        yield 'global compact' => [$intro, $full, [], ['event_details_layout' => 'compact'], [], $preparedIntro, true];
        yield 'event overrides global' => [$intro, $full, ['event_details_layout' => 'details'], ['event_details_layout' => 'compact'], [], $preparedFull, false];
        yield 'request compact' => [$intro, $full, [], [], ['jem_layout' => 'compact'], $preparedIntro, true];
        yield 'event request wins' => [$intro, $full, [], [], ['jem_layout' => 'compact', 'jem_event_layout' => 'details'], $preparedFull, false];
        yield 'invalid request ignored' => [$intro, $full, [], [], ['jem_event_layout' => 'invalid'], $preparedFull, false];
        yield 'invalid layout falls back' => [$intro, $full, ['event_details_layout' => 'invalid'], [], [], $preparedFull, false];
    }

    public function testPreparationRemainsAfterTheAccessGuard(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/event/view.html.php');
        $guard = strpos($source, "if (!\$item->params->get('access-view'))");
        $prepare = strpos($source, '// Select the visible description before preparing content plugins.');
        self::assertNotFalse($guard);
        self::assertNotFalse($prepare);
        self::assertLessThan($prepare, $guard);
        $guardSource = substr($source, $guard, $prepare - $guard);
        self::assertStringContainsString('return;', $guardSource);
        self::assertStringContainsString("throw new \\Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);", $guardSource);
    }
}

final class EventDescriptionPluginStub
{
    public static array $imports = [];

    public static function importPlugin(string $group): void
    {
        self::$imports[] = $group;
    }
}
