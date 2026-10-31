<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Validate visitor-controlled page sizes against Joomla's frontend Limit selector.
 */
final class JemListLimit
{
    public const OPTIONS = [5, 10, 15, 20, 25, 30, 50, 100, 200, 500, 0];

    /**
     * Resolve and repair a view's pagination without changing the request or global state.
     *
     * @param object $app Application providing input and view-scoped user state.
     * @param string $context Session key prefix for this list.
     * @param mixed $default Configured page size; invalid settings fall back to 10.
     * @param bool $enabled Whether visitors may select the page size.
     * @param bool $allowCustomDefault Whether the selector also offers a custom menu default.
     * @return array{0: int, 1: int} Validated limit and nonnegative aligned offset.
     */
    public static function resolve($app, string $context, $default, bool $enabled = true, bool $allowCustomDefault = false): array
    {
        $default = ($allowCustomDefault ? self::unsignedInteger($default) : self::pageSize($default)) ?? 10;
        $value = $enabled
            ? $app->getUserStateFromRequest($context . '.limit', 'limit', $default, 'raw')
            : $default;
        $limit = self::unsignedInteger($value) === $default ? $default : self::pageSize($value);
        $invalid = $limit === null;
        $limit = $limit ?? $default;
        $start = self::unsignedInteger($app->input->get('limitstart', 0, 'raw')) ?? 0;
        $start = !$invalid && $limit > 0 ? intdiv($start, $limit) * $limit : 0;

        $app->setUserState($context . '.limit', $limit);
        $app->setUserState($context . '.limitstart', $start);

        return [$limit, $start];
    }

    /**
     * Load framework sorting/filter state while keeping JEM pagination authoritative.
     *
     * @param object $model List model receiving the validated pagination.
     * @param object $app Application with session access.
     * @param string $context Framework model context, not the menu-specific JEM key.
     * @param array{0: int, 1: int} $page Validated limit and offset.
     * @param callable $populate Parent state loader, invoked in the model's scope.
     * @return void
     */
    public static function populate($model, $app, string $context, array $page, callable $populate): void
    {
        $globalLimit = $app->getUserState('global.list.limit');
        $original = [];
        foreach (['limit', 'limitstart', 'list'] as $name) {
            $original[$name] = $app->input->get($name, null, 'raw');
        }
        $list = $original['list'] ?? $app->getUserState($context . '.list', []);
        $list = is_array($list) ? $list : [];
        unset($list['limit'], $list['start'], $list['links']);
        $app->input->set('limit', $page[0]);
        $app->input->set('limitstart', $page[1]);
        $app->input->set('list', $list);

        try {
            $populate();
        } finally {
            // Do not leak temporary pagination input/state into other component models.
            foreach ($original as $name => $value) {
                $app->input->set($name, $value);
            }
            $app->setUserState('global.list.limit', $globalLimit);
        }

        // Nested list input belongs to Joomla's generic list API, not this selector.
        $list = (array) $app->getUserState($context . '.list', []);
        unset($list['limit'], $list['start'], $list['links']);
        $app->setUserState($context . '.list', $list);
        $app->setUserState($context . '.limitstart', $page[1]);
        $model->setState('list.limit', $page[0]);
        $model->setState('list.start', $page[1]);
        $model->setState('list.links', 0);
    }

    /**
     * Render the category selector, including its custom menu page size when needed.
     *
     * @param object $pagination Joomla pagination instance.
     * @param mixed $default Trusted menu configuration, never visitor input.
     * @return string Selector HTML with a visible option matching the configured default.
     */
    public static function getLimitBox($pagination, $default): string
    {
        $default = self::unsignedInteger($default) ?? 10;
        if (in_array($default, self::OPTIONS, true)) {
            return $pagination->getLimitBox();
        }

        $sizes = array_merge(self::OPTIONS, [$default]);
        sort($sizes, SORT_NUMERIC);
        $sizes = array_merge(array_filter($sizes), [0]);
        $options = [];
        foreach ($sizes as $size) {
            $options[] = \Joomla\CMS\HTML\HTMLHelper::_(
                'select.option',
                $size,
                $size === 0 ? \Joomla\CMS\Language\Text::_('JALL') : (string) $size
            );
        }

        return \Joomla\CMS\HTML\HTMLHelper::_(
            'select.genericlist',
            $options,
            $pagination->prefix . 'limit',
            'class="form-select" onchange="this.form.submit()"',
            'value',
            'text',
            $pagination->viewall ? 0 : $pagination->limit
        );
    }

    /**
     * Accept only page sizes actually offered by the standard Joomla selector.
     *
     * @param mixed $value Raw request, session, or configured value.
     * @return int|null Accepted size (including All = 0), or null for invalid input.
     */
    private static function pageSize($value): ?int
    {
        $number = self::unsignedInteger($value);

        return in_array($number, self::OPTIONS, true) ? $number : null;
    }

    /**
     * Parse an unsigned decimal integer without coercing malformed input to All.
     *
     * @param mixed $value Raw value to validate.
     * @return int|null Integer in the platform range, or null.
     */
    private static function unsignedInteger($value): ?int
    {
        if (!is_int($value) && (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/D', $value))) {
            return null;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $number === false ? null : $number;
    }
}
