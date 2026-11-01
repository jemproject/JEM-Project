<?php
/** Isolated native Joomla database/table services for the Types language policy tests. */
declare(strict_types=1);

namespace JemProject\Tests\TypeLanguage;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

final class Database extends \Joomla\Database\Sqlite\SqliteDriver
{
    /** @return bool Availability of the disposable SQLite PDO driver. */
    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }

    /** @param mixed $text Value. @param bool $extra LIKE mode. @return string Escaped SQL text. */
    public function escape($text, $extra = false)
    {
        $this->connect();
        return substr($this->connection->quote((string) $text), 1, -1);
    }
}

final class User
{
    public int $id = 1;

    /** @return array<int> Public view levels; query ACL checks are not stubbed. */
    public function getAuthorisedViewLevels(): array
    {
        return [1];
    }
}

final class Application
{
    public string $tag = 'es-ES';

    /** @return object Minimal current-language service. */
    public function getLanguage(): object
    {
        return new class ($this->tag) {
            /** @param string $tag Current display language. */
            public function __construct(private string $tag)
            {
            }

            /** @return string Current display language. */
            public function getTag(): string
            {
                return $this->tag;
            }
        };
    }

    /** @return User Fixture viewer. */
    public function getIdentity(): User
    {
        return new User();
    }

    /** @return \Joomla\Event\Dispatcher Empty native event dispatcher, no installed plugins. */
    public function getDispatcher(): \Joomla\Event\Dispatcher
    {
        return new \Joomla\Event\Dispatcher();
    }

    /** @param string $key Setting. @param mixed $default Default. @return mixed Setting. */
    public function get(string $key, mixed $default = null): mixed
    {
        return ['access' => 1, 'unicodeslugs' => 1][$key] ?? $default;
    }
}

final class JemFactory
{
    /** @return User Isolated authenticated identity. */
    public static function getUser(): User
    {
        return new User();
    }
}

class ModelBoundary
{
    protected int $_typeid = 1;
    protected bool $_typeFilterRequested = true;
    protected mixed $_type = null;
    protected mixed $_types = null;
    public int $id = 1;

    /** @param string $key State key. @param mixed $default Fallback. @return mixed State. */
    public function getState(string $key, mixed $default = null): mixed
    {
        return $key === 'filter.type_id' ? $this->id : $default;
    }

    /** @param string $key State key. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void
    {
    }

    /** @param int $id Requested category Type. @return void */
    public function selectType(int $id): void
    {
        $this->_typeid = $id;
    }
}

class HelperBoundary
{
    /** @return Registry Special Days settings, unrelated to language selection. */
    public static function globalattribs(): Registry
    {
        return new Registry(['calendar_special_days_enabled' => 1]);
    }
}

/**
 * Extract unchanged production methods, isolating only filesystem dependency loading.
 *
 * @param string $file Source relative to the checkout.
 * @param array<string> $names Methods to exercise.
 * @return string Actual method declarations.
 */
function methods(string $file, array $names): string
{
    $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
    $result = '';
    foreach ($names as $name) {
        if (!preg_match('/    (?:(?:static|public|protected|private)\s+)+function '
            . preg_quote($name, '/') . '\([^\n]*\)[^\n]*\n    \{.*?^    \}/ms', $source, $match)) {
            throw new \RuntimeException('Missing method: ' . $file . ':' . $name);
        }
        $result .= preg_replace('/^ *require_once .*output\.class\.php.*;\R/m', '', $match[0]) . "\n";
    }
    return $result;
}

/** @return void Load actual translation, query, Special Day and installer methods. */
function loadMethods(): void
{
    $imports = 'namespace ' . __NAMESPACE__ . '; use Joomla\CMS\Factory; use Joomla\Registry\Registry;'
        . ' use \DateTimeImmutable; use \Exception; use \RuntimeException; use Joomla\CMS\Application\ApplicationHelper;';
    eval($imports . ' class JemOutput {'
        . methods('site/classes/output.class.php', ['translateType', 'getTypeTranslationValue']) . '}');
    foreach (['EventModel' => 'typeevents', 'VenueModel' => 'typevenues', 'CategoryModel' => 'categories'] as $class => $file) {
        eval($imports . ' class ' . $class . ' extends ModelBoundary {'
            . methods('site/models/' . $file . '.php', $file === 'categories' ? ['getType', 'getTypes'] : ['getType']) . '}');
    }
    eval($imports . ' class Installer {' . methods('script.php', ['normaliseTypeLanguages']) . '}');
    eval($imports . ' class JemHelper extends HelperBoundary {'
        . methods('site/helpers/helper.php', ['calendarSpecialDayTypes', 'loadCalendarSpecialDayTypesFromTable',
            'calendarSpecialDayTypesById', 'resolveCalendarSpecialDayType', 'calendarSpecialDays',
            'calendarSpecialDayLegend', 'calendarSpecialDayTypeClass']) . '}');
}

