<?php

declare(strict_types=1);

namespace JemProject\Tests\VenueState;

use RuntimeException;

/** Isolated state dependencies; no Joomla installation or database is required. */
final class Registry
{
    /** @param array<string, mixed> $values Initial settings/state. */
    public function __construct(private array $values = [])
    {
    }

    /** @param string $key Setting. @param mixed $default Fallback. @return mixed Stored value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Setting. @param mixed $value New value. @return void */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }
}

final class InputFilter
{
    /** @return self Isolated input filter. */
    public static function getInstance(): self
    {
        return new self();
    }

    /** @param mixed $value Input. @param string $type Filter name. @return mixed Filtered fixture value. */
    public function clean(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            'array' => (array) $value,
            'cmd' => preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $value),
            'word' => preg_replace('/[^A-Za-z_]/', '', (string) $value),
            default => (string) $value,
        };
    }
}

final class Input
{
    /** @param array<string, mixed> $values Request fields. */
    public function __construct(private array $values)
    {
    }

    /** @param string $key Field. @param mixed $default Fallback. @param string $type Filter. @return mixed Value. */
    public function get(string $key, mixed $default = null, string $type = 'string'): mixed
    {
        return isset($this->values[$key]) ? InputFilter::getInstance()->clean($this->values[$key], $type) : $default;
    }

    /** @param string $name Typed getter. @param array $args Field and fallback. @return mixed Filtered value. */
    public function __call(string $name, array $args): mixed
    {
        return $this->get($args[0], $args[1] ?? null, strtolower(substr($name, 3)));
    }
}

final class Application
{
    public array $session = [];
    public Input $input;
    public Registry $params;

    /** @return void Initialize isolated request/menu state. */
    public function __construct()
    {
        $this->input = new Input([]);
        $this->params = new Registry(['showcountryfilter' => 1]);
    }

    /** @param string|null $component Component name. @return Registry Menu parameters. */
    public function getParams(?string $component = null): Registry
    {
        return $this->params;
    }

    /** @param string $key Session key. @param mixed $value Value. @return void */
    public function setUserState(string $key, mixed $value): void
    {
        $this->session[$key] = $value;
    }

    /**
     * Mirror Joomla's absent-input versus explicitly empty-input state contract.
     *
     * @param string $key Session key.
     * @param string $request Request field.
     * @param mixed $default Fallback.
     * @param string $type Input filter.
     * @return mixed Saved or submitted state.
     */
    public function getUserStateFromRequest(string $key, string $request, mixed $default = null, string $type = 'string'): mixed
    {
        $value = $this->input->get($request, null, $type);
        if ($value === null) {
            return $this->session[$key] ?? $default;
        }
        $this->setUserState($key, $value);

        return $value;
    }
}

final class Factory
{
    public static Application $application;

    /** @return Application Current isolated application. */
    public static function getApplication(): Application
    {
        return self::$application;
    }
}

final class JemHelper
{
    /** @return object Synthetic component configuration. */
    public static function config(): object
    {
        return (object) ['display_num' => 20, 'showlocate' => 1];
    }
}

class ModelDependencies
{
    public string $context = 'com_jem.venueslist';
    private Registry $state;

    /** @return void Run the extracted production state-population method. */
    public function __construct()
    {
        $this->state = new Registry();
        $this->populateState();
    }

    /** @param string $key State key. @param mixed $value State value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state->set($key, $value);
    }

    /** @param string|null $key State key. @param mixed $default Fallback. @return mixed State or value. */
    public function getState(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->state : $this->state->get($key, $default);
    }

    /**
     * Model the relevant Joomla parent behavior: generic filters are restored last.
     * This is not a complete ListModel or SQL fixture.
     *
     * @param mixed $ordering Default framework order.
     * @param mixed $direction Default framework direction.
     * @return void
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        foreach ($app->getUserStateFromRequest($this->context . '.filter', 'filter', [], 'array') as $key => $value) {
            $this->setState('filter.' . $key, $value);
        }
        $this->setState('list.ordering', $ordering);
        $this->setState('list.direction', $direction);
    }
}

final class Text
{
    /** @param string $key Language key. @return string Untranslated fixture label. */
    public static function _(string $key): string
    {
        return $key;
    }
}

final class JemHelperCountries
{
    /** @return array<object> Synthetic catalog; production model performs menu restriction/normalization. */
    public static function getCountryOptions(): array
    {
        return [
            (object) ['value' => 'ES', 'text' => 'Spain'],
            (object) ['value' => 'DE', 'text' => 'Germany'],
            (object) ['value' => '', 'text' => 'Invalid country'],
        ];
    }
}

final class HTMLHelper
{
    /** @param string $key Helper name. @param mixed ...$args Arguments. @return mixed Synthetic option/select. */
    public static function _(string $key, mixed ...$args): mixed
    {
        if ($key === 'select.option') {
            return (object) ['value' => $args[0], 'text' => $args[1]];
        }
        if ($key !== 'select.genericlist') {
            throw new RuntimeException('Unexpected helper: ' . $key);
        }
        [$options, $name, $attributes, $valueKey, $textKey, $selected] = $args;
        $id = $args[6] ?? $name;
        $html = '<select name="' . htmlspecialchars($name, ENT_QUOTES) . '" id="' . htmlspecialchars($id, ENT_QUOTES) . '"';
        foreach ($attributes as $attribute => $value) {
            $html .= ' ' . htmlspecialchars($attribute, ENT_QUOTES) . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
        }
        $html .= '>';
        foreach ($options as $option) {
            $html .= '<option value="' . htmlspecialchars((string) $option->$valueKey, ENT_QUOTES) . '"'
                . ((string) $selected === (string) $option->$valueKey ? ' selected="selected"' : '') . '>'
                . htmlspecialchars((string) $option->$textKey, ENT_QUOTES) . '</option>';
        }

        return $html . '</select>';
    }
}

final class ViewState
{
    /** @param Registry $state Populated model state. @param array<object>|null $countries Model country options. */
    public function __construct(private Registry $state, private ?array $countries = null)
    {
    }

    /** @param string $name Model request. @return mixed State or synthetic country options. */
    public function get(string $name): mixed
    {
        return match ($name) {
            'State' => $this->state,
            'CountryOptions' => $this->countries ?? [(object) ['value' => 'ES', 'text' => 'Spain'], (object) ['value' => 'DE', 'text' => 'Germany']],
            default => throw new RuntimeException('Unexpected model request: ' . $name),
        };
    }

    /**
     * Execute the actual view's filter/list preparation, excluding unrelated display setup.
     *
     * @param string $view Production view name.
     * @return array<string, mixed> Prepared list controls.
     */
    public function lists(string $view): array
    {
        $source = str_replace("\r\n", "\n", file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php'));
        if (!preg_match('/\n        (?:\$filter_order\s*=|\$state\s*=)/', $source, $match, PREG_OFFSET_CAPTURE)) {
            throw new RuntimeException('Missing view state preparation.');
        }
        $start = $match[0][1];
        $end = strpos($source, $view === 'venueslist' ? '        // pathway' : '        $typeName', $start);
        if ($end === false) {
            throw new RuntimeException('Missing view state boundary.');
        }
        $app = Factory::getApplication();
        $params = $app->getParams();
        $jemsettings = JemHelper::config();

        return eval('namespace JemProject\\Tests\\VenueState; '
            . substr($source, $start, $end - $start) . "\nreturn \$lists;");
    }
}
