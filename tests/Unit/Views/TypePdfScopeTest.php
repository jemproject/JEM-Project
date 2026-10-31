<?php

declare(strict_types=1);

use JemProject\Tests\TypePdf\Application;
use JemProject\Tests\TypePdf\Factory;
use JemProject\Tests\TypePdf\JemFrontendAccess;
use JemProject\Tests\TypePdf\JemPdfView;
use JemProject\Tests\TypePdf\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute unchanged production display methods with isolated model/access/rendering spies. */
final class TypePdfScopeTest extends TestCase
{
    /** @return void Load each raw display method in a separate fixture class. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/type_pdf_environment.php';

        foreach (['typevenues', 'typeevents'] as $view) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.raw.php');
            self::assertSame(1, preg_match('/public function display\([^\n]*\)\s*\{.*?^    \}/ms', $source, $method));
            eval('namespace JemProject\Tests\TypePdf; final class ' . $view
                . ' extends ViewDependencies {' . $method[0] . '}');
        }
    }

    /** @return iterable<string, array{string, string}> Views and resolved-state scenarios. */
    public static function scenarios(): iterable
    {
        foreach (['typevenues', 'typeevents'] as $view) {
            foreach (['missing', 'not_found', 'valid', 'valid_empty', 'resolved_without_url_id',
                'blank_name', 'denied_redirect', 'denied_exception', 'non_pdf'] as $case) {
                yield $view . ':' . $case => [$view, $case];
            }
        }
    }

    /**
     * Check empty-type handling, existing exports and access/non-PDF early exits.
     *
     * @param string $view Raw view under test.
     * @param string $case Model/access response to simulate.
     * @return void
     */
    #[DataProvider('scenarios')]
    public function testOnlyResolvedAccessibleTypesRetrieveItems(string $view, string $case): void
    {
        $type = match ($case) {
            'missing' => null,
            'not_found' => false,
            default => (object) [
                'id' => 17,
                'name' => $case === 'blank_name' ? ' ' : 'Selected type',
                'user_has_access_type' => str_starts_with($case, 'denied') ? 0 : 1,
            ],
        };
        $items = $case === 'valid_empty' ? [] : [(object) ['id' => 101], (object) ['id' => 102]];
        $model = new Model($type, $items);
        Factory::$app = new Application($case === 'non_pdf' ? 'default' : 'pdf');
        JemFrontendAccess::$calls = [];
        JemFrontendAccess::$throwOnDenial = $case === 'denied_exception';
        JemPdfView::$output = null;
        $class = 'JemProject\\Tests\\TypePdf\\' . $view;
        $instance = new $class($model);
        $exception = null;

        try {
            $instance->display();
        } catch (RuntimeException $error) {
            $exception = $error;
        }

        if ($case === 'non_pdf') {
            self::assertTrue(Factory::$app->closed);
            self::assertSame(0, $model->typeCalls);
            self::assertSame(0, $model->itemCalls);
            self::assertSame([], $model->state);
            self::assertSame([], JemFrontendAccess::$calls);
            self::assertNull(JemPdfView::$output);
            self::assertNull($exception);

            return;
        }

        self::assertFalse(Factory::$app->closed);
        self::assertSame(1, $model->typeCalls);
        self::assertSame(['list.start' => 0, 'list.limit' => 0], $model->state);
        self::assertSame($type ? [(bool) $type->user_has_access_type] : [], JemFrontendAccess::$calls);

        if (str_starts_with($case, 'denied')) {
            self::assertSame(0, $model->itemCalls);
            self::assertNull(JemPdfView::$output);
            if ($case === 'denied_exception') {
                self::assertInstanceOf(RuntimeException::class, $exception);
                self::assertSame(403, $exception->getCode());
            } else {
                self::assertNull($exception);
            }

            return;
        }

        self::assertNull($exception);
        self::assertSame($type ? 1 : 0, $model->itemCalls, 'An unresolved type must not load the general list.');
        self::assertNotNull(JemPdfView::$output);
        self::assertSame($type, JemPdfView::$output['type']);
        self::assertSame($type ? $items : [], JemPdfView::$output['rows']);
        $isVenue = $view === 'typevenues';
        self::assertSame($isVenue ? 'venue' : 'event', JemPdfView::$output['kind']);
        self::assertSame($isVenue ? 'jem-type-venues.pdf' : 'jem-type-events.pdf', JemPdfView::$output['file']);
        $title = $type && trim($type->name) !== ''
            ? ($isVenue ? 'COM_JEM_TYPEVENUES_TITLE' : 'COM_JEM_TYPEEVENTS_TITLE') . ':' . $type->name
            : ($isVenue ? 'COM_JEM_VENUES' : 'COM_JEM_EVENTS');
        self::assertSame($title, JemPdfView::$output['title']);
    }
}
