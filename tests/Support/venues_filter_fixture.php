<?php

declare(strict_types=1);

/**
 * Isolated rendering services for real Venues List and Type Venues templates.
 * No Joomla application, database, actual user, token or installed site is used.
 */
final class VenuesFilterServices
{
    public static string $layout = '';

    /** @param string $key Language/helper key. @param mixed ...$args Helper values. @return string Synthetic output. */
    public static function _(string $key, mixed ...$args): string
    {
        if ($key === 'form.token') {
            return '<input type="hidden" name="fixture_token" value="fixture-only">';
        }

        return htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
    }

    /** @param mixed $value Haystack. @param string $needle Suffix. @return bool Whether it occurs. */
    public static function jemStringContains(mixed $value, string $needle): bool
    {
        return str_contains((string) $value, $needle);
    }

    /** @return string Configured fixture layout style. */
    public static function getLayoutStyleSuffix(): string
    {
        return self::$layout;
    }

    /** @return self Isolated URI/user service. */
    public static function getInstance(): self
    {
        return new self();
    }

    /** @return self Non-editing fixture user. */
    public static function getUser(): self
    {
        return new self();
    }

    /** @param mixed ...$args Authorization arguments. @return bool Always deny edit links in this fixture. */
    public function can(mixed ...$args): bool
    {
        return false;
    }

    /** @param bool $pathOnly URI option. @return string Synthetic site root. */
    public static function root(bool $pathOnly = false): string
    {
        return '';
    }

    /** @return string Synthetic site base. */
    public function base(): string
    {
        return '/';
    }

    /** @param string $slug Venue slug. @return string Fixture route. */
    public static function getVenueRoute(string $slug): string
    {
        return '/venue/' . $slug;
    }

    /** @param mixed ...$args Button configuration. @return string No unrelated action buttons. */
    public static function createButtonBar(mixed ...$args): string
    {
        return '';
    }

    /** @return string No unrelated footer. */
    public static function footer(): string
    {
        return '';
    }

    /** @param object $row Synthetic venue. @return string No unrelated status markup. */
    public static function publishstateicon(object $row): string
    {
        return '';
    }
}

foreach ([
    'Joomla\CMS\Language\Text',
    'Joomla\CMS\HTML\HTMLHelper',
    'Joomla\CMS\Router\Route',
    'Joomla\CMS\Uri\Uri',
    'JemHelper',
    'JemFactory',
    'JemHelperRoute',
    'JemOutput',
] as $alias) {
    class_alias(VenuesFilterServices::class, $alias);
}

final class VenuesFilterRegistry
{
    /** @param array<string, mixed> $values Synthetic settings. */
    public function __construct(private array $values)
    {
    }

    /** @param string $key Setting. @param mixed $default Fallback. @return mixed Setting value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}

final class VenuesFilterPagination
{
    /** @return string Page-size control with the production onchange submission contract. */
    public function getLimitBox(): string
    {
        return '<select id="limit" name="limit" onchange="this.form.submit()">'
            . '<option value="10" selected>10</option><option value="20">20</option></select>';
    }

    /** @return string Synthetic pagination marker. */
    public function getPagesLinks(): string
    {
        return '<span data-fixture-pagination>Pages</span>';
    }
}

final class VenuesFilterFixture
{
    private array $templatePaths;
    public array $rows;
    public array $lists;
    public object $settings;
    public object $params;
    public object $jemsettings;
    public object $pagination;
    public object $permissions;
    public object $type;
    public int $novenues;
    public string $action;
    public string $pageclass_sfx = '';
    public string $task = '';
    public string $print_link = '';
    public string $pdf_link = '';

    /**
     * Build synthetic state; country availability matches each actual view.
     *
     * @param array<string, mixed> $options View, layout, suffix, control flags and empty/nonempty rows.
     */
    public function __construct(private array $options)
    {
        VenuesFilterServices::$layout = $options['layout'];
        $this->templatePaths = ['site/views/venueslist/tmpl'];

        if ($options['layout'] === 'responsive') {
            array_unshift($this->templatePaths, 'site/views/venueslist/tmpl/responsive');
        }

        $this->settings = new VenuesFilterRegistry([
            'global_show_filter' => $options['search'],
            'global_display' => $options['limit'],
        ]);
        $this->params = new VenuesFilterRegistry([
            'pageclass_sfx' => $options['suffix'],
            'showcountryfilter' => $options['country'],
            'showvenueimage' => 0, 'showtypes' => 0, 'showcity' => 0,
            'showstate' => 0, 'showcountry' => 0, 'showvenuemap' => 0,
            'showvenuecalendar' => 0, 'show_page_heading' => 0,
        ]);
        $this->lists = [
            'filter' => '<select id="filter_type" name="filter_type"><option value="2">Venue</option></select>',
            'search' => 'North "Hall" & Club',
            'country_filter' => $options['country']
                ? '<select id="filter_country" name="filter_country[]" onchange="this.form.submit()">'
                    . '<option value="">All</option><option value="ES" selected>Spain</option></select>'
                : '',
            'show_venue_event_filter' => $options['all'],
            'show_all_venues' => true,
            'order' => 'a.venue',
            'order_Dir' => 'ASC',
        ];
        $this->jemsettings = (object) [
            'tablewidth' => '100%', 'locationwidth' => '40%',
            'citywidth' => '20%', 'statewidth' => '20%', 'showlinkvenue' => 0,
        ];
        $this->pagination = new VenuesFilterPagination();
        $this->permissions = (object) [];
        $this->type = (object) ['id' => 7, 'name' => 'Fixture type', 'icon' => '', 'description' => ''];
        $this->rows = empty($options['rows']) ? [] : [(object) [
            'id' => 7, 'alias' => 'hall', 'venueslug' => '7:hall', 'venue' => 'Test hall',
            'user_has_access_venue' => true, 'created_by' => 0, 'odd' => 0, 'featured' => 0,
        ]];
        $this->novenues = $this->rows === [] ? 1 : 0;
        $this->action = '/submit?view=' . $options['view'] . '&id=7';
    }

    /** @return string Rendered real parent template and its list/filter partials. */
    public function render(): string
    {
        $relative = $this->options['view'] === 'typevenues'
            ? 'site/views/typevenues/tmpl/default.php'
            : 'site/views/venueslist/tmpl/' . ($this->options['layout'] ? 'responsive/' : '') . 'default.php';

        return $this->renderFile($relative);
    }

    /**
     * Resolve partials using Joomla's first-existing-path lookup order.
     *
     * @param string $name Partial suffix.
     *
     * @return string Rendered template.
     */
    public function loadTemplate(string $name): string
    {
        if (!in_array($name, ['venues', 'filters'], true)) {
            throw new RuntimeException('Unexpected fixture partial: ' . $name);
        }

        foreach ($this->templatePaths as $directory) {
            $relative = $directory . '/default_' . $name . '.php';

            if (is_file(JEM_TEST_ROOT . '/' . $relative)) {
                return $this->renderFile($relative);
            }
        }

        throw new RuntimeException('Missing fixture partial: ' . $name);
    }

    /** @param string $value Display value. @return string Escaped HTML. */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** @param string $path Production template path. @return void Prepend as Joomla HtmlView does. */
    public function addTemplatePath(string $path): void
    {
        $relative = str_replace(JPATH_SITE . '/components/com_jem/', 'site/', $path);

        if (!in_array($relative, ['site/views/venueslist/tmpl', 'site/views/venueslist/tmpl/responsive'], true)) {
            throw new RuntimeException('Unexpected fixture template path: ' . $path);
        }

        array_unshift($this->templatePaths, $relative);
    }

    /** @return string Synthetic view name. */
    public function getName(): string
    {
        return $this->options['view'];
    }

    /** @return array<object> Synthetic rows, already carrying their odd/even marker. */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Render source unchanged except its two external bootstrap includes.
     *
     * @param string $relative Whitelisted repository template path.
     *
     * @return string Rendered HTML; never loads an installed Joomla site.
     */
    private function renderFile(string $relative): string
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/' . $relative);
        $source = preg_replace(
            "~^require_once JPATH_SITE \\. '/components/com_jem/(?:helpers/countries|classes/image\\.class)\\.php';\\R~m",
            '',
            $source
        );
        ob_start();

        try {
            eval('?>' . $source);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
