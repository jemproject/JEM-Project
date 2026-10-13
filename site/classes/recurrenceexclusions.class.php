<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Date-only exclusions for generated occurrences, independent of calendar visibility. */
final class JemRecurrenceExclusions
{
    public static function normalise($values): array
    {
        if (!is_array($values) || count($values) > 200) {
            throw new InvalidArgumentException('Invalid recurrence exclusions');
        }

        $result = [];
        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }
            if (!is_string($value) || !preg_match('/^(day|type):([1-9][0-9]{0,9})$/D', $value)
                || (int) substr($value, strpos($value, ':') + 1) > 2147483647) {
                throw new InvalidArgumentException('Invalid recurrence exclusion');
            }
            $result[$value] = $value;
        }
        sort($result, SORT_STRING);

        return array_values($result);
    }

    public static function fromAttribs($attribs): array
    {
        if (!is_array($attribs)) {
            $attribs = trim((string) $attribs) === '' ? [] : json_decode($attribs, true, 512, JSON_THROW_ON_ERROR);
        }
        if (!is_array($attribs)) {
            throw new InvalidArgumentException('Invalid event parameters');
        }

        return self::normalise($attribs['recurrence_exclusions'] ?? []);
    }

    /** Options respect the editor's view levels; background generation never uses them. */
    public static function options($db, array $levels): array
    {
        $levels = implode(',', array_map('intval', $levels ?: [0]));
        $result = [];
        foreach (['type' => ['#__jem_types', 'name'], 'day' => ['#__jem_special_days', 'title']] as $kind => $source) {
            $query = $db->getQuery(true)->select(['id', $source[1] . ' AS title'])
                ->from($db->quoteName($source[0]))->where('published = 1')->where('access IN (' . $levels . ')');
            if ($kind === 'type') {
                $query->where('entity = 4');
            }
            $query->order('ordering ASC, ' . $source[1] . ' ASC');
            foreach ($db->setQuery($query)->loadAssocList() as $row) {
                $result[$kind . ':' . (int) $row['id']] = ['kind' => $kind, 'title' => $row['title']];
            }
        }

        return $result;
    }

    /** One query per series, never per occurrence. Unpublished/deleted entries do not exclude. */
    public static function loadRows($db, array $selection): array
    {
        $ids = ['day' => [], 'type' => []];
        foreach (self::normalise($selection) as $token) {
            [$kind, $id] = explode(':', $token);
            $ids[$kind][] = (int) $id;
        }
        $conditions = [];
        if ($ids['day']) {
            $conditions[] = 'd.id IN (' . implode(',', $ids['day']) . ')';
        }
        if ($ids['type']) {
            $conditions[] = '(d.day_type_id IN (' . implode(',', $ids['type']) . ') AND t.published = 1 AND t.entity = 4)';
        }
        if (!$conditions) {
            return [];
        }
        $query = $db->getQuery(true)->select(['d.start_date', 'd.end_date', 'd.weekdays'])
            ->from($db->quoteName('#__jem_special_days', 'd'))
            ->join('LEFT', $db->quoteName('#__jem_types', 't') . ' ON t.id = d.day_type_id')
            ->where('d.published = 1')->where('(' . implode(' OR ', $conditions) . ')');

        // Let database errors reach the caller: never silently generate on excluded dates.
        return $db->setQuery($query)->loadAssocList();
    }

    /** Any date occupied by the occurrence may match; endpoints are inclusive. */
    public static function matches(array $rows, string $start, ?string $end = null): bool
    {
        $first = self::date($start);
        $last = empty($end) || $end === '0000-00-00' ? $first : self::date($end);
        if ($last < $first) {
            throw new InvalidArgumentException('Invalid occurrence date range');
        }

        foreach ($rows as $row) {
            $weekdays = trim((string) ($row['weekdays'] ?? ''));
            $from = empty($row['start_date']) || $row['start_date'] === '0000-00-00' ? null : self::date($row['start_date']);
            $until = empty($row['end_date']) || $row['end_date'] === '0000-00-00' ? null : self::date($row['end_date']);
            if ($from === null && $until === null && $weekdays === '') {
                continue;
            }
            if ($until === null && $weekdays === '') {
                $until = $from;
            }
            $a = $from !== null && $from > $first ? $from : $first;
            $b = $until !== null && $until < $last ? $until : $last;
            if ($a > $b) {
                continue;
            }
            if ($weekdays === '') {
                return true;
            }
            if (!preg_match('/^[0-6](,[0-6])*$/D', $weekdays)) {
                throw new InvalidArgumentException('Invalid special-day weekdays');
            }
            $allowed = array_map('intval', explode(',', $weekdays));
            // Seven checks suffice even for a long event or an unbounded weekly rule.
            for ($i = 0; $i < 7 && $a <= $b; $i++, $a = $a->modify('+1 day')) {
                if (in_array((int) $a->format('w'), $allowed, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid special-day date');
        }

        return $date;
    }
}
