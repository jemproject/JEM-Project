<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Helpers\FrontendVenueMap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise the real frontend model with persistence isolated, and render both real geodata blocks. */
final class FrontendVenueMapTest extends TestCase
{
    /** Load the production class body without bootstrapping its unrelated Joomla dependencies. */
    public static function setUpBeforeClass(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/editvenue.php');
        $start = strpos($source, 'class JemModelEditvenue extends JemModelVenue');
        if ($start === false) {
            throw new \RuntimeException('Frontend venue model declaration not found.');
        }

        eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start));
    }

    /** @return iterable<string, array{int, int, int, mixed}> Service, stored flag, ID and posted flag. */
    public static function saveCases(): iterable
    {
        foreach ([0, 1, 2, 3, 4, 5, 99] as $service) {
            foreach ([0, 1] as $stored) {
                foreach ([0, 7] as $id) {
                    foreach ([null, 0, 1, ['1']] as $posted) {
                        yield json_encode([$service, $stored, $id, $posted]) => [$service, $stored, $id, $posted];
                    }
                }
            }
        }
    }

    /**
     * Preserve unavailable preferences server-side, but let the parent validate editable controls.
     *
     * @param int $service Global service.
     * @param int $stored Database flag.
     * @param int $id Existing record ID, or zero for new/copied records.
     * @param mixed $posted Submitted checkbox value, or null when absent.
     * @return void
     */
    #[DataProvider('saveCases')]
    public function testSavePolicy(int $service, int $stored, int $id, mixed $posted): void
    {
        JemHelper::$service = $service;
        $model = new JemModelEditvenue();
        $model->stored = $stored;
        $data = ['id' => $id, 'access' => 1, 'venue' => 'Unchanged', 'latitude' => '40.4'];
        if ($posted !== null) {
            $data['map'] = $posted;
        }

        self::assertTrue($model->save($data));
        $editable = in_array($service, [1, 2, 3, 4, 5], true);
        $expected = $data;
        if (!$editable) {
            $expected['map'] = $id ? $stored : 0;
        }
        self::assertSame($expected, $model->saved);
        self::assertSame(!$editable && $id > 0 ? [7] : [], $model->loaded);
    }

    /** Missing records must fail rather than silently become new records. */
    public function testMissingStoredVenueFails(): void
    {
        JemHelper::$service = 0;
        $model = new JemModelEditvenue();
        $model->loadResult = false;
        self::assertFalse($model->save(['id' => 7, 'access' => 1, 'map' => 1]));
        self::assertNull($model->saved);
        self::assertSame('COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND', $model->error);
    }

    /** Keep the existing access check ahead of record loading and persistence. */
    public function testAccessValidationRemainsFirst(): void
    {
        JemHelper::$service = 0;
        $model = new JemModelEditvenue();
        self::assertFalse($model->save(['id' => 7, 'access' => 3]));
        self::assertNull($model->saved);
        self::assertSame([], $model->loaded);
        self::assertSame('COM_JEM_FRONTEND_ACCESS_INVALID', $model->error);
    }

    /** Do not conceal a parent-model validation failure. */
    public function testParentFailureIsPropagated(): void
    {
        JemHelper::$service = 0;
        $model = new JemModelEditvenue();
        $model->saveResult = false;
        self::assertFalse($model->save(['id' => 7, 'access' => 1]));
    }

    /** @return iterable<string, array{string, int, int}> Real template, global mode and bound checkbox value. */
    public static function renderCases(): iterable
    {
        foreach (['edit.php', 'responsive/edit.php'] as $layout) {
            foreach ([0, 1, 2, 3, 4, 5, 99] as $service) {
                foreach ([0, 1] as $stored) {
                    yield $layout . ':' . $service . ':' . $stored => [$layout, $service, $stored];
                }
            }
        }
    }

    /** Render the real template block with minimal form/translation dependencies. */
    #[DataProvider('renderCases')]
    public function testControls(string $layout, int $service, int $stored): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/editvenue/tmpl/' . $layout);
        $start = strpos($source, '<!-- VENUE-GEODATA-->');
        self::assertNotFalse($start);
        $end = strpos($source, '<?php endif; ?>', $start);
        self::assertNotFalse($end);
        $block = substr($source, $start, $end + strlen('<?php endif; ?>') - $start);
        $view = new MapViewFixture($service, $stored);
        $html = $view->render($block);
        $editable = in_array($service, [1, 2, 3, 4, 5], true);
        self::assertSame($editable ? 1 : 0, substr_count($html, 'name="jform[map]"'));
        self::assertStringNotContainsString('type="hidden" name="jform[map]"', $html);
        self::assertSame($editable && $stored === 1, str_contains($html, ' checked'));
        self::assertSame(in_array($service, [2, 3, 5], true), str_contains($html, 'id="mapdiv"'));
    }
}

/** Persistence fixture: production save must pass its unmodified remaining data to this parent. */
class JemModelVenue
{
    public ?array $saved = null;
    public array $loaded = [];
    public int $stored = 1;
    public bool $loadResult = true;
    public bool $saveResult = true;
    public ?string $error = null;

    /** @param array $data Data passed to parent persistence. @return bool Simulated persistence result. */
    public function save($data)
    {
        $this->saved = $data;
        return $this->saveResult;
    }

    /** @return object Record-loading fixture. */
    public function getTable()
    {
        return new class ($this) {
            public int $map = 0;
            /** @param JemModelVenue $model Fixture state. */
            public function __construct(private JemModelVenue $model)
            {
            }
            /** @param int $id Requested record. @return bool Simulated load result. */
            public function load(int $id): bool
            {
                $this->model->loaded[] = $id;
                $this->map = $this->model->stored;
                return $this->model->loadResult;
            }
        };
    }

    /** @param string $error Error supplied by production code. @return void */
    public function setError(string $error): void
    {
        $this->error = $error;
    }
}

final class JemHelper
{
    public static int $service = 0;
    /** @return object Global setting fixture. */
    public static function globalattribs(): object
    {
        return new class {
            /** @param string $key Setting name. @param mixed $default Fallback. @return int Global mode. */
            public function get(string $key, $default = null): int
            {
                return JemHelper::$service;
            }
        };
    }
}

final class JemFactory
{
    /** @return object Identity fixture. */
    public static function getUser(): object
    {
        return new \stdClass();
    }
}

final class JemFrontendAccess
{
    /** @param object $user Identity. @param mixed $access Requested level. @return bool Fixture access decision. */
    public static function canAssignViewLevel($user, $access): bool
    {
        return $access === 1;
    }
}

final class Text
{
    /** @param string $key Translation key. @return string Untranslated key for assertions. */
    public static function _(string $key): string
    {
        return $key;
    }
}

final class MapViewFixture
{
    public object $settings;
    public object $form;

    /** @param int $service Global mode. @param int $stored Bound map flag. */
    public function __construct(int $service, int $stored)
    {
        JemHelper::$service = $service;
        $this->settings = JemHelper::globalattribs();
        $this->form = new class ($stored) {
            /** @param int $stored Bound map flag. */
            public function __construct(private int $stored)
            {
            }
            /** @param string $field Field name. @return string Label fixture. */
            public function getLabel(string $field): string
            {
                return $field;
            }
            /** @param string $field Field name. @return string Bound checkbox fixture. */
            public function getInput(string $field): string
            {
                return '<input type="checkbox" name="jform[' . $field . ']" value="1"'
                    . ($this->stored ? ' checked' : '') . '>';
            }
        };
    }

    /** @param string $block Actual template block. @return string Rendered markup. */
    public function render(string $block): string
    {
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; ?>' . $block);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
