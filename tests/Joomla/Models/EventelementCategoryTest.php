<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Actual event selector getData and native Joomla MVC; controlled fetches, no site/database writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventelementCategoryTest extends TestCase
{
    private ?int $errorReporting = null;

    /** @return void Load native MVC libraries and turn unsuppressed PHP diagnostics into failures. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/admin/models/eventelement.php';
        require_once JEM_TEST_ROOT . '/tests/Support/eventelement_category_environment.php';
        $this->errorReporting = error_reporting(E_ALL);
        set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            throw new ErrorException($message, 0, $level, $file, $line);
        });
    }

    /** @return void Restore the previous PHP diagnostic configuration. */
    protected function tearDown(): void
    {
        if ($this->errorReporting !== null) {
            restore_error_handler();
            error_reporting($this->errorReporting);
        }
    }

    /** @return iterable<string, array{array, array, array, int, int}> Rows, category losses and expected IDs. */
    public static function categoryCases(): iterable
    {
        $cases = [
            'normal' => [[101, 102, 103], [], [101, 102, 103]],
            'first' => [[101, 102, 103], [101], [102, 103]],
            'middle' => [[101, 102, 103], [102], [101, 103]],
            'last' => [[101, 102, 103], [103], [101, 102]],
            'multiple' => [[101, 102, 103], [101, 102], [103]],
            'all' => [[101, 102, 103], [101, 102, 103], []],
            'empty' => [[], [], []],
            'sparse' => [[2 => 101, 5 => 102, 9 => 103], [102], [101, 103]],
            'full-page' => [[101, 102, 103, 104, 105], [103], [101, 102, 104, 105]],
        ];
        foreach ([[0, 5], [10, 5], [0, 0]] as [$offset, $limit]) {
            foreach ($cases as $name => [$rows, $missing, $expected]) {
                yield $name . '/' . $offset . '/' . $limit => [$rows, $missing, $expected, $offset, $limit];
            }
        }
    }

    /**
     * Exclude only rows without second-stage categories and cache the resulting list.
     *
     * @param array $rows First-stage event IDs keyed as returned by the fetch provider.
     * @param array $missing IDs with no categories on the second read.
     * @param array $expected Retained event IDs in their original order.
     * @param int $offset Request offset (not a claim of live pagination integration).
     * @param int $limit Request page size.
     * @return void
     */
    #[DataProvider('categoryCases')]
    public function testCategoryLoss(array $rows, array $missing, array $expected, int $offset, int $limit): void
    {
        $model = new EventelementCategoryFixture();
        $model->rows = $rows;
        $model->missingCategories = $missing;
        $model->offset = $offset;
        $model->limit = $limit;
        $result = $model->getData();

        self::assertSame($expected, array_column($result, 'id'));
        self::assertTrue(array_is_list($result));
        self::assertSame([['controlled event selector query', $offset, $limit]], $model->loads);
        self::assertSame(array_values($rows), $model->categoryCalls);
        foreach ($result as $row) {
            self::assertSame('Event ' . $row->id, $row->title);
            self::assertSame([7, 9], array_column($row->categories, 'id'));
            self::assertSame(['First', 'Second'], array_column($row->categories, 'catname'));
        }

        // Changing the provider must not invalidate a result already loaded by this instance.
        $model->rows = [999];
        $model->missingCategories = [];
        self::assertSame($result, $model->getData());
        self::assertCount(1, $model->loads);
        self::assertSame(array_values($rows), $model->categoryCalls);
    }

    /** @return iterable<string, array{mixed}> Non-array fetch failures retain the previous retry behavior. */
    public static function failedFetches(): iterable
    {
        yield 'false' => [false];
        yield 'null' => [null];
    }

    /**
     * Do not treat a failed/non-array fetch as a successful empty result.
     *
     * @param mixed $failure Simulated data-fetch failure value.
     * @return void
     */
    #[DataProvider('failedFetches')]
    public function testFailedFetchCanBeRetried($failure): void
    {
        $model = new EventelementCategoryFixture();
        $model->rows = [101];
        $model->fetchResults = [$failure];
        self::assertSame($failure, $model->getData());
        self::assertSame([], $model->categoryCalls);
        self::assertSame([101], array_column($model->getData(), 'id'));
        self::assertCount(2, $model->loads);
        self::assertSame([101], $model->categoryCalls);
    }

    /** @return void Preserve fetch exceptions instead of converting errors into an empty list. */
    public function testFetchExceptionIsPreserved(): void
    {
        $model = new EventelementCategoryFixture();
        $failure = new RuntimeException('Controlled fetch failure');
        $model->fetchResults = [$failure];
        try {
            $model->getData();
            self::fail('Expected the original fetch exception.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
            self::assertSame([], $model->categoryCalls);
        }
    }
}
