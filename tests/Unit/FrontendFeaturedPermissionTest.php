<?php

declare(strict_types=1);

namespace JemProject\Tests\FeaturedPermission;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute the production guard with controlled persistence and permission boundaries. */
final class FrontendFeaturedPermissionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        self::assertSame(1, preg_match('/(protected function protectFrontendFeaturedData\(.*?)(?=\r?\n    \/\*\*)/s', $source, $match));
        eval('namespace ' . __NAMESPACE__ . '; final class Model extends ModelBoundary {' . $match[1] . '}');
    }

    protected function setUp(): void
    {
        JemFactory::$user = new UserBoundary();
    }

    public static function changes(): iterable
    {
        yield 'feature ordinary event' => [0, 1];
        yield 'unfeature existing event' => [1, 0];
        yield 'keep featured event' => [1, 1];
        yield 'keep ordinary event' => [0, 0];
    }

    #[DataProvider('changes')]
    public function testEditorsPreserveStoredFeaturedWithoutBlockingOtherChanges(int $stored, int $submitted): void
    {
        $model = new Model();
        $model->rows[10]['featured'] = $stored;
        $data = ['id' => 10, 'title' => 'Edited title', 'featured' => $submitted, 'created_by' => 999];
        self::assertTrue($model->protect($data, [99]));
        self::assertArrayNotHasKey('featured', $data);
        self::assertSame($stored, array_replace($model->rows[10], $data)['featured']);
        self::assertSame('Edited title', $data['title']);
        self::assertSame(['publish', 'event', 10, 7, [2, 3]], JemFactory::$user->calls[0]);
    }

    #[DataProvider('changes')]
    public function testPublishersCanChangeFeatured(int $stored, int $submitted): void
    {
        $model = new Model();
        JemFactory::$user->allowedRecords = [10];
        $data = ['id' => 10, 'featured' => $submitted];
        self::assertTrue($model->protect($data, [99]));
        self::assertSame($submitted, $data['featured']);
    }

    public function testOmittedFeaturedIsNotIntroducedForAnExistingEvent(): void
    {
        $data = ['id' => 10, 'title' => 'Unrelated edit'];
        self::assertTrue((new Model())->protect($data, [2, 3]));
        self::assertArrayNotHasKey('featured', $data);
        self::assertSame([], JemFactory::$user->calls);
    }

    public function testMissingStoredRecordRejectsTheSave(): void
    {
        $model = new Model();
        $data = ['id' => 404, 'featured' => 1];
        self::assertFalse($model->protect($data, [2]));
        self::assertSame('JLIB_APPLICATION_ERROR_RECORD_NOT_FOUND', $model->error);
        self::assertSame([], JemFactory::$user->calls);
    }

    public static function newEvents(): iterable
    {
        yield 'no category' => [[], [], 1, 0];
        yield 'unauthorized category' => [[2], [], 1, 0];
        yield 'mixed permissions' => [[2, 3], [2], 1, 0];
        yield 'all permitted' => [[2, 3], [2, 3], 1, 1];
        yield 'publisher chooses No' => [[2], [2], 0, 0];
        yield 'omitted field' => [[2], [], null, 0];
    }

    #[DataProvider('newEvents')]
    public function testNewEventsRequirePublicationPermissionInEverySelectedCategory(array $categories, array $allowed, ?int $value, int $expected): void
    {
        JemFactory::$user->allowedCategories = $allowed;
        $data = ['id' => 0];
        if ($value !== null) {
            $data['featured'] = $value;
        }
        self::assertTrue((new Model())->protect($data, $categories));
        self::assertSame($expected, $data['featured']);
    }

    public function testCopiesUseNewRecordPermissionsEvenWhenSourceIsFeatured(): void
    {
        foreach ([0, 10] as $sourceId) {
            $model = new Model();
            JemFactory::$user->allowedRecords = [10];
            $data = ['id' => $sourceId, 'featured' => 1];
            self::assertTrue($model->protect($data, [2], 'save2copy'));
            self::assertSame(0, $data['featured']);
            self::assertSame(0, JemFactory::$user->calls[array_key_last(JemFactory::$user->calls)][2]);
        }

        JemFactory::$user->allowedCategories = [2];
        $data = ['id' => 10, 'featured' => 1];
        self::assertTrue((new Model())->protect($data, [2], 'save2copy'));
        self::assertSame(1, $data['featured']);
    }

    public function testRecurrenceDiffDoesNotPropagateAProtectedRootValue(): void
    {
        $model = new Model();
        $model->rows[10]['featured'] = 1;
        $model->rows[11]['featured'] = 0;
        $data = ['id' => 10, 'featured' => 0, 'title' => 'Changed series'];
        self::assertTrue($model->protect($data, [2]));
        $diff = array_diff_assoc($data, $model->rows[10]);
        self::assertArrayNotHasKey('featured', $diff);

        foreach ([10 => 1, 11 => 0] as $id => $expected) {
            $event = array_replace($model->rows[$id], $diff);
            self::assertTrue($model->protect($event, [99]));
            self::assertSame($expected, array_replace($model->rows[$id], $event)['featured']);
        }
    }

    public function testEachOccurrenceRequiresItsOwnPublishPermission(): void
    {
        $model = new Model();
        JemFactory::$user->allowedRecords = [10];
        foreach ([10 => 1, 11 => 0] as $id => $expected) {
            $event = array_replace($model->rows[$id], ['featured' => 1]);
            self::assertTrue($model->protect($event, [2]));
            self::assertSame($expected, array_replace($model->rows[$id], $event)['featured']);
        }
        self::assertSame(['publish', 'event', 11, 8, [4]], JemFactory::$user->calls[1]);
    }

    public function testProductionSaveRoutesInvokeTheGuardBeforeWritingOrDiffing(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $early = strpos($source, 'if (!$backend && !$this->protectFrontendFeaturedData($data, $cats, $task))');
        self::assertNotFalse($early);
        self::assertLessThan(strpos($source, '$diff = array_diff_assoc('), $early);
        self::assertLessThan(strpos($source, 'parent::save($data)'), $early);

        $loop = strpos($source, 'foreach ($events as $event)');
        $guard = strpos($source, 'if (!$backend && !$this->protectFrontendFeaturedData($event, $cats))', $loop);
        self::assertNotFalse($guard);
        self::assertLessThan(strpos($source, 'parent::save($event)', $loop), $guard);
        self::assertSame(2, substr_count($source, 'if (!$backend && !$this->protectFrontendFeaturedData($eventData, $categories))'));
    }

    public function testFormDisablesFeaturedForNewEventsAndNonPublishers(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/view.html.php');
        $start = strpos($source, '        // A new event has no fixed category yet.');
        $end = strpos($source, '        // configure image field', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $render = eval('return function ($item) {' . substr($source, $start, $end - $start) . '};');
        foreach ([[0, false, true], [0, true, true], [10, false, true], [10, true, false]] as [$id, $publish, $disabled]) {
            $view = new ViewBoundary();
            $view->form = new FormBoundary();
            $item = (object) ['id' => $id, 'params' => new ParamsBoundary($publish)];
            $render->call($view, $item);
            self::assertSame($disabled, isset($view->form->attributes['featured']['disabled']));
        }
    }
}

class ModelBoundary
{
    public array $rows = [
        10 => ['id' => 10, 'title' => 'Root', 'featured' => 0, 'created_by' => 7],
        11 => ['id' => 11, 'title' => 'Child', 'featured' => 0, 'created_by' => 8],
    ];
    public ?string $error = null;

    public function getTable(): object
    {
        return new TableBoundary($this->rows);
    }

    protected function getEventCategoryIds($id): array
    {
        return $id === 10 ? [2, 3] : [4];
    }

    public function setError($error): void
    {
        $this->error = $error;
    }

    public function protect(array &$data, array $categories, string $task = ''): bool
    {
        return $this->protectFrontendFeaturedData($data, $categories, $task);
    }
}

final class TableBoundary
{
    public int $created_by = 0;

    public function __construct(private array $rows)
    {
    }

    public function load(int $id): bool
    {
        $this->created_by = $this->rows[$id]['created_by'] ?? 0;
        return isset($this->rows[$id]);
    }
}

final class UserBoundary
{
    public int $id = 15;
    public array $allowedRecords = [];
    public array $allowedCategories = [];
    public array $calls = [];

    public function can($action, $type, $id, $owner, $categories): bool
    {
        $this->calls[] = [$action, $type, $id, $owner, $categories];
        return $id > 0 ? in_array($id, $this->allowedRecords, true)
            : !array_diff($categories, $this->allowedCategories);
    }
}

final class JemFactory
{
    public static UserBoundary $user;

    public static function getUser(): UserBoundary
    {
        return self::$user;
    }
}

final class Text
{
    public static function _($key): string
    {
        return $key;
    }
}

final class FormBoundary
{
    public array $attributes = [];

    public function setFieldAttribute($field, $attribute, $value): void
    {
        $this->attributes[$field][$attribute] = $value;
    }
}

final class ViewBoundary
{
    public FormBoundary $form;
}

final class ParamsBoundary
{
    public function __construct(private bool $publish)
    {
    }

    public function get($key, $default): bool
    {
        return $this->publish;
    }
}
