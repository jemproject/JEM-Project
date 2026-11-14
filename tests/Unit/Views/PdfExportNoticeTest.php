<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/pdfexportnotice.class.php';

/** Bounded visibility counting with the shared state/cache semantics of Joomla models. */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PdfExportNoticeTest extends TestCase
{
    /** @return void Count after visibility filtering without retaining the complete collection. */
    public function testBoundedVisibleCountAndSample(): void
    {
        $model = new PdfExportNoticeModel(3000, 200);
        $before = clone $model->state;
        $result = JemPdfExportNotice::inspect($model, 100);
        self::assertSame(2798, $result['count']);
        self::assertCount(24, $result['sample']);
        foreach ($result['sample'] as $row) {
            self::assertGreaterThan(202, $row->id);
        }
        self::assertEquals($before, $model->state);
        self::assertSame([], $model->cache);
        self::assertCount(30, $model->calls->values);
        foreach ($model->calls->values as $call) {
            self::assertSame(100, $call['limit']);
            self::assertSame('archive+category+search', $call['scope']);
            self::assertTrue($call['skip']);
        }
    }

    /** @return iterable<string, array{int,int,int,bool}> Total, hidden count, threshold, notice. */
    public static function boundaries(): iterable
    {
        yield 'empty' => [0, 0, 100, false];
        yield 'small' => [15, 0, 100, false];
        yield 'below' => [99, 0, 100, false];
        yield 'at threshold' => [100, 0, 100, true];
        yield 'above' => [101, 0, 100, true];
        yield 'hidden rows do not count' => [150, 100, 100, false];
        yield 'higher configured threshold' => [150, 0, 200, false];
        yield 'zero setting is bounded' => [1, 0, 0, true];
    }

    /** @return void Honour the configured threshold and never display inaccessible SQL totals. */
    #[DataProvider('boundaries')]
    public function testThreshold(int $total, int $hidden, int $threshold, bool $warning): void
    {
        $model = new PdfExportNoticeModel($total, $hidden);
        $result = JemPdfExportNotice::inspect($model, $threshold);
        self::assertSame($warning, $result !== null);
        if ($result !== null) {
            self::assertSame($total - $hidden, $result['count']);
            self::assertLessThanOrEqual(25, count($result['sample']));
        }
        self::assertSame(40, $model->state->{'list.start'});
        self::assertSame(20, $model->state->{'list.limit'});
    }

    /** @return void Preserve the real export state when a preview batch fails. */
    public function testRestoreOnFailure(): void
    {
        $model = new PdfExportNoticeModel(250, 0);
        $model->failAt = 100;
        $before = clone $model->state;
        try {
            JemPdfExportNotice::inspect($model, 100);
            self::fail('Expected fixture failure');
        } catch (RuntimeException $e) {
            self::assertSame('page failed', $e->getMessage());
        }
        self::assertEquals($before, $model->state);
    }

    /** @return void Estimate follows sampled layout density and remains an approximation. */
    public function testPageEstimate(): void
    {
        self::assertSame(300, JemPdfExportNotice::estimatePages(3000, 25, 2.5));
        self::assertSame(600, JemPdfExportNotice::estimatePages(3000, 25, 5.0));
        self::assertSame(1, JemPdfExportNotice::estimatePages(0, 0, 0.0));
        self::assertSame(11, JemPdfExportNotice::estimatePages(101, 25, 2.5));
    }
    /** @return iterable<string, array{int,bool,bool}> Posted flag, token validity, expected confirmation. */
    public static function confirmations(): iterable
    {
        yield 'GET flag is insufficient' => [0, true, false];
        yield 'missing token' => [1, false, false];
        yield 'confirmed POST' => [1, true, true];
        yield 'invalid flag' => [2, true, false];
    }

    /** @return void Require an explicit POST and the session token. */
    #[DataProvider('confirmations')]
    public function testConfirmation(int $flag, bool $valid, bool $expected): void
    {
        $this->loadRequestBoundary();
        PdfNoticeFactory::$app->input->post->flag = $flag;
        PdfNoticeSession::$valid = $valid;
        self::assertSame($expected, JemPdfExportNotice::isConfirmed());
    }

    /** @return void Preserve filter links, escape labels, and mark estimates as approximate. */
    public function testNoticeHtml(): void
    {
        $this->loadRequestBoundary();
        ob_start();
        JemPdfExportNotice::display(3000, 292);
        $html = (string) ob_get_clean();
        self::assertStringContainsString('3000', $html);
        self::assertStringContainsString('292', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('name="fixture-token"', $html);
        self::assertStringContainsString('filter_search=A%26B', $html);
        self::assertStringContainsString('Itemid=7', $html);
        self::assertStringContainsString('task=archive', $html);
        self::assertStringContainsString('&amp;format=raw&amp;layout=pdf', $html);
        self::assertStringContainsString('&lt;label&gt;', $html);
        self::assertStringNotContainsString('<label>', $html);
        self::assertSame('private, no-store', PdfNoticeFactory::$app->headers['Cache-Control']);
        self::assertTrue(PdfNoticeFactory::$app->closed);
    }

    /** @return void Do not manufacture a page estimate if the preview cannot run. */
    public function testUnavailableEstimate(): void
    {
        $this->loadRequestBoundary();
        ob_start();
        JemPdfExportNotice::display(3000, null);
        $html = (string) ob_get_clean();
        self::assertStringContainsString('COM_JEM_PDF_LARGE_PAGES_UNKNOWN', $html);
        self::assertStringContainsString('COM_JEM_PDF_LARGE_GENERATE', $html);
    }

    /** @return void Load request/translation boundaries; production notice code is unchanged. */
    private function loadRequestBoundary(): void
    {
        class_alias(PdfNoticeFactory::class, 'Joomla\\CMS\\Factory');
        class_alias(PdfNoticeSession::class, 'Joomla\\CMS\\Session\\Session');
        class_alias(PdfNoticeText::class, 'Joomla\\CMS\\Language\\Text');
        class_alias(PdfNoticeRoute::class, 'Joomla\\CMS\\Router\\Route');
        class_alias(PdfNoticeOutput::class, 'JemOutput');
        PdfNoticeFactory::$app = new PdfNoticeApplication();
    }

}

/** A clone shares the state object but has its own per-page item cache, as in Joomla. */
final class PdfExportNoticeModel
{
    public object $state;
    public object $calls;
    public array $cache = [];
    public int $failAt = -1;

    /** @param int $total SQL total. @param int $hidden Rows excluded by model visibility checks. */
    public function __construct(private int $total, private int $hidden)
    {
        $this->state = (object) ['list.start' => 40, 'list.limit' => 20, 'list.skip_attendee_numbers' => false, 'scope' => 'archive+category+search'];
        $this->calls = (object) ['values' => []];
    }

    /** @return int SQL total. */
    public function getTotal(): int
    {
        return $this->total;
    }

    /** @param string $key State key. @return mixed Stored state. */
    public function getState(string $key): mixed
    {
        return $this->state->{$key};
    }

    /** @param string $key State key. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state->{$key} = $value;
    }

    /** @return array Visible rows for the requested page. */
    public function getItems(): array
    {
        $start = $this->state->{'list.start'};
        if ($start === $this->failAt) {
            throw new RuntimeException('page failed');
        }
        $limit = $this->state->{'list.limit'};
        $this->calls->values[] = ['limit' => $limit, 'scope' => $this->state->scope, 'skip' => $this->state->{'list.skip_attendee_numbers'}];
        $rows = [];
        for ($id = $start + 1; $id <= min($this->total, $start + $limit); ++$id) {
            if ($id > $this->hidden) {
                $rows[] = (object) ['id' => $id, 'user_has_access_category' => !($this->total === 3000 && in_array($id, [201, 202], true))];
            }
        }
        return $this->cache[$start] = $rows;
    }
}

/** Request services isolated from any installed Joomla site. */
final class PdfNoticeFactory
{
    public static PdfNoticeApplication $app;
    /** @return PdfNoticeApplication Current fixture request. */
    public static function getApplication(): PdfNoticeApplication
    {
        return self::$app;
    }
}
final class PdfNoticeSession
{
    public static bool $valid = false;
    /** @param string $method Input source. @return bool Fixture token validity. */
    public static function checkToken(string $method): bool
    {
        return $method === 'post' && self::$valid;
    }
    /** @return string Test-only token name. */
    public static function getFormToken(): string
    {
        return 'fixture-token';
    }
}
final class PdfNoticeText
{
    /** @param string $key Translation key. @return string Label with markup to escape. */
    public static function _(string $key): string
    {
        return $key . ' <label>';
    }
    /** @param string $key Translation key. @param int $value Count. @return string Formatted label. */
    public static function sprintf(string $key, int $value): string
    {
        return self::_($key) . ' ' . $value;
    }
}
final class PdfNoticeRoute
{
    /** @param string $url Internal URL. @param bool $xhtml Escaping switch. @return string URL. */
    public static function _(string $url, bool $xhtml): string
    {
        return $url;
    }
}
final class PdfNoticeOutput
{
    /** @param string $url Target. @param bool $current Current filter precedence. @return string Scoped target. */
    public static function prepareExportLink(string $url, bool $current): string
    {
        return $url . '&Itemid=7&task=archive&filter_search=A%26B';
    }
}
final class PdfNoticeApplication
{
    public object $input;
    public array $headers = [];
    public bool $closed = false;
    /** Initialise an independent POST fixture. */
    public function __construct()
    {
        $this->input = (object) ['post' => new PdfNoticePost()];
    }
    /** @param string $name Header. @param string $value Value. @param bool $replace Replace existing. @return void */
    public function setHeader(string $name, string $value, bool $replace): void
    {
        $this->headers[$name] = $value;
    }
    /** @return void No HTTP output in isolated tests. */
    public function sendHeaders(): void
    {
    }
    /** @return void Record response completion. */
    public function close(): void
    {
        $this->closed = true;
    }
    /** @return PdfNoticeLanguage Language fixture. */
    public function getLanguage(): PdfNoticeLanguage
    {
        return new PdfNoticeLanguage();
    }
}
final class PdfNoticeLanguage
{
    /** @return string Page language. */
    public function getTag(): string
    {
        return 'en-GB';
    }
    /** @return bool Text direction. */
    public function isRtl(): bool
    {
        return false;
    }
}
final class PdfNoticePost
{
    public int $flag = 0;
    /** @param string $key Input key. @param int $default Default. @return int Explicit POST value. */
    public function getInt(string $key, int $default): int
    {
        return $this->flag;
    }
}
