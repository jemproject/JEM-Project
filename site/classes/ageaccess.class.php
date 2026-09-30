<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

/**
 * Resolves JEM age classifications and the current user's declared age.
 *
 * The date of birth is read from Joomla's standard User Profile value
 * `profile.dob`. It is a user-declared value, not proof of identity.
 */
final class JemAgeAccess
{
    public const MAX_SUPPORTED_AGE = 99;
    public const UNRESTRICTED = 'unrestricted';
    public const GUEST = 'guest';
    public const ELIGIBLE = 'eligible';
    public const UNKNOWN = 'unknown';
    public const RESTRICTED = 'restricted';

    private static $birthDates = array();

    /**
     * Return the stricter non-null minimum age.
     */
    public static function effectiveMinAge($eventMinAge, $venueMinAge): ?int
    {
        $ages = array();

        foreach (array($eventMinAge, $venueMinAge) as $age) {
            if ($age !== null && $age !== '') {
                $ages[] = max(0, (int) $age);
            }
        }

        return $ages ? max($ages) : null;
    }

    /**
     * Return the stricter non-null maximum age.
     */
    public static function effectiveMaxAge($eventMaxAge, $venueMaxAge): ?int
    {
        $ages = array();

        foreach (array($eventMaxAge, $venueMaxAge) as $age) {
            if ($age !== null && $age !== '') {
                $ages[] = min(self::MAX_SUPPORTED_AGE, max(0, (int) $age));
            }
        }

        return $ages ? min($ages) : null;
    }

    public static function isAgeWithinRange(int $age, int $minimumAge, int $maximumAge): bool
    {
        return $minimumAge <= $maximumAge && $age >= $minimumAge && $age <= $maximumAge;
    }

    public static function formatRange(int $minimumAge, int $maximumAge): string
    {
        return $maximumAge < self::MAX_SUPPORTED_AGE
            ? $minimumAge . '-' . $maximumAge
            : $minimumAge . '+';
    }

    /**
     * Calculate complete years on the event date.
     */
    public static function ageOnDate(string $birthDate, string $eventDate): ?int
    {
        $birth = self::createDate($birthDate);
        $event = self::createDate($eventDate);

        if (!$birth || !$event || $birth > $event) {
            return null;
        }

        return (int) $birth->diff($event)->y;
    }

    /**
     * Assess access for one inclusive age range.
     */
    public static function assess($user, $minimumAge, string $eventDate, $maximumAge = null): string
    {
        if (($minimumAge === null || $minimumAge === '')
            && ($maximumAge === null || $maximumAge === '')) {
            return self::UNRESTRICTED;
        }

        $minimumAge = $minimumAge === null || $minimumAge === '' ? 0 : max(0, (int) $minimumAge);
        $maximumAge = $maximumAge === null || $maximumAge === ''
            ? self::MAX_SUPPORTED_AGE
            : min(self::MAX_SUPPORTED_AGE, max(0, (int) $maximumAge));

        $userId = self::userValue($user, 'id', 0);
        $guest = (bool) self::userValue($user, 'guest', $userId < 1);

        if ($userId < 1 || $guest) {
            return self::GUEST;
        }

        if ($minimumAge === 0 && $maximumAge === self::MAX_SUPPORTED_AGE) {
            return self::ELIGIBLE;
        }

        $birthDate = self::getUserBirthDate((int) $userId);
        if ($birthDate === null) {
            return self::UNKNOWN;
        }

        $age = self::ageOnDate($birthDate, $eventDate);

        if ($age === null) {
            return self::UNKNOWN;
        }

        return self::isAgeWithinRange($age, $minimumAge, $maximumAge)
            ? self::ELIGIBLE
            : self::RESTRICTED;
    }

    /**
     * Add the effective event/venue classification and access state to a row.
     */
    public static function decorateEvent($event, $user = null, bool $bypass = false)
    {
        if (!is_object($event)) {
            return $event;
        }

        $eventLevel = self::levelFromRow($event, 'event_age_');
        $venueLevel = self::levelFromRow($event, 'venue_age_');
        $effective = self::combineLevels($eventLevel, $venueLevel);

        self::applyLevel($event, $effective);

        $eventDate = self::normaliseEventDate((string) ($event->dates ?? ''));
        $event->age_access_state = self::assess(
            $user ?: Factory::getApplication()->getIdentity(),
            $event->age_minimum,
            $eventDate,
            $event->age_maximum
        );
        $event->age_visibility_bypass = $bypass;

        return $event;
    }

    /**
     * Add a venue's own classification to its row.
     */
    public static function decorateVenue($venue)
    {
        if (!is_object($venue)) {
            return $venue;
        }

        $level = self::levelFromRow($venue, 'venue_age_');
        self::applyLevel($venue, $level);

        return $venue;
    }

    public static function canViewEvent($event): bool
    {
        return !is_object($event)
            || !empty($event->age_visibility_bypass)
            || ($event->age_access_state ?? self::UNRESTRICTED) !== self::RESTRICTED;
    }

    /**
     * Validate a submitted level ID. Empty means intentionally unrestricted.
     */
    public static function normaliseLevelId($levelId): ?int
    {
        $levelId = (int) $levelId;

        if ($levelId < 1) {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_age_levels'))
            ->where($db->quoteName('id') . ' = ' . $levelId);
        $db->setQuery($query);

        return (int) $db->loadResult() > 0 ? $levelId : null;
    }

    /**
     * Read Joomla's standard profile date of birth.
     */
    public static function getUserBirthDate(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }

        if (array_key_exists($userId, self::$birthDates)) {
            return self::$birthDates[$userId];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('profile_value'))
                ->from($db->quoteName('#__user_profiles'))
                ->where($db->quoteName('user_id') . ' = ' . $userId)
                ->where($db->quoteName('profile_key') . ' = ' . $db->quote('profile.dob'));
            $db->setQuery($query, 0, 1);
            $value = $db->loadResult();
        } catch (Throwable $error) {
            $value = null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_string($decoded) ? $decoded : $value;
        }

        $value = trim((string) $value);
        $birthDate = self::createDate($value);
        $today = self::createDate(Factory::getDate()->format('Y-m-d'));
        self::$birthDates[$userId] = $birthDate && $today && $birthDate <= $today ? $value : null;

        return self::$birthDates[$userId];
    }

    /**
     * Build a database-side visibility condition so pagination and counts do
     * not include events hidden from a known underage user. PHP assessment is
     * still applied afterwards as the authoritative defensive check.
     */
    public static function sqlVisibilityCondition(
        DatabaseDriver $db,
        string $eventAlias,
        string $eventAgeAlias,
        string $venueAgeAlias,
        $user = null
    ): string {
        $user = $user ?: Factory::getApplication()->getIdentity();
        $userId = (int) self::userValue($user, 'id', 0);

        if ($userId < 1
            || (method_exists($user, 'can') && $user->can(array('edit', 'publish'), 'event'))) {
            return '1 = 1';
        }

        $birthDate = self::getUserBirthDate($userId);
        if ($birthDate === null) {
            return '1 = 1';
        }

        $birthDateSql = $db->quote($birthDate);
        $eventDate = 'COALESCE(NULLIF(' . $db->quoteName($eventAlias . '.dates')
            . ', ' . $db->quote('0000-00-00') . '), CURRENT_DATE)';
        $minimum = 'GREATEST(COALESCE(' . $db->quoteName($eventAgeAlias . '.min_age') . ', 0), '
            . 'COALESCE(' . $db->quoteName($venueAgeAlias . '.min_age') . ', 0))';
        $maximum = 'LEAST(COALESCE(' . $db->quoteName($eventAgeAlias . '.max_age') . ', '
            . self::MAX_SUPPORTED_AGE . '), COALESCE(' . $db->quoteName($venueAgeAlias . '.max_age') . ', '
            . self::MAX_SUPPORTED_AGE . '))';
        $age = 'TIMESTAMPDIFF(YEAR, ' . $birthDateSql . ', ' . $eventDate . ')';

        return '((' . $db->quoteName($eventAgeAlias . '.id') . ' IS NULL AND '
            . $db->quoteName($venueAgeAlias . '.id') . ' IS NULL) OR ('
            . $minimum . ' = 0 AND ' . $maximum . ' = ' . self::MAX_SUPPORTED_AGE . ') OR '
            . $age . ' BETWEEN ' . $minimum . ' AND ' . $maximum . ')';
    }

    private static function levelFromRow($row, string $prefix): ?object
    {
        $minimum = $row->{$prefix . 'minimum'} ?? null;

        if ($minimum === null || $minimum === '') {
            return null;
        }

        return (object) array(
            'id' => (int) ($row->{$prefix . 'id'} ?? 0),
            'title' => (string) ($row->{$prefix . 'title'} ?? ''),
            'minimum' => max(0, (int) $minimum),
            'maximum' => min(
                self::MAX_SUPPORTED_AGE,
                max(0, (int) ($row->{$prefix . 'maximum'} ?? self::MAX_SUPPORTED_AGE))
            ),
            'label' => trim((string) ($row->{$prefix . 'label'} ?? '')),
            'background' => self::normaliseColor($row->{$prefix . 'background'} ?? '', '#1F2937'),
            'text' => self::normaliseColor($row->{$prefix . 'text'} ?? '', '#FFFFFF'),
        );
    }

    private static function combineLevels(?object $eventLevel, ?object $venueLevel): ?object
    {
        if ($eventLevel === null) {
            return $venueLevel;
        }

        if ($venueLevel === null) {
            return $eventLevel;
        }

        $minimum = max($eventLevel->minimum, $venueLevel->minimum);
        $maximum = min($eventLevel->maximum, $venueLevel->maximum);

        if ($venueLevel->minimum === $minimum && $venueLevel->maximum === $maximum) {
            $effective = clone $venueLevel;
        } elseif ($eventLevel->minimum === $minimum && $eventLevel->maximum === $maximum) {
            $effective = clone $eventLevel;
        } else {
            $effective = clone ($venueLevel->minimum > $eventLevel->minimum ? $venueLevel : $eventLevel);
            $effective->label = self::formatRange($minimum, $maximum);
        }

        $effective->minimum = $minimum;
        $effective->maximum = $maximum;
        $effective->conflict = $minimum > $maximum;

        if ($effective->conflict) {
            $effective->label = '-';
        }

        return $effective;
    }

    private static function applyLevel($item, ?object $level): void
    {
        $item->effective_age_level_id = $level ? (int) $level->id : null;
        $item->age_minimum = $level ? (int) $level->minimum : null;
        $item->age_maximum = $level ? (int) $level->maximum : null;
        $item->age_level_title = $level ? (string) $level->title : '';
        $item->age_badge_background = $level ? (string) $level->background : '';
        $item->age_badge_text = $level ? (string) $level->text : '';
        $item->age_badge_label = $level
            ? ((string) $level->label !== ''
                ? (string) $level->label
                : self::formatRange((int) $level->minimum, (int) $level->maximum))
            : '';
        $item->age_range_conflict = $level ? !empty($level->conflict) : false;
    }

    private static function normaliseEventDate(string $eventDate): string
    {
        return self::createDate($eventDate)
            ? $eventDate
            : Factory::getDate()->format('Y-m-d');
    }

    private static function createDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (!$date || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date;
    }

    private static function normaliseColor($value, string $fallback): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/D', $value) ? $value : $fallback;
    }

    private static function userValue($user, string $name, $default = null)
    {
        if (!is_object($user)) {
            return $default;
        }

        if (method_exists($user, 'get')) {
            return $user->get($name, $default);
        }

        return property_exists($user, $name) ? $user->$name : $default;
    }
}
