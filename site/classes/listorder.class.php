<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Keep SQL ordering, persisted state and frontend controls in agreement. */
final class JemListOrder
{
    /**
     * Return real event-query columns and supported legacy names.
     *
     * @return array<string, string> Input name to trusted SQL column.
     */
    public static function eventFields(): array
    {
        $fields = [
            'a.id', 'a.title', 'a.dates', 'a.times', 'a.alias', 'l.venue', 'l.city', 'l.state',
            'l.country', 'c.catname', 'a.created', 'a.created_by', 'a.published', 'a.access',
            'a.ordering', 'a.featured', 'a.language', 'a.hits', 'a.publish_up', 'a.publish_down',
            'a.checked_out', 'a.checked_out_time', 'a.event_status', 'a.ticket_availability',
        ];
        $allowed = array_combine($fields, $fields);
        foreach ($fields as $field) {
            if (str_starts_with($field, 'a.')) {
                $allowed[substr($field, 2)] = $field;
            }
        }

        return $allowed + [
            'venue' => 'l.venue', 'venue_title' => 'l.venue',
            'city' => 'l.city', 'venue_city' => 'l.city',
            'category_title' => 'c.catname', 'access_level' => 'a.access',
            'state' => 'a.published', 'a.state' => 'a.published',
        ];
    }

    /**
     * Accept only a caller-owned column; never interpret request text as SQL.
     *
     * @param mixed $value Request or saved field, including malformed arrays.
     * @param array $allowed List of columns or input-name to column map.
     * @param string $fallback Trusted default column.
     * @return string Validated canonical column.
     */
    public static function field($value, array $allowed, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }
        $value = trim($value);
        if (array_is_list($allowed)) {
            return in_array($value, $allowed, true) ? $value : $fallback;
        }

        return $allowed[$value] ?? $fallback;
    }

    /**
     * Normalise a direction without accepting SQL fragments.
     *
     * @param mixed $value Request or saved direction.
     * @param string $fallback Default direction.
     * @return string ASC or DESC.
     */
    public static function direction($value, string $fallback = 'ASC'): string
    {
        $fallback = strtoupper(trim($fallback)) === 'DESC' ? 'DESC' : 'ASC';
        $value = is_string($value) ? strtoupper(trim($value)) : '';

        return in_array($value, ['ASC', 'DESC'], true) ? $value : $fallback;
    }

    /**
     * Resolve request/session ordering and save only the validated pair.
     *
     * @param object $app Joomla application with input and user-state storage.
     * @param string $context Existing list-specific session namespace.
     * @param array $allowed Trusted columns for this query.
     * @param string $defaultField Default field.
     * @param string $defaultDirection Default direction for non-date ordering.
     * @param bool $archive Use descending dates by default, but honour an explicit valid direction.
     * @return array{string, string} Canonical field and direction.
     */
    public static function resolve(
        $app,
        string $context,
        array $allowed,
        string $defaultField,
        string $defaultDirection = 'ASC',
        bool $archive = false
    ): array {
        $fieldKey = $context . '.filter_order';
        $directionKey = $context . '.filter_order_Dir';
        $requestedField = $app->input->get('filter_order', null, 'raw');
        $field = self::field(
            $requestedField ?? $app->getUserState($fieldKey, $defaultField),
            $allowed,
            $defaultField
        );
        // Determine the date fallback after normalising a missing/invalid column.
        $fallback = $archive && $field === 'a.dates' ? 'DESC' : $defaultDirection;
        $requestedDirection = $app->input->get('filter_order_Dir', null, 'raw');
        $direction = self::direction(
            $requestedDirection ?? $app->getUserState($directionKey, $fallback),
            $fallback
        );
        $app->setUserState($fieldKey, $field);
        $app->setUserState($directionKey, $direction);

        return [$field, $direction];
    }
}
