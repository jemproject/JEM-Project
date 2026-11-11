<?php
/** Isolated Joomla presentation boundaries for the actual Housekeeping template. */
declare(strict_types=1);

namespace Joomla\CMS;

final class Factory
{
    public static bool $administrator = false;

    /** @return object Read-only application and identity fixture. */
    public static function getApplication(): object
    {
        return new class {
            /** @return object Identity with the configured global administrator permission. */
            public function getIdentity(): object
            {
                return new class {
                    /** @param string $action Permission requested by the template. @return bool Fixture result. */
                    public function authorise(string $action): bool
                    {
                        return $action === 'core.admin' && Factory::$administrator;
                    }
                };
            }
        };
    }
}

namespace Joomla\CMS\Language;

final class Text
{
    /** @param string $key Language key. @return string Stable fixture label. */
    public static function _(string $key): string
    {
        return $key;
    }

    /** @param string $key Language key. @param mixed ...$values Arguments. @return string Fixture text. */
    public static function sprintf(string $key, mixed ...$values): string
    {
        return $key . ' ' . implode(' ', $values);
    }
}

namespace Joomla\CMS\HTML;

final class HTMLHelper
{
    /** @param string $name Helper requested. @param mixed ...$values Arguments. @return string Fixture markup. */
    public static function _(string $name, mixed ...$values): string
    {
        return $name === 'form.token' ? '<input type="hidden" name="csrf" value="fixture">' : '';
    }
}

namespace Joomla\CMS\Router;

final class Route
{
    /** @param string $url Component URL. @return string Escaped URL without an installed router. */
    public static function _(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}
