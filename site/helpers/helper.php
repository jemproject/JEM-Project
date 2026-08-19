<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\CMS\Log\LogEntry;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Multilanguage;

// ensure JemFactory is loaded (because this class is used by modules or plugins too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');
require_once(JPATH_SITE.'/components/com_jem/classes/log.class.php');
require_once(JPATH_SITE.'/components/com_jem/classes/menuviewscope.class.php');

/**
 * Holds some usefull functions to keep the code a bit cleaner
 */
class JemHelper
{
    /**
     * Component stylesheet assets loaded during the current request.
     *
     * The list is used as the dependency chain for jem-user-front.css so the
     * additive user stylesheet is always rendered after the selected JEM
     * component stylesheets.
     *
     * @var  array
     */
    protected static $frontendCssAssets = array();

    /**
     * Checks whether the active Joomla menu item targets the current JEM view.
     *
     * Page intro and footer text belong to a menu view, not to the records
     * reached from that view. Joomla keeps the originating Itemid while
     * navigating to an event, category or venue, so the generic page-text
     * parameters must only be honoured when the active menu item actually
     * represents the view being rendered.
     *
     * @param   string      $view       Current JEM view name.
     * @param   mixed|null  $requestId  Current record id; the request value is
     *                                 used when omitted.
     *
     * @return  boolean
     */
    static public function isActiveMenuView($view, $requestId = null)
    {
        try {
            $app  = Factory::getApplication();
            $menu = $app->getMenu()->getActive();
        } catch (\Throwable $e) {
            return false;
        }

        if (!$menu || empty($menu->query)) {
            return false;
        }

        if ($requestId === null) {
            $requestId = $app->input->getString('id', '');
        }

        return JemMenuViewScope::matches($menu->query, $view, $requestId);
    }

    /**
     * Renders optional module intro or footer text.
     *
     * @param   Registry|object  $params    Module parameters.
     * @param   string           $position  intro or footer.
     *
     * @return  string
     */
    static public function renderModuleText($params, $position = 'intro')
    {
        $position = $position === 'footer' ? 'footer' : 'intro';
        $showKey  = $position === 'footer' ? 'showfootertext' : 'showintrotext';
        $textKey  = $position === 'footer' ? 'footertext' : 'introtext';

        if ((int) $params->get($showKey, 0) !== 1) {
            return '';
        }

        $text = trim((string) $params->get($textKey, ''));

        if ($text === '') {
            return '';
        }

        $class = $position === 'footer'
            ? 'jem-module-footertext description no_space floattext'
            : 'jem-module-introtext description no_space floattext';

        return '<div class="' . $class . '">' . $text . '</div>';
    }

    /**
     * Builds a stable CSS token for calendar special day type filters.
     *
     * @param   string  $type  Special day type name.
     *
     * @return  string
     */
    static public function calendarSpecialDayTypeClass($type)
    {
        $type = trim((string) $type);
        $slug = $type !== '' ? ApplicationHelper::stringURLSafe($type) : '';

        if ($slug === '') {
            $slug = 'unknown';
        }

        return 'special-day-type-' . $slug;
    }

    /**
     * Pulls settings from database and stores in an static object
     *
     * @return object
     */
    static public function config()
    {
        static $config;

        if (!is_object($config)) {
            $jemConfig = JemConfig::getInstance();
            $config = clone $jemConfig->toObject(); // We need a copy to ensure not to store 'params' we add below!

            $config->params = ComponentHelper::getParams('com_jem');
        }

        return $config;
    }

    /**
     * Return Joomla's configured timezone as a valid PHP timezone identifier.
     *
     * @return string
     */
    static public function getJoomlaTimeZoneName()
    {
        $timeZone = trim((string) Factory::getConfig()->get('offset', 'UTC'));

        try {
            new \DateTimeZone($timeZone);
        } catch (\Exception $e) {
            $timeZone = 'UTC';
        }

        return $timeZone;
    }

    /**
     * Return a Joomla-timezone calendar date.
     *
     * @param   integer  $offsetDays  Number of days relative to today.
     *
     * @return string
     */
    static public function getJoomlaDate($offsetDays = 0)
    {
        $date = new \DateTimeImmutable('now', new \DateTimeZone(self::getJoomlaTimeZoneName()));

        if ((int) $offsetDays !== 0) {
            $date = $date->modify(((int) $offsetDays > 0 ? '+' : '') . (int) $offsetDays . ' days');
        }

        return $date->format('Y-m-d');
    }

    /**
     * Check whether a timezone can be used for event date calculations.
     *
     * @param   string  $timeZone  Timezone identifier.
     *
     * @return boolean
     */
    static public function isValidTimeZone($timeZone)
    {
        $timeZone = trim((string) $timeZone);

        if ($timeZone === '') {
            return false;
        }

        if (!in_array($timeZone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            return false;
        }

        try {
            new \DateTimeZone($timeZone);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Resolve the authoritative timezone of an event.
     *
     * Existing events default to Joomla's timezone. Venue mode falls back to
     * Joomla when no valid timezone is assigned to the selected venue.
     *
     * @param   object|array  $event          Event data.
     * @param   string|null   $venueTimeZone  Known venue timezone, if available.
     *
     * @return string
     */
    static public function getEventTimeZoneName($event, $venueTimeZone = null)
    {
        $event = is_array($event) ? (object) $event : $event;
        if (!is_object($event)) {
            $event = new \stdClass();
        }
        $mode  = isset($event->timezone_mode) ? trim((string) $event->timezone_mode) : 'joomla';

        if ($mode === 'custom') {
            $customTimeZone = isset($event->timezone) ? trim((string) $event->timezone) : '';

            if (self::isValidTimeZone($customTimeZone)) {
                return $customTimeZone;
            }
        }

        if ($mode === 'venue') {
            if ($venueTimeZone === null && !empty($event->venue_timezone)) {
                $venueTimeZone = $event->venue_timezone;
            }

            if ($venueTimeZone === null && !empty($event->locid)) {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select($db->quoteName('timezone'))
                    ->from($db->quoteName('#__jem_venues'))
                    ->where($db->quoteName('id') . ' = ' . (int) $event->locid);
                $db->setQuery($query);
                $venueTimeZone = $db->loadResult();
            }

            if (self::isValidTimeZone($venueTimeZone)) {
                return trim((string) $venueTimeZone);
            }
        }

        return self::getJoomlaTimeZoneName();
    }

    /**
     * Calculate the canonical UTC start and end values for an event.
     *
     * The dates and times stored in the event remain local wall-clock values.
     * These UTC columns are derived values used for reliable comparisons.
     *
     * @param   object  $event          Event table or data object.
     * @param   string  $venueTimeZone  Known venue timezone, if available.
     *
     * @return void
     */
    static public function setEventUtcDates(&$event, $venueTimeZone = null)
    {
        $event->start_utc = null;
        $event->end_utc   = null;

        if (empty($event->dates) || !self::isValidDate($event->dates)) {
            return;
        }

        $timeZoneName = self::getEventTimeZoneName($event, $venueTimeZone);
        $timeZone     = new \DateTimeZone($timeZoneName);
        $utc          = new \DateTimeZone('UTC');
        $startTime    = empty($event->times) ? '00:00:00' : (string) $event->times;
        $endDate      = !empty($event->enddates) ? (string) $event->enddates : (string) $event->dates;
        $endTime      = empty($event->endtimes) ? '23:59:59' : (string) $event->endtimes;

        try {
            $start = new \DateTimeImmutable((string) $event->dates . ' ' . $startTime, $timeZone);
            $end   = new \DateTimeImmutable($endDate . ' ' . $endTime, $timeZone);

            $event->start_utc = $start->setTimezone($utc)->format('Y-m-d H:i:s');
            $event->end_utc   = $end->setTimezone($utc)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $event->start_utc = null;
            $event->end_utc   = null;
        }
    }

    /**
     * Rebuild UTC values of all events that inherit a venue timezone.
     *
     * @param   integer  $venueId       Venue id.
     * @param   string   $venueTimeZone Venue timezone.
     *
     * @return void
     */
    static public function refreshVenueEventUtcDates($venueId, $venueTimeZone = '')
    {
        $venueId = (int) $venueId;

        if ($venueId <= 0) {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array('id', 'locid', 'dates', 'enddates', 'times', 'endtimes', 'timezone_mode', 'timezone'))
            ->from($db->quoteName('#__jem_events'))
            ->where($db->quoteName('locid') . ' = ' . $venueId)
            ->where($db->quoteName('timezone_mode') . ' = ' . $db->quote('venue'));
        $db->setQuery($query);

        foreach ((array) $db->loadObjectList() as $event) {
            self::setEventUtcDates($event, $venueTimeZone);
            $update = $db->getQuery(true)
                ->update($db->quoteName('#__jem_events'))
                ->set($db->quoteName('start_utc') . ' = ' . ($event->start_utc === null ? 'NULL' : $db->quote($event->start_utc)))
                ->set($db->quoteName('end_utc') . ' = ' . ($event->end_utc === null ? 'NULL' : $db->quote($event->end_utc)))
                ->where($db->quoteName('id') . ' = ' . (int) $event->id);
            $db->setQuery($update);
            $db->execute();
        }
    }

    /**
     * Build the UTC publication-window condition for an event query.
     *
     * @param   string       $alias         Event table alias.
     * @param   boolean      $includeState  Include the published state check.
     * @param   string|null  $now           UTC SQL datetime, mainly for tests.
     *
     * @return string
     */
    static public function getEventPublicationWhere($alias = 'a', $includeState = true, $now = null)
    {
        $db       = Factory::getContainer()->get('DatabaseDriver');
        $now      = $now ?: Factory::getDate()->toSql();
        $nullDate = $db->quote($db->getNullDate());
        $prefix   = $includeState ? $alias . '.published = 1 AND ' : '';

        return $prefix
            . '(' . $alias . '.publish_up IS NULL OR ' . $alias . '.publish_up = ' . $nullDate . ' OR ' . $alias . '.publish_up <= ' . $db->quote($now) . ')'
            . ' AND (' . $alias . '.publish_down IS NULL OR ' . $alias . '.publish_down = ' . $nullDate . ' OR ' . $alias . '.publish_down > ' . $db->quote($now) . ')';
    }

    /**
     * Build an EXISTS condition for venues which do (or do not) have an event
     * visible to the current user.
     *
     * @param   string   $venueAlias  Venue table alias.
     * @param   boolean  $hasEvents   True for EXISTS, false for NOT EXISTS.
     * @param   integer  $state       Event state (1 published, 2 archived).
     *
     * @return string
     */
    static public function getVenueEventExistsWhere($venueAlias = 'a', $hasEvents = true, $state = 1)
    {
        $app         = Factory::getApplication();
        $db          = Factory::getContainer()->get('DatabaseDriver');
        $user        = $app->getIdentity();
        $settings    = self::config();
        $levels      = array_map('intval', $user->getAuthorisedViewLevels());
        $eventLevels = self::mergeLockedViewLevels($levels, $settings->access_level_locked_events ?? '["1"]');
        $catLevels   = self::mergeLockedViewLevels($levels, $settings->access_level_locked_categories ?? '["1"]');
        $typeLevels  = implode(',', array_unique($levels));
        $language    = $app->getLanguage()->getTag();

        $effectiveType = 'COALESCE(NULLIF(ve.type_id, 0), vp.type_id)';
        $typeLanguage = '(vet.language IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')'
            . ' OR vet.base_language <> ' . $db->quote('') . ' OR vet.translation_languages IS NOT NULL)';

        $conditions = array(
            've.locid = ' . $venueAlias . '.id',
            've.published = ' . (int) $state,
            'vc.published = 1',
            've.access IN (' . implode(',', $eventLevels) . ')',
            'vc.access IN (' . implode(',', $catLevels) . ')',
            '(' . $effectiveType . ' IS NULL OR ' . $effectiveType . ' = 0'
                . ' OR (vet.id IS NOT NULL AND vet.access IN (' . $typeLevels . ')))',
        );

        if ((int) $state === 1) {
            $conditions[] = self::getEventPublicationWhere('ve', false);
        }

        $subquery = 'SELECT 1 FROM #__jem_events AS ve'
            . ' LEFT JOIN #__jem_events AS vp ON vp.id = ve.recurrence_first_id'
            . ' INNER JOIN #__jem_cats_event_relations AS vrel ON vrel.itemid = ve.id'
            . ' INNER JOIN #__jem_categories AS vc ON vc.id = vrel.catid'
            . ' LEFT JOIN #__jem_types AS vet ON vet.id = ' . $effectiveType
            . ' AND vet.entity = 1 AND vet.published = 1 AND ' . $typeLanguage
            . ' WHERE ' . implode(' AND ', $conditions);

        return ($hasEvents ? 'EXISTS' : 'NOT EXISTS') . ' (' . $subquery . ')';
    }

    private static function mergeLockedViewLevels(array $levels, $lockedLevels)
    {
        if ((string) $lockedLevels !== '["1"]') {
            $extra = json_decode((string) $lockedLevels, true);
            if (is_array($extra)) {
                $levels = array_merge($levels, array_map('intval', $extra));
            }
        }

        $levels = array_values(array_unique(array_filter(array_map('intval', $levels))));

        return $levels ?: array(1);
    }

    /**
     * Test an event's publication state and UTC publication window.
     *
     * @param   object   $event         Event data.
     * @param   boolean  $includeState  Require published=1.
     *
     * @return boolean
     */
    static public function isEventPublishedNow($event, $includeState = true)
    {
        if ($includeState && (int) ($event->published ?? 0) !== 1) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach (array('publish_up' => 'up', 'publish_down' => 'down') as $field => $direction) {
            $value = trim((string) ($event->$field ?? ''));
            if ($value === '' || $value === '0000-00-00 00:00:00') {
                continue;
            }

            try {
                $boundary = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            } catch (\Exception $e) {
                continue;
            }

            if (($direction === 'up' && $boundary > $now) || ($direction === 'down' && $boundary <= $now)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert a UTC database datetime to a Unix timestamp.
     *
     * @param   string  $value  SQL datetime value.
     *
     * @return integer
     */
    static public function getUtcTimestamp($value)
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return 0;
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Return the current state of an event registration window.
     *
     * Registration boundary values are stored in UTC. Open-date events keep
     * the legacy behaviour where a limited window does not restrict access.
     *
     * @param   object|array  $event  Event data.
     * @param   integer|null  $now    Unix timestamp, mainly for tests.
     *
     * @return string disabled, not_started, open or closed.
     */
    static public function getEventRegistrationWindowState($event, $now = null)
    {
        $event = is_array($event) ? (object) $event : $event;
        if (!is_object($event)) {
            return 'disabled';
        }

        $mode = (int) ($event->registra ?? 0);
        if ($mode === 1) {
            return 'open';
        }

        if ($mode !== 2) {
            return 'disabled';
        }

        if (empty($event->dates)) {
            return 'open';
        }

        $now   = $now === null ? time() : (int) $now;
        $from  = self::getUtcTimestamp($event->registra_from ?? '');
        $until = self::getUtcTimestamp($event->registra_until ?? '');

        if ($from && $now < $from) {
            return 'not_started';
        }

        if ($until && $now >= $until) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * Return whether an event currently accepts registrations.
     *
     * @param   object|array  $event  Event data.
     * @param   integer|null  $now    Unix timestamp, mainly for tests.
     *
     * @return boolean
     */
    static public function isEventRegistrationOpen($event, $now = null)
    {
        return self::getEventRegistrationWindowState($event, $now) === 'open';
    }

    /**
     * Return the current state of an event cancellation window.
     *
     * @param   object|array  $event  Event data.
     * @param   integer|null  $now    Unix timestamp, mainly for tests.
     *
     * @return string disabled, open or closed.
     */
    static public function getEventUnregistrationWindowState($event, $now = null)
    {
        $event = is_array($event) ? (object) $event : $event;
        if (!is_object($event)) {
            return 'disabled';
        }

        $mode = (int) ($event->unregistra ?? 0);
        if ($mode === 1) {
            return 'open';
        }

        if ($mode !== 2) {
            return 'disabled';
        }

        if (empty($event->dates)) {
            return 'open';
        }

        $now   = $now === null ? time() : (int) $now;
        $until = self::getUtcTimestamp($event->unregistra_until ?? '');

        return $until && $now < $until ? 'open' : 'closed';
    }

    /**
     * Return whether an existing registration can currently be cancelled.
     *
     * @param   object|array  $event  Event data.
     * @param   integer|null  $now    Unix timestamp, mainly for tests.
     *
     * @return boolean
     */
    static public function isEventUnregistrationOpen($event, $now = null)
    {
        return self::getEventUnregistrationWindowState($event, $now) === 'open';
    }

    /**
     * Build an event start/end comparison against the current instant.
     *
     * Cached UTC values are preferred. The fallback preserves the legacy
     * Joomla-timezone interpretation for records not backfilled yet.
     *
     * @param   string   $boundary       start or end.
     * @param   string   $operator       SQL comparison operator.
     * @param   integer  $offsetMinutes  Offset applied to the current instant.
     * @param   string   $alias          Event table alias.
     * @param   boolean  $includeOpen    Include events without a start date.
     *
     * @return string
     */
    static public function getEventDateTimeWhere($boundary, $operator, $offsetMinutes = 0, $alias = 'a', $includeOpen = false)
    {
        $boundary = $boundary === 'end' ? 'end' : 'start';
        $operator = in_array($operator, array('>', '>=', '<', '<='), true) ? $operator : '>';
        $instant  = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ((int) $offsetMinutes !== 0) {
            $instant = $instant->modify(((int) $offsetMinutes > 0 ? '+' : '') . (int) $offsetMinutes . ' minutes');
        }

        $utcNow   = $instant->format('Y-m-d H:i:s');
        $localNow = $instant->setTimezone(new \DateTimeZone(self::getJoomlaTimeZoneName()))->format('Y-m-d H:i:s');
        $db       = Factory::getContainer()->get('DatabaseDriver');
        $utcField = $alias . '.' . ($boundary === 'end' ? 'end_utc' : 'start_utc');
        $local    = $boundary === 'end'
            ? 'CONCAT(IFNULL(' . $alias . '.enddates,' . $alias . '.dates), \' \', IFNULL(' . $alias . '.endtimes,\'23:59:59\'))'
            : 'CONCAT(' . $alias . '.dates, \' \', IFNULL(' . $alias . '.times,\'00:00:00\'))';
        $condition = '((COALESCE(' . $alias . '.timezone_mode, \'joomla\') <> \'joomla\' AND ' . $utcField . ' IS NOT NULL AND ' . $utcField . ' ' . $operator . ' ' . $db->quote($utcNow) . ')'
            . ' OR ((COALESCE(' . $alias . '.timezone_mode, \'joomla\') = \'joomla\' OR ' . $utcField . ' IS NULL) AND ' . $alias . '.dates IS NOT NULL AND ' . $local . ' ' . $operator . ' ' . $db->quote($localNow) . '))';

        if ($includeOpen) {
            $condition = '(' . $alias . '.dates IS NULL OR ' . $condition . ')';
        }

        return $condition;
    }

    /**
     * Returns true when Joomla's core Contacts component is available.
     *
     * @return boolean
     */
    static public function isContactComponentEnabled()
    {
        return ComponentHelper::isEnabled('com_contact');
    }

    /**
     * Returns true when Community Builder is enabled and its profile table is available.
     *
     * @return boolean
     */
    static public function isCommunityBuilderEnabled()
    {
        if (!ComponentHelper::isEnabled('com_comprofiler')) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            return in_array($db->replacePrefix('#__comprofiler'), $db->getTableList(), true);
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Load published Joomla articles associated with the given events.
     *
     * @param   array  $events  Event objects that may contain article_id.
     * @param   array  $levels  Authorized view levels.
     *
     * @return  array  Associated article data keyed by article id.
     */
    static public function getAssociatedArticles(array $events, array $levels)
    {
        if ((int) self::globalattribs()->get('event_use_associated_article', 1) !== 1) {
            return array();
        }

        $articles = array();

        foreach ($events as $event) {
            $params = $event->params ?? null;

            if (!$params instanceof Registry) {
                $params = new Registry();
                $params->loadString((string) ($event->attribs ?? '{}'));
            }

            if (in_array((string) $params->get('article_usage', 'information'), array('none', 'content'), true)) {
                continue;
            }

            if (!empty($event->article_id)) {
                $article = self::getAssociatedArticleForEventContent((int) $event->article_id, $levels);

                if (!empty($article)) {
                    $articles[(int) $event->article_id] = $article;
                }
            }
        }

        return $articles;
    }

    /**
     * Build display data for an associated article.
     *
     * @param   object|null  $article  Associated article.
     *
     * @return  array
     */
    static public function getAssociatedArticleLink($article)
    {
        if (empty($article)) {
            return array('link' => '', 'title' => '');
        }

        $articleSlug = $article->alias ? ((int) $article->id . ':' . $article->alias) : (int) $article->id;
        $user = JemFactory::getUser();
        $canEdit = $user->authorise('core.edit', 'com_content.article.' . (int) $article->id)
            || ((int) $article->created_by === (int) $user->id && $user->authorise('core.edit.own', 'com_content.article.' . (int) $article->id));

        return array(
            'link'      => Route::_('index.php?option=com_content&view=article&id=' . $articleSlug . '&catid=' . (int) $article->catid),
            'title'     => htmlspecialchars($article->title, ENT_COMPAT, 'UTF-8'),
            'edit_link' => $canEdit ? Route::_('index.php?option=com_content&task=article.edit&a_id=' . (int) $article->id . '&return=' . base64_encode(Uri::getInstance()->toString()) . '&' . Session::getFormToken() . '=1') : '',
            'can_edit'  => $canEdit
        );
    }

    /**
     * Apply associated Joomla article content to an event when the event opts in.
     *
     * @param   object       $event     Event data.
     * @param   array|null   $levels    Authorized view levels.
     * @param   string|null  $language  Preferred language tag.
     *
     * @return  object
     */
    static public function applyAssociatedArticleEventContent($event, ?array $levels = null, ?string $language = null)
    {
        if (empty($event) || empty($event->article_id)) {
            return $event;
        }

        $params = $event->params ?? null;

        if (!$params instanceof Registry) {
            $params = new Registry();
            $params->loadString((string) ($event->attribs ?? '{}'));
        }

        if ((string) $params->get('article_usage', 'information') !== 'content') {
            return $event;
        }

        $levels = $levels ?: JemFactory::getUser()->getAuthorisedViewLevels();
        $globalAttribs = self::globalattribs();
        $fallback = (string) $globalAttribs->get('event_article_content_language_fallback', 'article');
        $article = self::getAssociatedArticleForEventContent((int) $event->article_id, $levels, $language, $fallback);

        if (empty($article)) {
            if ($fallback === 'blank') {
                if ((int) $globalAttribs->get('event_article_content_use_title', 1) === 1) {
                    $event->title = '';
                }

                if ((int) $globalAttribs->get('event_article_content_use_alias', 1) === 1) {
                    $event->alias = '';
                }

                if ((int) $globalAttribs->get('event_article_content_use_introtext', 1) === 1) {
                    $event->introtext = '';
                }

                if ((int) $globalAttribs->get('event_article_content_use_fulltext', 1) === 1) {
                    $event->fulltext = '';
                }

                if ((int) $globalAttribs->get('event_article_content_use_metadata', 1) === 1) {
                    $event->meta_keywords = '';
                    $event->meta_description = '';
                    $event->metadata = new Registry();
                }

                if ((int) $globalAttribs->get('event_article_content_use_image', 1) === 1) {
                    $event->datimage = '';
                }
            }

            return $event;
        }

        $event->article_content_applied = true;
        $event->article_content_id = (int) $article->id;
        $event->article_content_language = (string) $article->language;

        if ((int) $globalAttribs->get('event_article_content_use_title', 1) === 1 && trim((string) $article->title) !== '') {
            $event->title = $article->title;
        }

        if ((int) $globalAttribs->get('event_article_content_use_alias', 1) === 1 && trim((string) $article->alias) !== '') {
            $event->alias = $article->alias;
        }

        if ((int) $globalAttribs->get('event_article_content_use_introtext', 1) === 1) {
            $event->introtext = (string) $article->introtext;
        }

        if ((int) $globalAttribs->get('event_article_content_use_fulltext', 1) === 1) {
            $event->fulltext = (string) $article->fulltext;
        }

        if ((int) $globalAttribs->get('event_article_content_use_metadata', 1) === 1) {
            if (!empty($article->metakey)) {
                $event->meta_keywords = $article->metakey;
            }

            if (!empty($article->metadesc)) {
                $event->meta_description = $article->metadesc;
            }

            if (isset($article->metadata)) {
                $registry = new Registry();
                $registry->loadString((string) $article->metadata);
                $event->metadata = $registry;
            }
        }

        if ((int) $globalAttribs->get('event_article_content_use_image', 1) === 1) {
            $articleImage = self::getAssociatedArticleImage($article);

            if ($articleImage !== '') {
                $event->datimage = $articleImage;
                $event->article_content_image = $articleImage;
            }
        }

        return $event;
    }

    /**
     * Apply associated Joomla article content to a list of events.
     *
     * @param   array        $events    Event objects.
     * @param   array|null   $levels    Authorized view levels.
     * @param   string|null  $language  Preferred language tag.
     *
     * @return  array
     */
    static public function applyAssociatedArticleEventContentToEvents(array $events, ?array $levels = null, ?string $language = null)
    {
        foreach ($events as $event) {
            self::applyAssociatedArticleEventContent($event, $levels, $language);

            if (!empty($event->id)) {
                $event->slug = !empty($event->alias) ? ((int) $event->id . ':' . $event->alias) : (int) $event->id;
            }
        }

        return $events;
    }

    /**
     * Resolve the best associated Joomla article for event content.
     *
     * @param   int          $articleId  Base article id.
     * @param   array        $levels     Authorized view levels.
     * @param   string|null  $language   Preferred language tag.
     *
     * @return  object|null
     */
    static public function getAssociatedArticleForEventContent(int $articleId, array $levels, ?string $language = null, string $fallback = 'article')
    {
        if ($articleId <= 0 || (int) self::globalattribs()->get('event_use_associated_article', 1) !== 1) {
            return null;
        }

        $levels = array_values(array_unique(array_map('intval', $levels)));

        if (!$levels) {
            return null;
        }

        $app = Factory::getApplication();
        $language = $language ?: $app->getLanguage()->getTag();
        $defaultLanguage = (string) ComponentHelper::getParams('com_languages')->get('site', '');

        if ($defaultLanguage === '') {
            $defaultLanguage = $language;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $nullDate = $db->quote($db->getNullDate());
        $nowDate = $db->quote(Factory::getDate()->toSql());
        $associationIds = array($articleId);

        if (Multilanguage::isEnabled()) {
            $assocQuery = $db->getQuery(true)
                ->select($db->quoteName('assoc2.id'))
                ->from($db->quoteName('#__associations', 'assoc1'))
                ->join('INNER', $db->quoteName('#__associations', 'assoc2') . ' ON ' . $db->quoteName('assoc2.key') . ' = ' . $db->quoteName('assoc1.key') . ' AND ' . $db->quoteName('assoc2.context') . ' = ' . $db->quoteName('assoc1.context'))
                ->where($db->quoteName('assoc1.id') . ' = ' . (int) $articleId)
                ->where($db->quoteName('assoc1.context') . ' = ' . $db->quote('com_content.item'));

            try {
                $db->setQuery($assocQuery);
                $associationIds = array_merge($associationIds, array_map('intval', (array) $db->loadColumn()));
            } catch (RuntimeException $e) {
                $associationIds = array($articleId);
            }
        }

        $associationIds = array_values(array_unique(array_filter($associationIds)));

        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.catid'),
                $db->quoteName('a.introtext'),
                $db->quoteName('a.fulltext'),
                $db->quoteName('a.metakey'),
                $db->quoteName('a.metadesc'),
                $db->quoteName('a.metadata'),
                $db->quoteName('a.images'),
                $db->quoteName('a.language'),
                $db->quoteName('a.created_by')
            ))
            ->from($db->quoteName('#__content', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid') . ' AND ' . $db->quoteName('c.extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('a.id') . ' IN (' . implode(',', $associationIds) . ')')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.access') . ' IN (' . implode(',', $levels) . ')')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('c.access') . ' IN (' . implode(',', $levels) . ')')
            ->where('(' . $db->quoteName('a.publish_up') . ' IS NULL OR ' . $db->quoteName('a.publish_up') . ' = ' . $nullDate . ' OR ' . $db->quoteName('a.publish_up') . ' <= ' . $nowDate . ')')
            ->where('(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' . $db->quoteName('a.publish_down') . ' = ' . $nullDate . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $nowDate . ')');

        try {
            $db->setQuery($query);
            $articles = $db->loadObjectList('id') ?: array();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return null;
        }

        if (!$articles) {
            return null;
        }

        if (!Multilanguage::isEnabled()) {
            return $articles[$articleId] ?? reset($articles) ?: null;
        }

        $candidateLanguages = array($language, '*');

        if ($fallback === 'article') {
            $candidateLanguages = array($language, $defaultLanguage, '*');
        }

        foreach ($candidateLanguages as $candidateLanguage) {
            if ($candidateLanguage === '') {
                continue;
            }

            foreach ($articles as $article) {
                if ((string) $article->language === (string) $candidateLanguage) {
                    return $article;
                }
            }
        }

        return null;
    }

    /**
     * Get the best image path from a Joomla article.
     *
     * @param   object  $article  Joomla article.
     *
     * @return  string
     */
    static protected function getAssociatedArticleImage($article)
    {
        $images = new Registry();
        $images->loadString((string) ($article->images ?? ''));

        foreach (array('image_fulltext', 'image_intro') as $field) {
            $image = trim((string) $images->get($field, ''));

            if ($image !== '') {
                return ltrim($image, '/');
            }
        }

        return '';
    }

    /**
     * Return a sanitized online meeting URL for display and export.
     *
     * @param   object  $event  Event data.
     *
     * @return  string
     */
    static public function getOnlineMeetingUrl($event)
    {
        $url = isset($event->online_meeting_url) ? trim((string) $event->online_meeting_url) : '';

        return self::sanitizeOnlineMeetingUrl($url);
    }

    /**
     * Return the first valid online event link for ICS fallback.
     *
     * @param   object  $event  Event data.
     *
     * @return  array
     */
    static public function getOnlineMeetingEventLink($event)
    {
        $links = array();

        if (!empty($event->event_links) && is_array($event->event_links)) {
            $links = $event->event_links;
        } elseif (!empty($event->id)) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(array(
                    $db->quoteName('title'),
                    $db->quoteName('url')
                ))
                ->from($db->quoteName('#__jem_links'))
                ->where($db->quoteName('event_id') . ' = ' . (int) $event->id)
                ->where($db->quoteName('type') . ' = ' . $db->quote('online'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('url') . ' <> ' . $db->quote(''))
                ->order(array($db->quoteName('ordering') . ' ASC', $db->quoteName('id') . ' ASC'));

            try {
                $db->setQuery($query);
                $links = $db->loadObjectList() ?: array();
            } catch (Exception $e) {
                $links = array();
            }
        }

        foreach ($links as $link) {
            $type = is_array($link) ? ($link['type'] ?? '') : ($link->type ?? '');

            if ($type !== '' && $type !== 'online') {
                continue;
            }

            $url = self::sanitizeOnlineMeetingUrl(is_array($link) ? ($link['url'] ?? '') : ($link->url ?? ''));

            if ($url === '') {
                continue;
            }

            $label = trim((string) (is_array($link) ? ($link['title'] ?? '') : ($link->title ?? '')));

            return array(
                'url' => $url,
                'label' => $label
            );
        }

        return array('url' => '', 'label' => '');
    }

    /**
     * Sanitize an online meeting URL.
     *
     * @param   string  $url  URL to check.
     *
     * @return  string
     */
    static protected function sanitizeOnlineMeetingUrl($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!$scheme || !in_array(strtolower($scheme), array('http', 'https'), true)) {
            return '';
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * Return the label used for the online meeting call to action.
     *
     * @param   object  $event  Event data.
     *
     * @return  string
     */
    static public function getOnlineMeetingLabel($event)
    {
        $label = isset($event->online_meeting_label) ? trim((string) $event->online_meeting_label) : '';

        if ($label === '') {
            $settings = self::globalattribs();
            $label = trim((string) $settings->get('event_online_meeting_default_label', ''));
        }

        if ($label === '') {
            $label = Text::_('COM_JEM_JOIN_ONLINE');
        } elseif (strtoupper($label) === $label) {
            $label = Text::_($label);
        }

        return $label;
    }

    /**
     * Detect the online meeting platform from a URL.
     *
     * @param   string  $url  Online meeting URL.
     *
     * @return  array
     */
    static public function getOnlineMeetingPlatform($url)
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        $platforms = array(
            'zoom' => array(
                'label' => 'Zoom',
                'domains' => array('zoom.us')
            ),
            'teams' => array(
                'label' => 'Microsoft Teams',
                'domains' => array('teams.microsoft.com', 'teams.live.com')
            ),
            'meet' => array(
                'label' => 'Google Meet',
                'domains' => array('meet.google.com')
            ),
            'webex' => array(
                'label' => 'Cisco Webex',
                'domains' => array('webex.com')
            ),
            'jitsi' => array(
                'label' => 'Jitsi Meet',
                'domains' => array('meet.jit.si', 'jitsi.org')
            ),
            'bigbluebutton' => array(
                'label' => 'BigBlueButton',
                'domains' => array('bigbluebutton.org')
            ),
            'gotomeeting' => array(
                'label' => 'GoTo Meeting',
                'domains' => array('gotomeeting.com')
            ),
            'whereby' => array(
                'label' => 'Whereby',
                'domains' => array('whereby.com')
            ),
            'discord' => array(
                'label' => 'Discord',
                'domains' => array('discord.gg', 'discord.com')
            ),
            'youtube' => array(
                'label' => 'YouTube Live',
                'domains' => array('youtube.com', 'youtu.be')
            )
        );

        foreach ($platforms as $key => $platform) {
            foreach ($platform['domains'] as $domain) {
                if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                    return array(
                        'key' => $key,
                        'label' => $platform['label'],
                        'icon' => 'fa fa-video'
                    );
                }
            }
        }

        if (preg_match('/(^|\.)bbb[.-]/', $host) || strpos($host, 'bigbluebutton') !== false) {
            return array(
                'key' => 'bigbluebutton',
                'label' => 'BigBlueButton',
                'icon' => 'fa fa-video'
            );
        }

        return array(
            'key' => 'generic',
            'label' => Text::_('COM_JEM_ONLINE_MEETING'),
            'icon' => 'fa fa-globe'
        );
    }

    /**
     * Normalize the More information display option.
     *
     * @param   mixed  $value  Module parameter value.
     *
     * @return  string  link, button, or empty string when disabled.
     */
    static public function getMoreInformationDisplay($value)
    {
        $value = (string) $value;

        if ($value === '1') {
            return 'link';
        }

        if ($value === '2') {
            return 'button';
        }

        if ($value === 'link' || $value === 'button') {
            return $value;
        }

        return '';
    }

    /**
     * Build CSS classes for the More information article link.
     *
     * @param   string  $display  Normalized display option.
     * @param   string  $base     Optional base classes.
     *
     * @return  string
     */
    static public function getMoreInformationClass($display, $base = '')
    {
        $classes = trim($base);

        if (!preg_match('/(^|\s)jem-more-information-link(\s|$)/', $classes)) {
            $classes = trim($classes . ' jem-more-information-link');
        }

        if ($display === 'button') {
            if (!preg_match('/(^|\s)btn(\s|$)/', $base)) {
                $classes .= ' btn btn-primary btn-sm';
            }
        }

        return trim($classes);
    }

    /**
     * Build a stable id for module event action links.
     *
     * @param   string  $module   Module name.
     * @param   string  $action   Action name.
     * @param   mixed   $eventId  Event id.
     * @param   mixed   $moduleId Module id.
     *
     * @return  string
     */
    static public function getModuleActionId($module, $action, $eventId, $moduleId = 0)
    {
        $module = preg_replace('/[^a-z0-9_-]+/i', '-', (string) $module);
        $action = preg_replace('/[^a-z0-9_-]+/i', '-', (string) $action);

        return strtolower(trim($module, '-') . '-' . trim($action, '-') . '-' . (int) $moduleId . '-' . (int) $eventId);
    }

    /**
     * Pulls settings from database and stores in an static object
     *
     * @return object
     */
    static public function globalattribs()
    {
        static $globalregistry;
        if (!is_object($globalregistry)) {
            $globalregistry = new Registry(self::config()->globalattribs);
        }

        return $globalregistry;
    }

    /**
     * Returns the configured calendar special day types.
     *
     * @return  array
     */
    static public function calendarSpecialDayTypes()
    {
        static $types;

        if (is_array($types)) {
            return $types;
        }

        $types = self::loadCalendarSpecialDayTypesFromTable();

        if ($types) {
            return $types;
        }

        $default = "Weekend | #d1d5db | 0\nPublic holiday | #e5e7eb | 0";
        $raw = (string) self::globalattribs()->get('calendar_special_day_types', $default);
        $types = array();

        $priority = 0;

        foreach (preg_split('/\R/u', $raw) as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $name = $parts[0] ?? '';

            if ($name === '') {
                continue;
            }

            $color = $parts[1] ?? '#d1d5db';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#d1d5db';
            }

            $types[$name] = array(
                'id' => 0,
                'name' => $name,
                'icon' => '',
                'color' => strtolower($color),
                'block_events' => !empty($parts[2]) && (int) $parts[2] === 1,
                'priority' => $priority++,
            );
        }

        if (!$types) {
            $types['Weekend'] = array('id' => 0, 'name' => 'Weekend', 'icon' => '', 'color' => '#d1d5db', 'block_events' => false, 'priority' => 0);
        }

        return $types;
    }

    /**
     * Returns configured Day types keyed by their numeric id.
     *
     * @return  array
     */
    static public function calendarSpecialDayTypesById()
    {
        $types = array();

        foreach (self::calendarSpecialDayTypes() as $type) {
            $id = (int) ($type['id'] ?? 0);

            if ($id > 0) {
                $types[$id] = $type;
            }
        }

        return $types;
    }

    /**
     * Resolve a Day type from id or name.
     *
     * @param   mixed  $value  Type id or name.
     *
     * @return  array|null
     */
    static public function resolveCalendarSpecialDayType($value)
    {
        if (is_numeric($value) && (int) $value > 0) {
            $typesById = self::calendarSpecialDayTypesById();

            return $typesById[(int) $value] ?? null;
        }

        $name = trim((string) $value);

        if ($name === '') {
            return null;
        }

        $types = self::calendarSpecialDayTypes();

        if (isset($types[$name])) {
            return $types[$name];
        }

        foreach ($types as $type) {
            if (strcasecmp((string) ($type['name'] ?? ''), $name) === 0) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Load Day types from #__jem_types.
     *
     * @return  array
     */
    static private function loadCalendarSpecialDayTypesFromTable()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('icon'),
                $db->quoteName('color'),
                $db->quoteName('ordering'),
                $db->quoteName('attribs'),
            ))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = 4')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels())) . ')')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        try {
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: array();
        } catch (RuntimeException $e) {
            return array();
        }

        $types = array();
        $priority = 0;

        foreach ($rows as $row) {
            $name = trim((string) $row->name);

            if ($name === '') {
                continue;
            }

            $color = trim((string) ($row->color ?? ''));
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '';

            $attribs = new Registry((string) ($row->attribs ?? ''));

            $types[$name] = array(
                'id' => (int) $row->id,
                'name' => $name,
                'icon' => trim((string) ($row->icon ?? '')),
                'color' => $color,
                'block_events' => (int) $attribs->get('block_events', 0) === 1,
                'show_dates_default' => (int) $attribs->get('show_dates_default', 1) === 0 ? 0 : 1,
                'priority' => $priority++,
            );
        }

        return $types;
    }

    /**
     * Returns calendar special days expanded by date for the requested period.
     *
     * @param   string  $startDate  Period start date, Y-m-d.
     * @param   string  $endDate    Period end date, Y-m-d.
     *
     * @return  array
     */
    static public function calendarSpecialDays($startDate, $endDate)
    {
        if ((int) self::globalattribs()->get('calendar_special_days_enabled', 0) !== 1) {
            return array();
        }

        try {
            $periodStart = new DateTimeImmutable((string) $startDate);
            $periodEnd = new DateTimeImmutable((string) $endDate);
        } catch (Exception $e) {
            return array();
        }

        if ($periodEnd < $periodStart) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $nullDate = $db->quote($db->getNullDate());
        $query = $db->getQuery(true)
            ->select($db->quoteName(array(
                'id',
                'title',
                'day_type_id',
                'day_type',
                'start_date',
                'end_date',
                'weekdays',
                'description',
                'article_id',
                'url',
                'show_dates',
                'access',
                'ordering',
            )))
            ->from($db->quoteName('#__jem_special_days'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels())) . ')')
            ->where('('
                . '(' . $db->quoteName('weekdays') . ' IS NOT NULL AND ' . $db->quoteName('weekdays') . ' <> ' . $db->quote('') . ')'
                . ' OR '
                . '('
                    . $db->quoteName('start_date') . ' IS NOT NULL'
                    . ' AND ' . $db->quoteName('start_date') . ' <> ' . $nullDate
                    . ' AND ' . $db->quoteName('start_date') . ' <= ' . $db->quote($periodEnd->format('Y-m-d'))
                    . ' AND ('
                        . $db->quoteName('end_date') . ' IS NULL'
                        . ' OR ' . $db->quoteName('end_date') . ' = ' . $nullDate
                        . ' OR ' . $db->quoteName('end_date') . ' >= ' . $db->quote($periodStart->format('Y-m-d'))
                    . ')'
                . ')'
            . ')')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

        try {
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: array();
        } catch (RuntimeException $e) {
            return array();
        }

        $types = self::calendarSpecialDayTypes();
        $typesById = self::calendarSpecialDayTypesById();
        $days = array();
        $nullDateValue = $db->getNullDate();

        $addDay = static function (array &$days, DateTimeImmutable $date, $row, array $type, $isDatedRule) {
            $dateKey = $date->format('Y-m-d');
            $days[$dateKey][] = array(
                'id' => (int) $row->id,
                'date' => $dateKey,
                'title' => (string) $row->title,
                'type' => $type['name'],
                'icon' => (string) ($type['icon'] ?? ''),
                'color' => $type['color'],
                'block_events' => (bool) $type['block_events'],
                'description' => (string) $row->description,
                'article_id' => (int) ($row->article_id ?? 0),
                'url' => (string) ($row->url ?? ''),
                'show_dates' => (int) ($row->show_dates ?? 1) === 1,
                'is_dated_rule' => (bool) $isDatedRule,
                'rule_start_date' => (string) ($row->start_date ?? ''),
                'rule_end_date' => (string) ($row->end_date ?? ''),
                'rule_order' => (int) ($row->ordering ?? 0),
                'priority' => (int) ($type['priority'] ?? 999),
            );
        };

        foreach ($rows as $row) {
            $type = !empty($row->day_type_id) && isset($typesById[(int) $row->day_type_id])
                ? $typesById[(int) $row->day_type_id]
                : ($types[$row->day_type] ?? array(
                    'id' => (int) ($row->day_type_id ?? 0),
                'name' => (string) $row->day_type,
                'icon' => '',
                'color' => '#d1d5db',
                'block_events' => false,
                'priority' => 999,
                ));

            $rangeStart = $periodStart;
            $rangeEnd = $periodEnd;
            $isDatedRule = !empty($row->start_date) && $row->start_date !== $nullDateValue;
            $rowStartDate = null;
            $rowEndDate = null;

            if ($isDatedRule) {
                try {
                    $rowStartDate = new DateTimeImmutable((string) $row->start_date);
                    if ($rowStartDate > $rangeStart) {
                        $rangeStart = $rowStartDate;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            if (!empty($row->end_date) && $row->end_date !== $nullDateValue) {
                try {
                    $rowEndDate = new DateTimeImmutable((string) $row->end_date);
                    if ($rowEndDate < $rangeEnd) {
                        $rangeEnd = $rowEndDate;
                    }
                } catch (Exception $e) {
                    continue;
                }
            } elseif (empty($row->weekdays) || ($isDatedRule && trim((string) $row->weekdays) === '0')) {
                $rangeEnd = $rangeStart;
            }

            if ($rangeEnd < $rangeStart) {
                continue;
            }

            $weekdays = array_filter(array_map('trim', explode(',', (string) $row->weekdays)), 'strlen');
            $weekdays = array_map('intval', $weekdays);
            $hasMultiDayDatedRange = $rowStartDate instanceof DateTimeImmutable
                && $rowEndDate instanceof DateTimeImmutable
                && $rowEndDate > $rowStartDate;
            $ignoreDefaultWeekday = $isDatedRule && !$hasMultiDayDatedRange && trim((string) $row->weekdays) === '0';

            for ($date = $rangeStart; $date <= $rangeEnd; $date = $date->modify('+1 day')) {
                if (!$ignoreDefaultWeekday && $weekdays && !in_array((int) $date->format('w'), $weekdays, true)) {
                    continue;
                }

                $addDay($days, $date, $row, $type, $isDatedRule);
            }
        }

        foreach ($days as &$specialDays) {
            usort($specialDays, static function ($a, $b) {
                $priority = ((int) ($a['rule_order'] ?? 0)) <=> ((int) ($b['rule_order'] ?? 0));

                if ($priority !== 0) {
                    return $priority;
                }

                $dated = (int) !empty($b['is_dated_rule']) <=> (int) !empty($a['is_dated_rule']);

                if ($dated !== 0) {
                    return $dated;
                }

                $typePriority = ((int) ($a['priority'] ?? 999)) <=> ((int) ($b['priority'] ?? 999));

                if ($typePriority !== 0) {
                    return $typePriority;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            });
        }
        unset($specialDays);

        return $days;
    }

    /**
     * Apply active special day styling to a JemCalendar-compatible calendar object.
     *
     * @param   object  $calendar   Calendar instance with setDayAttributes().
     * @param   string  $startDate  Period start date, Y-m-d.
     * @param   string  $endDate    Period end date, Y-m-d.
     *
     * @return  void
     */
    static public function applyCalendarSpecialDayAttributes($calendar, $startDate, $endDate)
    {
        if (!is_object($calendar) || !method_exists($calendar, 'setDayAttributes')) {
            return;
        }

        foreach (self::calendarSpecialDays($startDate, $endDate) as $specialDate => $specialDays) {
            if (empty($specialDays)) {
                continue;
            }

            $specialTimestamp = strtotime($specialDate);
            if (!$specialTimestamp) {
                continue;
            }

            $presentation = self::calendarSpecialDayPresentation($specialDays);

            $calendar->setDayAttributes(
                (int) date('Y', $specialTimestamp),
                (int) date('m', $specialTimestamp),
                (int) date('d', $specialTimestamp),
                $presentation['classes'],
                $presentation['style'],
                $presentation['title'],
                array('special-day-layers' => json_encode($presentation['layers']))
            );
        }
    }

    /**
     * Build classes, style, title and filter layers for one calendar day.
     *
     * @param   array  $specialDays  Ordered special days for the same date.
     *
     * @return  array
     */
    static public function calendarSpecialDayPresentation(array $specialDays)
    {
        if (!$specialDays) {
            return array(
                'classes' => array(),
                'style'   => '',
                'title'   => '',
                'layers'  => array(),
            );
        }

        usort($specialDays, static function ($a, $b) {
            $priority = ((int) ($a['rule_order'] ?? 0)) <=> ((int) ($b['rule_order'] ?? 0));

            if ($priority !== 0) {
                return $priority;
            }

            $dated = (int) !empty($b['is_dated_rule']) <=> (int) !empty($a['is_dated_rule']);

            if ($dated !== 0) {
                return $dated;
            }

            $typePriority = ((int) ($a['priority'] ?? 999)) <=> ((int) ($b['priority'] ?? 999));

            if ($typePriority !== 0) {
                return $typePriority;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        $classes = array('is-special-day');
        $labels = array();
        $layers = array();

        foreach ($specialDays as $specialDay) {
            $type = (string) ($specialDay['type'] ?? '');
            $filterClass = self::calendarSpecialDayTypeClass($type);
            $color = !empty($specialDay['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $specialDay['color'])
                ? strtolower((string) $specialDay['color'])
                : '';
            $title = trim((string) ($specialDay['title'] ?: $type));

            if ($title !== '' && !in_array($title, $labels, true)) {
                $labels[] = $title;
            }

            $classes[] = $filterClass;
            $layers[] = array(
                'filterClass' => $filterClass,
                'color'       => $color,
                'textColor'   => $color !== '' ? (self::getContrastTextColor($color) ?: '#111827') : '',
                'title'       => $title,
            );
        }

        $primaryLayer = array('color' => '', 'textColor' => '');

        foreach ($layers as $layer) {
            if (!empty($layer['color'])) {
                $primaryLayer = $layer;
                break;
            }
        }

        if (!empty($primaryLayer['color'])) {
            $classes[] = 'has-special-day-color';
        }

        return array(
            'classes' => array_values(array_unique($classes)),
            'style'   => !empty($primaryLayer['color'])
                ? '--jem-calendar-special-day-bg:' . $primaryLayer['color'] . ';--jem-calendar-special-day-color:' . $primaryLayer['textColor'] . ';background-color:' . $primaryLayer['color'] . ';color:' . $primaryLayer['textColor'] . ';'
                : '',
            'title'   => implode(', ', $labels),
            'layers'  => $layers,
        );
    }

    static public function renderCalendarSpecialDayBadges($date, ?array $specialDays = null)
    {
        $date = (string) $date;

        if (!self::isValidDate($date)) {
            return '';
        }

        if ($specialDays === null) {
            $days = self::calendarSpecialDays($date, $date);
            $specialDays = $days[$date] ?? array();
        }

        if (!$specialDays) {
            return '';
        }

        $badges = array();

        foreach ($specialDays as $specialDay) {
            $title = trim((string) ($specialDay['title'] ?: ($specialDay['type'] ?? '')));

            if ($title === '') {
                continue;
            }

            $color = !empty($specialDay['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $specialDay['color'])
                ? strtolower((string) $specialDay['color'])
                : '';
            $textColor = $color !== '' ? (self::getContrastTextColor($color) ?: '#111827') : '';
            $icon = trim((string) ($specialDay['icon'] ?? ''));
            $iconHtml = $icon !== '' && preg_match('/^[a-zA-Z0-9_\-\s]+$/', $icon)
                ? '<span class="jem-special-day-badge-icon ' . htmlspecialchars($icon, ENT_COMPAT, 'UTF-8') . '" aria-hidden="true"></span>'
                : '';
            $articleId = (int) ($specialDay['article_id'] ?? 0);
            $externalUrl = trim((string) ($specialDay['url'] ?? ''));
            $href = '';
            $target = '';

            if ($articleId > 0) {
                $href = Route::_('index.php?option=com_content&view=article&id=' . $articleId);
            } elseif ($externalUrl !== '' && filter_var($externalUrl, FILTER_VALIDATE_URL)) {
                $href = $externalUrl;
                $target = ' target="_blank" rel="noopener noreferrer"';
            } elseif ($externalUrl !== '' && (strpos($externalUrl, 'index.php?') === 0 || strpos($externalUrl, '/') === 0)) {
                $href = Route::_($externalUrl);
            }

            $badgeContent = $iconHtml . '<span class="jem-special-day-badge-label">' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</span>';
            $badgeStyle = $color !== ''
                ? '--jem-special-day-badge-color: ' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . '; --jem-special-day-badge-text-color: ' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';'
                : '';

            if ($href !== '') {
                $badges[] = '<a class="jem-special-day-badge jem-special-day-badge-link' . ($color !== '' ? ' has-special-day-color' : '') . '" href="' . htmlspecialchars($href, ENT_COMPAT, 'UTF-8') . '"' . $target . ($badgeStyle !== '' ? ' style="' . $badgeStyle . '"' : '') . '>'
                    . $badgeContent . '</a>';
            } else {
                $badges[] = '<span class="jem-special-day-badge' . ($color !== '' ? ' has-special-day-color' : '') . '"' . ($badgeStyle !== '' ? ' style="' . $badgeStyle . '"' : '') . '>'
                    . $badgeContent . '</span>';
            }
        }

        return $badges
            ? '<div class="jem-special-day-badges">' . implode('', $badges) . '</div>'
            : '';
    }

    /**
     * Build the Types of Days legend items applied in a calendar period.
     *
     * @param   string  $startDate  Period start date, Y-m-d.
     * @param   string  $endDate    Period end date, Y-m-d.
     *
     * @return  array
     */
    static public function calendarSpecialDayLegend($startDate, $endDate)
    {
        $legend = array();

        foreach (self::calendarSpecialDays($startDate, $endDate) as $specialDays) {
            foreach ($specialDays as $specialDay) {
                $type = trim((string) ($specialDay['type'] ?? ''));

                if ($type === '') {
                    $type = trim((string) ($specialDay['title'] ?? ''));
                }

                if ($type === '') {
                    continue;
                }

                $color = !empty($specialDay['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $specialDay['color'])
                    ? strtolower((string) $specialDay['color'])
                    : '';
                $legendKey = strtolower($type);

                if (!isset($legend[$legendKey])) {
                    $legend[$legendKey] = array(
                        'color' => $color,
                        'type' => $type,
                        'filter_class' => self::calendarSpecialDayTypeClass($type),
                        'first_id' => (int) ($specialDay['id'] ?? 0),
                        'first_order' => (int) ($specialDay['rule_order'] ?? 0),
                        'first_start_date' => (string) ($specialDay['rule_start_date'] ?? ''),
                        'first_end_date' => (string) ($specialDay['rule_end_date'] ?? ''),
                        'titles' => array(),
                        'title_dates' => array(),
                        'descriptions' => array(),
                    );
                }

                $legend[$legendKey]['first_id'] = min((int) $legend[$legendKey]['first_id'], (int) ($specialDay['id'] ?? 0));
                $legend[$legendKey]['first_order'] = min((int) $legend[$legendKey]['first_order'], (int) ($specialDay['rule_order'] ?? 0));

                foreach (array('first_start_date' => 'rule_start_date', 'first_end_date' => 'rule_end_date') as $legendField => $specialField) {
                    $specialValue = trim((string) ($specialDay[$specialField] ?? ''));

                    if ($specialValue !== '' && ($legend[$legendKey][$legendField] === '' || strcmp($specialValue, (string) $legend[$legendKey][$legendField]) < 0)) {
                        $legend[$legendKey][$legendField] = $specialValue;
                    }
                }

                $title = trim((string) ($specialDay['title'] ?? ''));
                $date = !empty($specialDay['is_dated_rule']) && !empty($specialDay['show_dates']) ? trim((string) ($specialDay['date'] ?? '')) : '';
                $description = trim((string) ($specialDay['description'] ?? ''));

                if ($title !== '') {
                    $titleKey = ($date !== '' ? $date : '0000-00-00') . '|' . $title;

                    if (!isset($legend[$legendKey]['title_dates'][$titleKey])) {
                        $legend[$legendKey]['title_dates'][$titleKey] = array(
                            'date' => $date,
                            'title' => $title,
                        );
                    }
                }

                if ($description !== '') {
                    if (!in_array($description, $legend[$legendKey]['descriptions'], true)) {
                        $legend[$legendKey]['descriptions'][] = $description;
                    }
                }
            }
        }

        foreach ($legend as &$legendItem) {
            uasort($legendItem['title_dates'], static function ($a, $b) {
                $dateCompare = strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));

                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });

            foreach ($legendItem['title_dates'] as $titleDate) {
                $date = trim((string) ($titleDate['date'] ?? ''));
                $title = trim((string) ($titleDate['title'] ?? ''));

                if ($title === '') {
                    continue;
                }

                if ($date !== '') {
                    $timestamp = strtotime($date);
                    $dateLabel = $timestamp ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC4')) : $date;
                    $legendItem['titles'][] = $dateLabel . ' - ' . $title;
                } else {
                    $legendItem['titles'][] = $title;
                }
            }

            $legendItem['title'] = implode("\n", $legendItem['titles']);
            $legendItem['description'] = implode('; ', $legendItem['descriptions']);

            if (strcasecmp($legendItem['title'], $legendItem['type']) === 0) {
                $legendItem['title'] = '';
            }

            unset($legendItem['titles']);
            unset($legendItem['title_dates']);
            unset($legendItem['descriptions']);
        }
        unset($legendItem);

        return $legend;
    }

    /**
     * Sort Types of Days legend rows for display.
     *
     * @param   array   $legend  Legend rows.
     * @param   string  $order   Sort option.
     *
     * @return  array
     */
    static public function sortCalendarSpecialDayLegend(array $legend, $order = 'order_asc')
    {
        $order = (string) $order;
        $allowed = array('id_asc', 'id_desc', 'order_asc', 'order_desc', 'name_asc', 'name_desc', 'start_date', 'end_date');

        if (!in_array($order, $allowed, true)) {
            $order = 'order_asc';
        }

        uasort($legend, static function ($a, $b) use ($order) {
            switch ($order) {
                case 'id_asc':
                    $result = ((int) ($a['first_id'] ?? 0)) <=> ((int) ($b['first_id'] ?? 0));
                    break;
                case 'id_desc':
                    $result = ((int) ($b['first_id'] ?? 0)) <=> ((int) ($a['first_id'] ?? 0));
                    break;
                case 'order_desc':
                    $result = ((int) ($b['first_order'] ?? 0)) <=> ((int) ($a['first_order'] ?? 0));
                    break;
                case 'name_asc':
                    $result = strcasecmp((string) ($a['type'] ?? ''), (string) ($b['type'] ?? ''));
                    break;
                case 'name_desc':
                    $result = strcasecmp((string) ($b['type'] ?? ''), (string) ($a['type'] ?? ''));
                    break;
                case 'start_date':
                    $result = strcmp((string) ($a['first_start_date'] ?? ''), (string) ($b['first_start_date'] ?? ''));
                    break;
                case 'end_date':
                    $result = strcmp((string) ($a['first_end_date'] ?? ''), (string) ($b['first_end_date'] ?? ''));
                    break;
                case 'order_asc':
                default:
                    $result = ((int) ($a['first_order'] ?? 0)) <=> ((int) ($b['first_order'] ?? 0));
                    break;
            }

            if ($result !== 0) {
                return $result;
            }

            return ((int) ($a['first_id'] ?? 0)) <=> ((int) ($b['first_id'] ?? 0));
        });

        return $legend;
    }

    /**
     * Render the Types of Days legend for a calendar period.
     *
     * @param   string    $startDate  Period start date, Y-m-d.
     * @param   string    $endDate    Period end date, Y-m-d.
     * @param   Registry  $params     View parameters.
     *
     * @return  string
     */
    static public function renderCalendarSpecialDayLegend($startDate, $endDate, $params = null)
    {
        if ($params && method_exists($params, 'get') && (int) $params->get('show_types_of_days_legend', 1) !== 1) {
            return '';
        }

        $legendOrder = $params && method_exists($params, 'get') ? (string) $params->get('special_day_legend_order', 'order_asc') : 'order_asc';
        $legend = self::sortCalendarSpecialDayLegend(self::calendarSpecialDayLegend($startDate, $endDate), $legendOrder);

        if (!$legend) {
            return '';
        }

        $html = array();
        $typeLabel = Text::_('COM_JEM_CALENDAR_TYPE_OF_DAY_TYPE');
        $specialDaysLabel = Text::_('COM_JEM_CALENDAR_TYPE_OF_DAY_SPECIAL_DAYS');
        $descriptionLabel = Text::_('COM_JEM_CALENDAR_TYPE_OF_DAY_DESCRIPTION');
        $html[] = '<div class="jem-annual-special-days-legend">';
        $html[] = '<style>
            .jem-annual-special-days-table-wrap { max-width: 100%; overflow-x: auto; }
            .jem-annual-special-days-table { table-layout: auto; }
            .jem-annual-special-days-table th,
            .jem-annual-special-days-table td { vertical-align: top; }
            .jem-annual-special-days-filter { max-width: 100%; }
            .jem-annual-special-days-filter-name { overflow-wrap: anywhere; }
            .jem-annual-special-days-label,
            .jem-annual-special-days-description { overflow-wrap: anywhere; word-break: normal; }
            @media (max-width: 640px) {
                .jem-annual-special-days-table-wrap { overflow-x: visible; }
                .jem-annual-special-days-table,
                .jem-annual-special-days-table thead,
                .jem-annual-special-days-table tbody,
                .jem-annual-special-days-table tr,
                .jem-annual-special-days-table th,
                .jem-annual-special-days-table td { display: block; width: 100%; }
                .jem-annual-special-days-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
                .jem-annual-special-days-table tr { border: 1px solid #d1d5db; margin-bottom: .75rem; background: #fff; }
                .jem-annual-special-days-table td { border: 0; padding: .45rem .6rem; }
                .jem-annual-special-days-table td::before { content: attr(data-label); display: block; margin-bottom: .2rem; font-weight: 700; color: #374151; }
                .jem-annual-special-days-type .jem-annual-special-days-filter { width: 100%; justify-content: flex-start; }
            }
        </style>';
        $html[] = '<h2 class="jem-annual-special-days-heading">' . Text::_('COM_JEM_CALENDAR_TYPES_OF_DAYS_APPLIED') . '</h2>';
        $html[] = '<div class="jem-annual-special-days-table-wrap">';
        $html[] = '<table class="jem-annual-special-days-table">';
        $html[] = '<thead><tr>'
            . '<th scope="col">' . $typeLabel . '</th>'
            . '<th scope="col">' . $specialDaysLabel . '</th>'
            . '<th scope="col">' . $descriptionLabel . '</th>'
            . '</tr></thead>';
        $html[] = '<tbody>';

        foreach ($legend as $legendItem) {
            $color = htmlspecialchars($legendItem['color'], ENT_COMPAT, 'UTF-8');
            $type = htmlspecialchars($legendItem['type'], ENT_COMPAT, 'UTF-8');
            $filterClass = htmlspecialchars($legendItem['filter_class'], ENT_COMPAT, 'UTF-8');
            $title = nl2br(htmlspecialchars($legendItem['title'], ENT_COMPAT, 'UTF-8'), false);
            $description = trim((string) $legendItem['description']);
            $titleText = $title !== '' ? $title : '-';
            $descriptionText = $description !== '' ? htmlspecialchars($description, ENT_COMPAT, 'UTF-8') : '-';

            $textColor = $color !== '' ? (self::getContrastTextColor($color) ?: '#111827') : '#111827';
            $swatchStyle = $color !== ''
                ? 'background-color:' . $color . ';color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';'
                : 'background-color:transparent;color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';';

            $html[] = '<tr>'
                . '<td class="jem-annual-special-days-type" data-label="' . htmlspecialchars($typeLabel, ENT_COMPAT, 'UTF-8') . '"><button type="button" class="eventSpecialDayType btn btn-outline-dark jem-annual-special-days-filter" data-filter-class="' . $filterClass . '" aria-pressed="true">'
                . '<span class="jem-annual-special-days-filter-color"><span class="jem-annual-special-days-swatch" style="' . $swatchStyle . '">&nbsp;</span></span>'
                . '<span class="jem-annual-special-days-filter-name">' . $type . '</span>'
                . '<span class="visually-hidden">' . $color . '</span></button></td>'
                . '<td class="jem-annual-special-days-label" data-label="' . htmlspecialchars($specialDaysLabel, ENT_COMPAT, 'UTF-8') . '">' . $titleText . '</td>'
                . '<td class="jem-annual-special-days-description" data-label="' . htmlspecialchars($descriptionLabel, ENT_COMPAT, 'UTF-8') . '">' . $descriptionText . '</td>'
                . '</tr>';
        }

        $html[] = '</tbody></table></div></div>';

        return implode("\n", $html);
    }

    /**
     * Returns special days that block event scheduling for the requested period.
     *
     * @param   string  $startDate  Period start date, Y-m-d.
     * @param   string  $endDate    Period end date, Y-m-d.
     *
     * @return  array
     */
    static public function calendarBlockedSpecialDays($startDate, $endDate)
    {
        $blocked = array();

        foreach (self::calendarSpecialDays($startDate, $endDate) as $date => $specialDays) {
            foreach ($specialDays as $specialDay) {
                if (!empty($specialDay['block_events'])) {
                    $blocked[$date][] = $specialDay;
                }
            }
        }

        return $blocked;
    }

    /**
     * Retrieves the CSS-settings from database and stores in an static object
     */
    static public function retrieveCss()
    {
        static $registryCSS;
        if (!is_object($registryCSS)) {
            $registryCSS = new Registry(self::config()->css);
        }

        return $registryCSS;
    }

    /**
     * Setup a file logger for JEM.
     */
    static public function addFileLogger()
    {
        JemLog::addFileLogger();
    }

    /**
     * Add en entry to JEM's log file.
     *
     * @param  $message The message to print
     * @param  $where   The location the message was generated, default: null
     * @param  $type    The log level, default: DEBUG
     */
    static public function addLogEntry($message, $where = null, $type = Log::DEBUG)
    {
        JemLog::add($message, $where, $type);
    }

    /**
     * Performs daily scheduled cleanups
     *
     * Currently it archives and removes outdated events
     * and takes care of the recurrence of events
     */
    static public function cleanup($forced = 0)
    {
        $jemsettings  = JemHelper::config();
        $weekstart    = $jemsettings->weekdaystart;

        $now = time(); // UTC
        $offset = idate('Z'); // timezone offset for "new day" test
        $lastupdate = (int)$jemsettings->lastupdate;
        $runningupdate = isset($jemsettings->runningupdate) ? $jemsettings->runningupdate : 0;
        $maxexectime = get_cfg_var('max_execution_time');
        $delay = min(86400, max(300, $maxexectime * 2));

        // New (local) day since last update?
        $nrdaysnow = floor(($now + $offset) / 86400);
        $nrdaysupdate = floor(($lastupdate + $offset) / 86400);

        if (($nrdaysnow > $nrdaysupdate) || $forced) {
            JemHelper::addLogEntry('forced: ' . $forced . ', now: '. $now . ', last update: ' . $lastupdate .
                                   ', running update: ' . $runningupdate . ', delay: ' . $delay . ', tz-offset: ' . $offset, __METHOD__);

            if (($runningupdate + $delay) < $now) {
                // Set timestamp of running cleanup
                JemConfig::getInstance()->set('runningupdate', $now);

                JemHelper::addLogEntry('  do cleanup...', __METHOD__);

                // trigger an event to let plugins handle whatever cleanup they want to do.
                if (PluginHelper::importPlugin('jem')) {
                    $dispatcher = JemFactory::getDispatcher();
                    $dispatcher->triggerEvent('onJemBeforeCleanup', array($jemsettings, $forced));
                }

                $db = Factory::getContainer()->get('DatabaseDriver');

                // Get the last event occurrence of each recurring published events, with unlimited repeat, or last date not passed.
                // Ignore published field to prevent duplicate events.
                // All column names are hardcoded constants — no user input reaches this query.
                // quoteName() is omitted on internal column names for readability; the real guard is
                // that $minusDays (below) is cast to int before any interpolation.
                $query = $db->getQuery(true)
                    ->select(array(
                        'id',
                        'CASE recurrence_first_id WHEN 0 THEN id ELSE recurrence_first_id END AS first_id',
                        'recurrence_number', 'recurrence_type', 'recurrence_limit_date',
                        'recurrence_limit', 'recurrence_byday', 'recurrence_bylastday',
                        'MAX(dates) AS dates', 'MAX(enddates) AS enddates',
                        'MAX(recurrence_counter) AS counter',
                    ))
                    ->from('#__jem_events')
                    ->where('recurrence_type <> ' . $db->quote('0'))
                    ->where('CASE WHEN recurrence_limit_date IS NULL THEN 1 ELSE ' . $db->quote(self::getJoomlaDate()) . ' < recurrence_limit_date END')
                    ->where('recurrence_number <> ' . $db->quote('0'))
                    ->group('first_id')
                    ->order('dates DESC');

                $db->setQuery($query);
                $recurrence_array = $db->loadAssocList();

                // If there are results we will be doing something with it
                foreach ($recurrence_array as $recurrence_row)
                {
                    // get the info of reference event for the duplicates
                    $ref_event = Table::getInstance('Event', 'JemTable');
                    $ref_event->load($recurrence_row['id']);

                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $query = $db->getQuery(true);
                    $query->select('*');
                    $query->from($db->quoteName('#__jem_events').' AS a');
                    $query->where('id = '.(int)$recurrence_row['id']);
                    $db->setQuery($query);
                    $reference = $db->loadAssoc();

                    // if reference event is "unpublished"(0) new event is "unpublished" too
                    // but on "archived"(2) and "trashed"(-2) reference events create "published"(1) event
                    if ($reference['published'] != 0) {
                        $reference['published'] = 1;
                    }

                    // the first day of the week is used for certain rules
                    $recurrence_row['weekstart'] = $weekstart;

                    // calculate next occurence date
                    $recurrence_row = JemHelper::calculate_recurrence($recurrence_row);

                    switch ($recurrence_row["recurrence_type"]) {
                        case 1:
                            $anticipation    = $jemsettings->recurrence_anticipation_day;
                            break;
                        case 2:
                            $anticipation    = $jemsettings->recurrence_anticipation_week;
                            break;
                        case 3:
                            $anticipation    = $jemsettings->recurrence_anticipation_month;
                            break;
                        case 4:
                            $anticipation    = $jemsettings->recurrence_anticipation_week;
                            break;
                        case 5:
                            $anticipation    = $jemsettings->recurrence_anticipation_year;
                            break;
                        case 6:
                            $anticipation    = $jemsettings->recurrence_anticipation_lastday;
                            break;
                        default:
                            $anticipation    = $jemsettings->recurrence_anticipation_day;
                            break;
                    }

                    // add events as long as we are under the interval and under the limit, if specified.
                    $shieldDate = new Date('now + ' . $anticipation . ' month');
                    while (($recurrence_row['recurrence_limit_date'] == null
                            || strtotime($recurrence_row['dates']) <= strtotime($recurrence_row['recurrence_limit_date']))
                            && strtotime($recurrence_row['dates']) <= strtotime($shieldDate))
                    {
                        $new_event = Table::getInstance('Event', 'JemTable');
                        $new_event->bind($reference, array('id', 'hits', 'dates', 'enddates','checked_out_time','checked_out'));
                        $new_event->recurrence_first_id = $recurrence_row['first_id'];
                        $new_event->recurrence_counter = $recurrence_row['counter'] + 1;
                        $new_event->dates = $recurrence_row['dates'];
                        $new_event->enddates = $recurrence_row['enddates'];
                        $new_event->_autocreate = true; // to tell table class this has to be stored AS IS (the underscore is important!)

                        if ($new_event->store())
                        {
                            $recurrence_row['counter']++;
                            // Duplicate categories event relationships via INSERT INTO ... SELECT.
                            // Both ids are cast to int — no user input is interpolated.
                            $insertQuery = 'INSERT INTO #__jem_cats_event_relations (itemid, catid)'
                                . ' SELECT ' . (int) $new_event->id . ', catid'
                                . ' FROM #__jem_cats_event_relations'
                                . ' WHERE itemid = ' . (int) $ref_event->id;

                            $db->setQuery($insertQuery);

                            if ($db->execute() === false) {
                                // run query always but don't show error message to "normal" users
                                $user = JemFactory::getUser();
                                if ($user->authorise('core.manage')) {
                                    // Escape title to prevent XSS in admin error output.
                                    $safeTitle = htmlspecialchars($ref_event->title, ENT_QUOTES, 'UTF-8');
                                    echo 'Error saving categories for event "' . $safeTitle . '" new recurrences' . "\n";
                                }
                            }
                        }

                        $recurrence_row = JemHelper::calculate_recurrence($recurrence_row);
                    }
                }

                // The only dynamic value is $minusDays — cast to int to eliminate any injection risk
                // even if the stored setting were somehow corrupted. Column names are hardcoded constants.
                $minusDays    = (int) $jemsettings->minus;
                $outdatedWhere = 'dates > 0 AND ' . $db->quote(self::getJoomlaDate(-$minusDays)) . ' > (IF (enddates IS NOT NULL, enddates, dates))';

                //delete outdated events
                if ($jemsettings->oldevent == 1) {
                    $db->setQuery('DELETE FROM #__jem_events WHERE ' . $outdatedWhere);
                    $db->execute();
                }

                //Set state archived of outdated events
                if ($jemsettings->oldevent == 2) {
                    $db->setQuery('UPDATE #__jem_events SET published = 2 WHERE ' . $outdatedWhere . ' AND published = 1');
                    $db->execute();
                }

                //Set state trashed of outdated events
                if ($jemsettings->oldevent == 3) {
                    $db->setQuery('UPDATE #__jem_events SET published = -2 WHERE ' . $outdatedWhere . ' AND published = 1');
                    $db->execute();
                }

                //Set state unpublished of outdated events
                if ($jemsettings->oldevent == 4) {
                    $db->setQuery('UPDATE #__jem_events SET published = 0 WHERE ' . $outdatedWhere . ' AND published = 1');
                    $db->execute();
                }

                // Cleanup orphaned registrations (events that no longer exist).
                $db->setQuery('DELETE FROM #__jem_register WHERE event NOT IN (SELECT id FROM #__jem_events)');
                $db->execute();

                // Set timestamp of last cleanup
                JemConfig::getInstance()->set('lastupdate', $now);
                // Clear timestamp of running cleanup
                JemConfig::getInstance()->set('runningupdate', 0);
            }

            JemHelper::addLogEntry('finished.', __METHOD__);
        }
    }

    /**
     * this methode calculate the next date
     */
    static public function calculate_recurrence($recurrence_row)
    {
        // get the recurrence information
        $recurrence_number = $recurrence_row['recurrence_number'];
        $recurrence_type = $recurrence_row['recurrence_type'];

        $day_time = 86400;    // 60s * 60min * 24h
        $week_time = $day_time * 7;
        $date_array = JemHelper::generate_date($recurrence_row['dates'], $recurrence_row['enddates']);

        switch($recurrence_type) {
            case "1":
                // +1 hour for the Summer to Winter clock change
                $start_day = mktime(1, 0, 0, $date_array["month"], $date_array["day"], $date_array["year"]);
                $start_day = $start_day + ($recurrence_number * $day_time);
                break;
            case "2":
                // +1 hour for the Summer to Winter clock change
                $start_day = mktime(1, 0, 0, $date_array["month"], $date_array["day"], $date_array["year"]);
                $start_day = $start_day + ($recurrence_number * $week_time);
                break;
            case "3": // month recurrence
                /*
                 * warning here, we have to make sure the date exists:
                 * 31 of october + 1 month = 31 of november, which doesn't exists => skip the date!
                 */
                $start_day = mktime(1,0,0,($date_array["month"] + $recurrence_number),$date_array["day"],$date_array["year"]);

                $i = 1;
                while (date('d', $start_day) != $date_array["day"] && $i < 20) { // not the same day of the month... try next date !
                    $i++;
                    $start_day = mktime(1,0,0,($date_array["month"] + $recurrence_number*$i),$date_array["day"],$date_array["year"]);
                }
                break;
            case "4": // weekday
                // the selected weekdays
                $selected = JemHelper::convert2CharsDaysToInt(explode(',', $recurrence_row['recurrence_byday']), 0);
                $days_names = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
                $litterals = array('first', 'second', 'third', 'fourth', 'fifth');
                if (count($selected) == 0)
                {
                    // this shouldn't happen, but if it does, to prevent problem use the current weekday for the repetition.
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_WRONG_EVENTRECURRENCE_WEEKDAY'), 'warning');
                    $current_weekday = (int) $date_array["weekday"];
                    $selected = array($current_weekday);
                }

                $start_day = null;
                foreach ($selected as $s)
                {
                    $next = null;
                    $nextmonth = null;

                    switch ($recurrence_number) {
                        case 7: // before last 'x' of the month
                            $next      = strtotime("previous ".$days_names[$s].' - 1 week ',
                                            mktime(1,0,0,$date_array["month"]+1 ,1,$date_array["year"]));
                            $nextmonth = strtotime("previous ".$days_names[$s].' - 1 week ',
                                            mktime(1,0,0,$date_array["month"]+2 ,1,$date_array["year"]));
                            break;
                        case 6: // last 'x' of the month
                            $next      = strtotime("previous ".$days_names[$s],
                                            mktime(1,0,0,$date_array["month"]+1 ,1,$date_array["year"]));
                            $nextmonth = strtotime("previous ".$days_names[$s],
                                            mktime(1,0,0,$date_array["month"]+2 ,1,$date_array["year"]));
                            break;
                        case 5: // 5th of the month
                            $currentMonth = $date_array["month"];
                            do {
                                $timeFisrtDayMonth = mktime(1,0,0, $currentMonth ,1,$date_array["year"]);
                                $timeLastDayNextMonth = mktime(23, 59, 59, $currentMonth+1, 0, $date_array["year"]);
                                $next = strtotime($litterals[$recurrence_number - 1] . " " . $days_names[$s] . ' of this month',$timeFisrtDayMonth);
                                $currentMonth++;
                            } while ($next > $timeLastDayNextMonth || $next < $date_array['unixtime']);
                            break;
                        case 4: // xth 'x' of the month
                        case 3:
                        case 2:
                        case 1:
                        default:
                            $next      = strtotime($litterals[$recurrence_number-1]." ".$days_names[$s].' of this month',
                                            mktime(1,0,0,$date_array["month"]   ,1,$date_array["year"]));
                            $nextmonth = strtotime($litterals[$recurrence_number-1]." ".$days_names[$s].' of this month',
                                            mktime(1,0,0,$date_array["month"]+1 ,1,$date_array["year"]));
                            break;
                    }

                    // is the next / nextm day eligible for next date ?
                    if ($next && $next > strtotime($recurrence_row['dates'])) // after current date !
                    {
                        if (!$start_day || $start_day > $next) { // comes before the current 'start_date'
                            $start_day = $next;
                        }
                    }
                    if ($nextmonth && (!$start_day || $start_day > $nextmonth)) {
                        $start_day = $nextmonth;
                    }
                }
                break;
            case "5": // year recurrence
                $start_day = mktime(1,0,0,($date_array["month"]),$date_array["day"],$date_array["year"]+ $recurrence_number);
                break;
            case "6": // last day recurrence
                $selected = $recurrence_row['recurrence_bylastday'];
                $lastdays_names = array('L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7');
                $lastday_number = array_search($selected, $lastdays_names);
                $start_day = mktime(1, 0, 0, ($date_array["month"] + $recurrence_number), 1, $date_array["year"]); // Set day to 1 to avoid issues
                $last_day_of_month = (int)date('t', $start_day);
                $day_of_month = $last_day_of_month - $lastday_number;
                $start_day = mktime(1, 0, 0, ($date_array["month"] + $recurrence_number), $day_of_month, $date_array["year"]);
                break;
        }

        if (!$start_day) {
            return false;
        }
        $recurrence_row['dates'] = date("Y-m-d", $start_day);

        if ($recurrence_row['enddates']) {
            $recurrence_row['enddates'] = date("Y-m-d", $start_day + $date_array["day_diff"]);
        }

        if ($start_day < $date_array["unixtime"]) {
            throw new Exception(Text::_('COM_JEM_RECURRENCE_DATE_GENERATION_ERROR'), 500);
        }

        return $recurrence_row;
    }

    /**
     * Method to dissolve recurrence of given id.
     *
     * @param  int     The id to clear as recurrence first id.
     *
     * @return boolean True on success.
     */
    static public function dissolve_recurrence($first_id)
    {
        // Sanitize the id.
        $first_id = (int)$first_id;

        if (empty($first_id)) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $db->setQuery('UPDATE #__jem_events'
                        . ' SET recurrence_first_id = 0, recurrence_type = 0'
                        . '   , recurrence_counter = 0, recurrence_number = 0'
                        . '   , recurrence_limit = 0, recurrence_limit_date = null'
                        . '   , recurrence_byday = ' . $db->quote('')
                        . ' WHERE recurrence_first_id = ' . $first_id
                         );
            $db->execute();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * This method deletes an image file if unused.
     *
     * @param  string $type     one of 'event', 'venue', 'category', 'events', 'venues', 'categories'
     * @param  mixed  $filename filename as stored in db, or null (which deletes all unused files)
     *
     * @return bool true on success, false on error
     * @access public
     */
    static public function delete_unused_image_files($type, $filename = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        switch ($type) {
        case 'event':
        case 'events':
            $folder = 'events';
            $countquery_tmpl = ' SELECT id FROM #__jem_events WHERE datimage = %s OR fullimage = %s';
            $imagequery      = ' SELECT datimage AS image, COUNT(*) AS count FROM #__jem_events WHERE datimage <> ' . $db->quote('') . ' GROUP BY datimage'
                . ' UNION SELECT fullimage AS image, COUNT(*) AS count FROM #__jem_events WHERE fullimage <> ' . $db->quote('') . ' GROUP BY fullimage';
            break;
        case 'venue':
        case 'venues':
            $folder = 'venues';
            $countquery_tmpl = ' SELECT id FROM #__jem_venues WHERE locimage = ';
            $imagequery      = ' SELECT locimage AS image, COUNT(*) AS count FROM #__jem_venues GROUP BY locimage';
            break;
        case 'category':
        case 'categories':
            $folder = 'categories';
            $countquery_tmpl = ' SELECT id FROM #__jem_categories WHERE image = ';
            $imagequery      = ' SELECT image, COUNT(*) AS count FROM #__jem_categories GROUP BY image';
            break;
        default:
            return false;
        }

        $fullPath = Path::clean(JPATH_SITE.'/images/jem/'.$folder.'/'.$filename);
        $fullPaththumb = Path::clean(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$filename);
        if (is_file($fullPath)) {
            // Count usage and don't delete if used elsewhere.
            $db = Factory::getContainer()->get('DatabaseDriver');
            $quotedFilename = $db->quote($filename);
            $db->setQuery(strpos($countquery_tmpl, '%s') !== false ? sprintf($countquery_tmpl, $quotedFilename, $quotedFilename) : $countquery_tmpl . $quotedFilename);
            if (null === ($usage = $db->loadObjectList())) {
                return false;
            }
            if (empty($usage)) {
                File::delete($fullPath);
                if (is_file($fullPaththumb)) {
                    File::delete($fullPaththumb);
                }

                return true;
            }
        }
        elseif (empty($filename) && is_dir($fullPath)) {
            // get image files used
            $db = Factory::getContainer()->get('DatabaseDriver');
            $db->setQuery($imagequery);
            if (null === ($used = $db->loadAssocList('image', 'count'))) {
                return false;
            }

            // get all files and delete if not in $used
            $fileList = Folder::files($fullPath);
            if ($fileList !== false) {
                foreach ($fileList as $file)
                {
                    if (is_file($fullPath.$file) && substr($file, 0, 1) != '.' && !isset($used[$file])) {
                        File::delete($fullPath.$file);
                        if (is_file($fullPaththumb.$file)) {
                            File::delete($fullPaththumb.$file);
                        }
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * This method deletes attachment files if unused.
     *
     * @param  mixed       $type  one of 'event', 'venue', 'category', ... or false for all
     * @param  object|null $stats Optional cleanup counters.
     *
     * @return bool true on success, false on error
     * @access public
     */
    static public function delete_unused_attachment_files($type = false, &$stats = null)
    {
        $jemsettings = JemHelper::config();
        $relativePath = trim((string) $jemsettings->attachments_path);
        $stats = (object) array(
            'files'   => 0,
            'folders' => 0,
            'failed'  => 0,
        );

        if ($relativePath === '') {
            return true;
        }

        $basePath = Path::clean(JPATH_SITE . '/' . $relativePath);
        $sitePath = rtrim(Path::clean(JPATH_SITE), '\\/');

        if (
            $basePath === $sitePath
            || strpos(rtrim($basePath, '\\/') . DIRECTORY_SEPARATOR, $sitePath . DIRECTORY_SEPARATOR) !== 0
            || !Folder::exists($basePath)
        ) {
            return true;
        }

        $type = $type ? preg_replace('/[^a-z0-9_-]/i', '', (string) $type) : false;
        $folders = Folder::folders($basePath, '.', false, true, array('.', '..'));
        $objects = array();

        foreach ($folders as $folder) {
            $object = basename($folder);

            if (!preg_match('/^[a-z]+[0-9]+$/i', $object)) {
                continue;
            }

            if ($type && stripos($object, $type) !== 0) {
                continue;
            }

            $objects[$object] = $folder;
        }

        if (!$objects) {
            return true;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $quotedObjects = array_map(array($db, 'quote'), array_keys($objects));
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('object'), $db->quoteName('file')))
            ->from($db->quoteName('#__jem_attachments'))
            ->where($db->quoteName('object') . ' IN (' . implode(',', $quotedObjects) . ')');

        $db->setQuery($query);
        $filesUsed = $db->loadObjectList() ?: array();
        $usedFiles = array();

        foreach ($filesUsed as $used) {
            $usedFiles[$used->object . '/' . $used->file] = true;
        }

        // Delete unused files and folders (ignore 'index.html')
        foreach ($objects as $object => $folder) {
            $folderFiles = Folder::files($folder, '.', false, true, array('index.html'), array());

            foreach ($folderFiles as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $filename = basename($file);

                if (isset($usedFiles[$object . '/' . $filename])) {
                    continue;
                }

                if (File::delete($file)) {
                    $stats->files++;
                } else {
                    $stats->failed++;
                    JemHelper::addLogEntry('Unable to delete unused attachment file: ' . $file, __METHOD__, Log::WARNING);
                }
            }

            $remainingFiles = Folder::files($folder, '.', true, true, array('index.html'), array());

            if (empty($remainingFiles)) {
                if (Folder::delete($folder)) {
                    $stats->folders++;
                } else {
                    $stats->failed++;
                    JemHelper::addLogEntry('Unable to delete empty attachment folder: ' . $folder, __METHOD__, Log::WARNING);
                }
            }
        }

        return $stats->failed === 0;
    }

    /**
     * this method generate the date string to a date array
     *
     * @param  string the date string
     * @return array  the date informations
     * @access public
     */
    static public function generate_date($startdate, $enddate)
    {
        $validStardate = JemHelper::isValidDate($startdate);
        $validEnddate = JemHelper::isValidDate($enddate);

        if($validStardate) {
            $startdate = explode("-", $startdate);
        $date_array = array("year" => $startdate[0],
                            "month" => $startdate[1],
                            "day" => $startdate[2],
                            "weekday" => date("w",mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0])),
                            "unixtime" => mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0]));

            if ($validEnddate) {
                $enddate = explode("-", $enddate);
                $day_diff = (mktime(1, 0, 0, $enddate[1], $enddate[2], $enddate[0]) - mktime(1, 0, 0, $startdate[1], $startdate[2], $startdate[0]));
                $date_array["day_diff"] = $day_diff;
            }


            return $date_array;
        }else{
            return false;
        }
    }

    /**
     * return day number of the week starting with 0 for first weekday
     *
     * @param  array of 2 letters day
     * @return array of int
     */
    static function convert2CharsDaysToInt($days, $firstday = 0)
    {
        $result = array();
        foreach ($days as $day)
        {
            switch (strtoupper($day))
            {
                case 'MO':
                    $result[] = 1 - $firstday;
                    break;
                case 'TU':
                    $result[] = 2 - $firstday;
                    break;
                case 'WE':
                    $result[] = 3 - $firstday;
                    break;
                case 'TH':
                    $result[] = 4 - $firstday;
                    break;
                case 'FR':
                    $result[] = 5 - $firstday;
                    break;
                case 'SA':
                    $result[] = 6 - $firstday;
                    break;
                case 'SU':
                    $result[] = (7 - $firstday) % 7;
                    break;
                default:
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_WRONG_EVENTRECURRENCE_WEEKDAY'), 'warning');
            }
        }

        return $result;
    }


    /**
     * Build the select list for access level
     */
    static public function getAccesslevelOptions($ownonly = false, $disabledLevels = false)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $where = '';
        $selDisabled = '';
        if ($ownonly) {
            $levels = Factory::getApplication()->getIdentity()->getAuthorisedViewLevels();
            $allLevels = $levels;
            if (!empty($disabledLevels)) {
                if (!is_array($disabledLevels)) {
                    $disabledLevels = array($disabledLevels);
                }
                foreach ($disabledLevels as $level) {
                    if (((int)$level > 0) && (!in_array((int)$level, $levels))) {
                        $allLevels[] = $level;
                    }
                }
                $selDisabled = ', IF (id IN ('.implode(',', $levels).'), \'\', \'disabled\') AS disabled';
            }
            $where = ' WHERE id IN ('.implode(',', $allLevels).')';
        }

        $query = 'SELECT id AS value, title AS text' . $selDisabled
               . ' FROM #__viewlevels'
               . $where
               . ' ORDER BY ordering, id'
               ;

        //JemHelper::addLogEntry('AccessLevel query: ' . $query, __METHOD__);

        $db->setQuery($query);
        $groups = $db->loadObjectList();

        //JemHelper::addLogEntry('result: ' . print_r($groups, true), __METHOD__);

        return $groups;
    }

    static public function buildtimeselect($max, $name, $selected, $class = array('class'=>'inputbox'))
    {
        $min = 0;
        $timelist = array();
        $timelist[0] = HTMLHelper::_('select.option', '', '');

        $jemreg = JemConfig::getInstance()->toRegistry();

        if ($max == 23) {
            // does user prefer 12 or 24 hours format?

            $format = $jemreg->get('formathour', false);
        } else {
            $format = false;
        }

        $settings = JemHelper::globalattribs();

        if ($name == 'starthours' || $name == 'endhours'){
            $min = $settings->get('global_editevent_starttime_limit');
            $max = $settings->get('global_editevent_endtime_limit');
            foreach (range($min, $max) as $value) {
                if ($value < 10) {
                    $value = '0'.$value;
                }

                $timelist[] = HTMLHelper::_('select.option', $value, ($format ? date($format, strtotime("$value:00:00")) : $value));
            }
        } else if ($name=='startminutes' || $name=='endminutes'){
            $block = $settings->get('global_editevent_minutes_block');
            for ($value = 0; $value <=59; $value += $block) {
                if ($value < 10) {
                    $value = '0'.$value;
                }

                $timelist[] = HTMLHelper::_('select.option', $value, $value);
            }
        } else {
            foreach (range($min, $max) as $value) {
                if ($value < 10) {
                    $value = '0'.$value;
                }

                $timelist[] = HTMLHelper::_('select.option', $value, ($format ? date($format, strtotime("$value:00:00")) : $value));
            }
        }

        return HTMLHelper::_('select.genericlist', $timelist, $name, $class, 'value', 'text', $selected);
    }

    /**
     * returns mime type of a file
     *
     * @param  string file path
     * @return string mime type
     */
    static public function getMimeType($filename)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else if (function_exists('mime_content_type') && 0)
        {
            return mime_content_type($filename);
        }
        else
        {
            $mime_types = array(
                'txt' => 'text/plain',
                'htm' => 'text/html',
                'html' => 'text/html',
                'php' => 'text/html',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'swf' => 'application/x-shockwave-flash',
                'flv' => 'video/x-flv',

                // images
                'png' => 'image/png',
                'webp' => 'image/webp',
                'jpe' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'ico' => 'image/vnd.microsoft.icon',
                'tiff' => 'image/tiff',
                'tif' => 'image/tiff',
                'svg' => 'image/svg+xml',
                'svgz' => 'image/svg+xml',

                // archives
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'exe' => 'application/x-msdownload',
                'msi' => 'application/x-msdownload',
                'cab' => 'application/vnd.ms-cab-compressed',

                // audio/video
                'mp3' => 'audio/mpeg',
                'qt' => 'video/quicktime',
                'mov' => 'video/quicktime',

                // adobe
                'pdf' => 'application/pdf',
                'psd' => 'image/vnd.adobe.photoshop',
                'ai' => 'application/postscript',
                'eps' => 'application/postscript',
                'ps' => 'application/postscript',

                // ms office
                'doc' => 'application/msword',
                'rtf' => 'application/rtf',
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',

                // open office
                'odt' => 'application/vnd.oasis.opendocument.text',
                'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            );

            //$ext = strtolower(array_pop(explode('.',$filename)));
            $var = explode('.',$filename);
            $ext = strtolower(array_pop($var));
            if (array_key_exists($ext, $mime_types)) {
                return $mime_types[$ext];
            }
            else {
                return 'application/octet-stream';
            }
        }
    }

    /**
     * updates waiting list of specified event
     *
     * @param  int     event id
     * @param  boolean bump users off/to waiting list
     * @return bool
     */
    static public function updateWaitingList($event)
    {
        return self::reconcileWaitingList($event)->success;
    }

    /**
     * Return the complete result of an automatic waiting-list reconciliation.
     *
     * @param  int    $event    Event identifier.
     * @param  array  $options  Promotion options such as source or excludeIds.
     * @return object Structured promotion result.
     */
    static public function reconcileWaitingList($event, array $options = array())
    {
        $options['mode'] = JemWaitingListPromotion::MODE_AUTOMATIC;
        $result = JemWaitingListPromotion::promote((int) $event, $options);

        if (!$result->success) {
            self::addLogEntry(
                'Waiting-list reconciliation failed for event ' . (int) $event . ': ' . (string) $result->reason,
                __METHOD__,
                Log::ERROR
            );

            if (JemFactory::getUser()->authorise('jem.attendees.manage', 'com_jem')) {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_JEM_WAITINGLIST_PROMOTION_FAILED'),
                    'warning'
                );
            }
        }

        if ($result->reason === 'automatic_disabled'
            && $result->waitingListEnabled
            && $result->maxPlaces > 0
            && $result->availableBefore > 0
            && $result->waitingBefore > 0
            && JemFactory::getUser()->authorise('jem.attendees.manage', 'com_jem')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
                    'COM_JEM_WAITINGLIST_MANUAL_ACTION_REQUIRED',
                    $result->availableBefore,
                    $result->waitingBefore
                ),
                'notice'
            );
        }

        if ($result->success
            && $result->reason === 'notification_failed'
            && JemFactory::getUser()->authorise('jem.attendees.manage', 'com_jem')) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_JEM_WAITINGLIST_PROMOTION_NOTIFICATION_FAILED'),
                'warning'
            );
        }

        return $result;
    }

    /**
     * Adds attendees numbers to rows
     *
     * @param  $data reference to event rows
     * @return false on error, $data on success
     */
    static public function getAttendeesNumbers(& $data)
    {
        // Make sure this is an array and it is not empty
        if (!is_array($data) || !count($data)) {
            return false;
        }

        // Get the ids of events
        $ids = array();
        foreach ($data as $event) {
            $ids[] = (int)$event->id;
        }
        $ids = implode(",", $ids);

        $db = Factory::getContainer()->get('DatabaseDriver');

        // status 1: user registered (attendee or waiting list), status -1: user exlicitely unregistered, status 0: user is invited but hadn't answered yet
        $query = ' SELECT COUNT(id) as total,'
               . '        SUM(IF(status =  1 AND waiting = 0, places, 0)) AS registered,'
               . '        SUM(IF(status =  1 AND waiting >  0, places, 0)) AS waiting,'
               . '        SUM(IF(status = -1,                  places, 0)) AS unregistered,'
               . '        SUM(IF(status =  0,                  places, 0)) AS invited,'
               . '        event '
               . ' FROM #__jem_register '
               . ' WHERE event IN (' . $ids .')'
               . ' GROUP BY event ';

        $db->setQuery($query);
        $res = $db->loadObjectList('event');

        foreach ($data as $k => &$event) { // by reference for direct edit
            if (isset($res[$event->id])) {
                $event->regTotal   = $res[$event->id]->total;
                $event->regCount   = $res[$event->id]->registered;
                $event->reserved   = $event->reservedplaces;
                $event->waiting    = $res[$event->id]->waiting;
                $event->unregCount = $res[$event->id]->unregistered;
                $event->invited    = $res[$event->id]->invited;
            } else {
                $event->regTotal   = 0;
                $event->regCount   = 0;
                $event->reserved   = 0;
                $event->waiting    = 0;
                $event->unregCount = 0;
                $event->invited    = 0;
            }
            $event->available = max(0, $event->maxplaces - $event->regCount -$event->reservedplaces);
        }

        return $data;
    }

    /**
     * returns timezone name
     */
    static public function getTimeZoneName()
    {
        return self::getJoomlaTimeZoneName();
    }

    /**
     * return initialized calendar tool class for ics export
     *
     * @return \Kigkonsult\Icalcreator\Vcalendar
     */
    static public function getCalendarTool()
    {
        require_once JPATH_SITE.'/components/com_jem/classes/icalcreator/autoload.php';
        $timezone_name = JemHelper::getTimeZoneName();

        $vcal = \Kigkonsult\Icalcreator\Vcalendar::factory([
            \Kigkonsult\Icalcreator\IcalInterface::UNIQUE_ID => 'com_jem',
        ]);
        $vcal->setCalscale('GREGORIAN');
        $vcal->setMethod('PUBLISH');
        if ($timezone_name) {
            $vcal->setXprop('X-WR-TIMEZONE', $timezone_name);
        }
        return $vcal;
    }

    /**
     * Send an iCalendar response and stop Joomla from appending template/plugin output.
     *
     * @param   \Kigkonsult\Icalcreator\Vcalendar  $calendartool  Calendar instance.
     * @param   string                             $filename      Download filename.
     *
     * @return  void
     */
    static public function sendCalendar($calendartool, $filename)
    {
        $filename = basename(str_replace(array("\r", "\n", '"'), '', (string) $filename));
        if ($filename === '') {
            $filename = 'events.ics';
        }

        $output = $calendartool->createCalendar();
        $output = preg_replace("/\r\n|\r|\n/", "\r\n", $output);

        if (substr($output, -2) !== "\r\n") {
            $output .= "\r\n";
        }

        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }

        header('Content-Type: text/calendar; charset=utf-8', true);
        header('Content-Disposition: attachment; filename="' . $filename . '"', true);
        header('Cache-Control: no-cache, no-store, must-revalidate', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
        header('Content-Length: ' . strlen($output), true);

        echo $output;
        Factory::getApplication()->close();
    }

    static public function icalAddEvent(&$calendartool, $event)
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_jem', JPATH_SITE . '/components/com_jem', null, true);
        $language->load('com_jem', JPATH_ADMINISTRATOR . '/components/com_jem', null, true);
        $language->load('com_jem', JPATH_SITE, null, false);

        $jemsettings   = JemHelper::config();
        $timezone_name = JemHelper::getEventTimeZoneName($event);
        $config        = Factory::getConfig();
        $sitename      = $config->get('sitename');
        $uri           = Uri::getInstance();

        // get categories names
        $categories = array();
        foreach ($event->categories as $c) {
            $categories[] = $c->catname;
        }

        // no start date...
        $validdate = JemHelper::isValidDate($event->dates);

        if (!$event->dates || !$validdate) {
            return false;
        }

        // make end date same as start date if not set
        if (!$event->enddates) {
            $event->enddates = $event->dates;
        }

        // validate start date format
        if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $event->dates, $start_date)) {
            throw new Exception(Text::_('COM_JEM_ICAL_EXPORT_WRONG_STARTDATE_FORMAT'), 0);
        }

        // all day event if start time is not set
        if (!$event->times) // all day !
        {
            // build start DateTime (date only)
            $dtStart      = new \DateTime($event->dates);
            $dtStartParams = ['VALUE' => 'DATE'];

            // for ical all day events, dtend must be the next day
            $event->enddates = date('Y-m-d', strtotime($event->enddates . ' +1 day'));

            if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $event->enddates, $end_date)) {
                throw new Exception(Text::_('COM_JEM_ICAL_EXPORT_WRONG_ENDDATE_FORMAT'), 0);
            }

            $dtEnd       = new \DateTime($event->enddates);
            $dtEndParams = ['VALUE' => 'DATE'];
        }
        else // not all day events, there is a start time
        {
            if (!preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/', $event->times, $start_time)) {
                throw new Exception(Text::_('COM_JEM_ICAL_EXPORT_WRONG_STARTTIME_FORMAT'), 0);
            }

            $tz           = $timezone_name ? new \DateTimeZone($timezone_name) : null;
            $dtStart      = new \DateTime($event->dates . ' ' . $event->times, $tz);
            $dtStartParams = ['VALUE' => 'DATE-TIME'];
            if ($jemsettings->ical_tz == 1 && $timezone_name) {
                $dtStartParams['TZID'] = $timezone_name;
            }

            if (!$event->endtimes || $event->endtimes == '00:00:00') {
                $event->endtimes = $event->times;
            }

            // if same day but end time < start time, change end date to +1 day
            if ($event->enddates == $event->dates &&
                strtotime($event->dates . ' ' . $event->endtimes) < strtotime($event->dates . ' ' . $event->times))
            {
                $event->enddates = date('Y-m-d', strtotime($event->enddates . ' +1 day'));
            }

            if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $event->enddates, $end_date)) {
                throw new Exception(Text::_('COM_JEM_ICAL_EXPORT_WRONG_ENDDATE_FORMAT'), 0);
            }

            if (!preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/', $event->endtimes, $end_time)) {
                throw new Exception(Text::_('COM_JEM_ICAL_EXPORT_WRONG_STARTTIME_FORMAT'), 0);
            }

            $dtEnd       = new \DateTime($event->enddates . ' ' . $event->endtimes, $tz);
            $dtEndParams = ['VALUE' => 'DATE-TIME'];
            if ($jemsettings->ical_tz == 1 && $timezone_name) {
                $dtEndParams['TZID'] = $timezone_name;
            }
        }

        $link = $uri->root() . JemHelperRoute::getEventRoute($event->slug);
        $link = Route::_($link);

        $onlineMeetingUrl = self::getOnlineMeetingUrl($event);
        $onlineMeetingLabel = self::getOnlineMeetingLabel($event);

        if ($onlineMeetingUrl === '') {
            $onlineMeetingLink = self::getOnlineMeetingEventLink($event);

            if ($onlineMeetingLink['url'] !== '') {
                $onlineMeetingUrl = $onlineMeetingLink['url'];

                if ($onlineMeetingLink['label'] !== '') {
                    $onlineMeetingLabel = $onlineMeetingLink['label'];
                }
            }
        }

        $onlineMeetingPlatform = $onlineMeetingUrl !== '' ? self::getOnlineMeetingPlatform($onlineMeetingUrl) : array('key' => '', 'label' => '');
        $includeOnlineMeetingInIcs = (int) self::globalattribs()->get('event_online_meeting_ics', 1) === 1;
        $includeOnlineMeetingInDescription = (int) self::globalattribs()->get('event_online_meeting_ics_description', 1) === 1;

        // item description text
        $description = $event->title . "\n\n";
        if ($onlineMeetingUrl !== '' && $includeOnlineMeetingInIcs && $includeOnlineMeetingInDescription) {
            $description .= Text::_('COM_JEM_ONLINE_MEETING') . ': ' . $onlineMeetingLabel . ' - ' . $onlineMeetingUrl . "\n";
        }

        $description .= Text::_('COM_JEM_CATEGORY') . ': ' . implode(', ', $categories) . "\n";
        $description .= Text::_('COM_JEM_ICS_EVENT_LINK') . ': ' . $link . "\n";

        $htmlDescription = '<html><body>';
        $htmlDescription .= '<p>' . htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8') . '</p>';

        if ($onlineMeetingUrl !== '' && $includeOnlineMeetingInIcs && $includeOnlineMeetingInDescription) {
            $htmlDescription .= '<p><strong>' . htmlspecialchars(Text::_('COM_JEM_ONLINE_MEETING'), ENT_QUOTES, 'UTF-8') . ':</strong> '
                . '<a href="' . htmlspecialchars($onlineMeetingUrl, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($onlineMeetingLabel, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }

        $htmlDescription .= '<p><strong>' . htmlspecialchars(Text::_('COM_JEM_CATEGORY'), ENT_QUOTES, 'UTF-8') . ':</strong> '
            . htmlspecialchars(implode(', ', $categories), ENT_QUOTES, 'UTF-8') . '</p>';
        $htmlDescription .= '<p><strong>' . htmlspecialchars(Text::_('COM_JEM_ICS_EVENT_LINK'), ENT_QUOTES, 'UTF-8') . ':</strong> '
            . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>';
        $htmlDescription .= '</body></html>';

        // location
        $hasAddressLocation = (isset($event->street) && trim((string) $event->street) !== '')
            || (isset($event->postalCode) && trim((string) $event->postalCode) !== '')
            || (isset($event->city) && trim((string) $event->city) !== '')
            || (isset($event->countryname) && trim((string) $event->countryname) !== '');
        $hasCoordinateLocation = isset($event->latitude, $event->longitude)
            && is_numeric($event->latitude)
            && is_numeric($event->longitude)
            && (float) $event->latitude != 0.0
            && (float) $event->longitude != 0.0;
        $hasPhysicalLocation = $hasAddressLocation || $hasCoordinateLocation;

        $location = array();
        if (isset($event->venue) && trim((string) $event->venue) !== '' && $hasPhysicalLocation) {
            $location[] = trim((string) $event->venue);
        }

        if (isset($event->street) && !empty($event->street)) {
            $location[] = $event->street;
        }

        if (isset($event->postalCode) && !empty($event->postalCode) && isset($event->city) && !empty($event->city)) {
            $location[] = $event->postalCode . ' ' . $event->city;
        } else {
            if (isset($event->postalCode) && !empty($event->postalCode)) {
                $location[] = $event->postalCode;
            }
            if (isset($event->city) && !empty($event->city)) {
                $location[] = $event->city;
            }
        }

        if (isset($event->countryname) && !empty($event->countryname)) {
            $exp = explode(",", $event->countryname);
            $location[] = $exp[0];
        }

        $location = implode(",", $location);

        // Build vevent using iCalcreator v2.41 API
        $e = $calendartool->newVevent();
        $e->setSummary($event->title);
        $e->setCategories(implode(', ', $categories));
        $e->setDtstart($dtStart, $dtStartParams);
        $e->setDtend($dtEnd, $dtEndParams);
        $e->setDescription($description);
        $e->setXprop('X-ALT-DESC', $htmlDescription, array('FMTTYPE' => 'text/html'));
        if ($location !== '') {
            $e->setLocation($location);
        }
        if ($onlineMeetingUrl !== '' && $includeOnlineMeetingInIcs) {
            $e->setConference($onlineMeetingUrl, array('FEATURE' => 'AUDIO,VIDEO', 'LABEL' => $onlineMeetingLabel));

            if ($onlineMeetingPlatform['key'] === 'teams') {
                $e->setXprop('X-MICROSOFT-SKYPETEAMSMEETINGURL', $onlineMeetingUrl);
                $e->setXprop('X-MICROSOFT-LOCATIONDISPLAYNAME', $onlineMeetingLabel);
                $e->setXprop('X-MICROSOFT-CDO-ONLINEMEETINGINFORMATION', $onlineMeetingUrl);
            }
        }
        $e->setUrl($link);
        $e->setUid('event' . $event->id . '@' . $sitename);

        return true;
    }

    /**
     * return true is a date is valid (not null, or 0000-00...)
     *
     * @param  string $date
     * @return boolean
     */
    static public function isValidDate($date)
    {
        if (is_null($date)) {
            return false;
        }
        if ($date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
            return false;
        }
        if (!strtotime($date)) {
            return false;
        }
        return true;
    }

    /**
     * Test a date against an exact calendar format without normalising invalid values.
     *
     * Unlike strtotime(), this rejects impossible dates such as 2027-02-29 and
     * 2027-04-31. Empty values are not dates; callers can allow them explicitly.
     *
     * @param   mixed   $date    Date value to validate.
     * @param   string  $format  Expected PHP date format.
     *
     * @return  boolean
     */
    static public function isValidCalendarDate($date, $format = 'Y-m-d')
    {
        if (!is_string($date) || $date === '') {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $date);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($parsed === false) {
            return false;
        }

        if ($errors !== false && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0)) {
            return false;
        }

        return $parsed->format($format) === $date;
    }

    /**
     * return true is a time is valid (not null, or 00:00:00...)
     *
     * @param  string $time
     * @return boolean
     */
    static public function isValidTime($time)
    {
        if (is_null($time)) {
            return false;
        }

        if (!strtotime($time)) {
            return false;
        }
        return true;
    }

    /**
     * Returns array of positive numbers
     *
     * @param  mixed array or string with comma separated list of ids
     * @return mixed array of numbers greater zero or false
     */
    static public function getValidIds($ids_in)
    {
        $ids_out = array();
        if($ids_in) {
            $tmp = is_array($ids_in) ? $ids_in : explode(',', $ids_in);
            if (!empty($tmp)) {
                foreach ($tmp as $id) {
                    if ((int)$id > 0) {
                        $ids_out[] = (int)$id;
                    }
                }
            }
        }

        return (empty($ids_out) ? false : $ids_out);
    }

    /**
     * Adds optional event page layout parameters to an event route.
     *
     * @param   string    $route   Internal event route.
     * @param   Registry  $params  Module parameters.
     *
     * @return  string
     */
    static public function applyEventRouteLayout($route, $params)
    {
        $eventLayout = (string) $params->get('event_link_event_layout', '');
        $venueLayout = (string) $params->get('event_link_venue_layout', '');
        $query = array();

        if (in_array($eventLayout, array('details', 'compact'), true)) {
            $query['jem_event_layout'] = $eventLayout;
        }

        if (in_array($venueLayout, array('details', 'compact'), true)) {
            $query['jem_venue_layout'] = $venueLayout;
        }

        if (!$query) {
            return $route;
        }

        $fragment = '';
        $hashPos = strpos($route, '#');

        if ($hashPos !== false) {
            $fragment = substr($route, $hashPos);
            $route = substr($route, 0, $hashPos);
        }

        $separator = strpos($route, '?') === false ? '?' : '&';

        foreach ($query as $key => $value) {
            $route .= $separator . rawurlencode($key) . '=' . rawurlencode($value);
            $separator = '&';
        }

        return $route . $fragment;
    }

    /**
     * Creates a tooltip
     */
    static public function caltooltip($tooltip, $title = '', $text = '', $href = '', $class = '', $time = '', $color = '')
    {
        HTMLHelper::_('bootstrap.tooltip');
        if (0) { /* old style using 'hasTip' */
            $title = HTMLHelper::tooltipText($title, '<div style="font-weight:normal;">'.$tooltip.'</div>', 0);
        } else { /* new style using 'has Tooltip' */
            $class = str_replace('hasTip', '', $class) . ' hasTooltip';
            $title = HTMLHelper::tooltipText($title, $tooltip, 0); // this calls htmlspecialchars()
        }
        $tooltip = '';


        if ($href) {
            $href = Route::_ ($href);
            $time = preg_replace('/(<br\s*\/?>\s*)+$/i', '', (string) $time);
            $eventText = ($time !== '' ? '<span class="jem-calendar-event-time">' . $time . '</span>' : '')
                . '<span class="jem-calendar-event-title">' . $text . '</span>';
            $tip = '<span class="'.$class.'" data-bs-toggle="tooltip" data-bs-html="true" data-bs-original-title="'.$title.$tooltip.'"><a href="'.$href.'">'.$eventText.'</a></span>';
        } else {
            $tip = '<span class="'.$class.'" data-bs-toggle="tooltip" data-bs-html="true" data-bs-original-title="'.$title.$tooltip.'">'.$text.'</span>';
        }

        return $tip;
    }

    /**
     * Return a readable text color for a hexadecimal background color.
     */
    static public function getContrastTextColor($backgroundColor)
    {
        $color = trim((string) $backgroundColor);

        if ($color === '') {
            return '';
        }

        if ($color[0] === '#') {
            $color = substr($color, 1);
        }

        if (strlen($color) === 3 && preg_match('/^[0-9a-f]{3}$/i', $color)) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        if (!preg_match('/^[0-9a-f]{6}$/i', $color)) {
            return '';
        }

        $red   = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue  = hexdec(substr($color, 4, 2));

        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $brightness < 140 ? '#fff' : '#000';
    }

    /**
     * Function to retrieve IP
     * @author: https://gist.github.com/cballou/2201933
     */
    static public function retrieveIP()
    {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    // trim for safety measures
                    $ip = trim($ip);
                    // attempt to validate IP
                    if (self::validate_ip($ip)) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
    }

    /**
     * Gets the IP value that should be stored according to JEM privacy settings.
     *
     * @return string|false
     */
    static public function getStoredIP()
    {
        $jemsettings = self::config();

        if (empty($jemsettings->storeip)) {
            return false;
        }

        $ip = self::retrieveIP();

        if (!$ip) {
            return false;
        }

        $mode = isset($jemsettings->storeipmode) ? (string) $jemsettings->storeipmode : 'full';

        switch ($mode) {
            case 'anonymized':
                return self::anonymizeIP($ip);

            case 'hash':
                $secret = (string) Factory::getApplication()->get('secret', '');

                return 'sha256:' . hash_hmac('sha256', $ip, $secret);

            case 'full':
            default:
                return $ip;
        }
    }

    /**
     * Removes host-level precision from an IP address before storage.
     *
     * @param   string  $ip  The detected IP address.
     *
     * @return string|false
     */
    static public function anonymizeIP($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);

            if ($packed !== false) {
                return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
            }
        }

        return false;
    }

    /**
     * Ensures an ip address is both a valid IP and does not fall within
     * a private network range.
     *
     * @author: https://gist.github.com/cballou/2201933
     */
    static public function validate_ip($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        return true;
    }

    static public function getLayoutStyleSuffix()
    {
        $jemsettings = self::config();
        $layoutstyle = isset($jemsettings->layoutstyle) ? (int)$jemsettings->layoutstyle : 0;

        return $layoutstyle === 1 ? 'responsive' : '';

    }

    /**
     * Return the CSS/layout basename from Joomla's module layout value.
     *
     * Joomla normally stores alternative layouts as "template:layout", but
     * older or incomplete module instances can contain only "layout" or an
     * empty value. Normalising it here prevents requests for an empty .css
     * filename and keeps those module instances on the default stylesheet.
     *
     * @param   string  $layout  Stored Joomla module layout value.
     *
     * @return  string
     */
    static public function getModuleLayoutName($layout = 'default')
    {
        $layout = (string) $layout;

        if (strpos($layout, ':') !== false) {
            $parts = explode(':', $layout, 2);
            $layout = $parts[1];
        }

        $layout = trim($layout);

        return $layout !== '' ? $layout : 'default';
    }

    /**
     * Get the path to a layout for a module respecting layout style configured in JEM Settings.
     *
     * @param   string  $module  The name of the module
     * @param   string  $layout  The name of the module layout. If alternative layout, in the form template:filename.
     *
     * @return  string  The path to the module layout
     *
     * @since   2.3
     */
    public static function getModuleLayoutPath($module, $layout = 'default')
    {
        $template = Factory::getApplication()->getTemplate();
        $defaultLayout = $layout;
        $suffix = self::getLayoutStyleSuffix();

        if (strpos($layout, ':') !== false)
        {
            // Get the template and file name from the string
            $temp = explode(':', $layout);
            $template = $temp[0] === '_' ? $template : $temp[0];
            $layout = $temp[1];
            $defaultLayout = $temp[1] ?: 'default';
        }

        // Build the template and base path for the layout
        $pathes = array();
        if (!empty($suffix)) {
            $pathes[] = JPATH_THEMES . '/' . $template . '/html/' . $module . '/' . $suffix . '/' . $layout . '.php';
            $pathes[] = JPATH_BASE . '/modules/' . $module . '/tmpl/' . $suffix . '/' . $defaultLayout . '.php';
        }
        $pathes[] = JPATH_THEMES . '/' . $template . '/html/' . $module . '/' . $layout . '.php';
        $pathes[] = JPATH_BASE . '/modules/' . $module . '/tmpl/' . $defaultLayout . '.php';

        // Return the first match
        foreach ($pathes as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        // last chance
        return JPATH_BASE . '/modules/' . $module . '/tmpl/default.php';
    }

    static public function loadCss($css)
    {
        $settings = self::retrieveCss();
        $app      = Factory::getApplication();
        $wa       = $app->getDocument()->getWebAssetManager();
        $isAdmin  = $app->isClient('administrator');
        // The layout style setting belongs to the frontend. Administrator
        // views always use the single canonical backend stylesheet.
        $layoutSuffix = $isAdmin ? '' : self::getLayoutStyleSuffix();
        $expectedSuffix = $layoutSuffix ? '-' . $layoutSuffix : '';
        $suffix   = $expectedSuffix !== '' && substr($css, -strlen($expectedSuffix)) !== $expectedSuffix
            ? $expectedSuffix
            : '';
        $variant  = $css . $suffix;
        $key      = str_replace('-', '_', $variant);
        $baseKey  = str_replace('-', '_', $css);
        $styleUri = '';

        $hasVariantSetting = $suffix
            && ($settings->get('css_' . $key . '_usecustom', null) !== null || $settings->get('css_' . $key . '_customfile', null) !== null);

        $configKey = $hasVariantSetting ? $key : $baseKey;

        if ($settings->get('css_' . $configKey . '_usecustom', '0')) {
            $file = (string) $settings->get('css_' . $configKey . '_customfile', '');
            $file = $file ? preg_replace('%^/([^/]*)%', '$1', $file) : '';

            if ($file && File::getExt($file) === 'css' && is_file(JPATH_SITE . '/media/com_jem/css/custom/' . $file)) {
                $styleUri = 'media/com_jem/css/custom/' . $file;
            }

            if ($styleUri === '' && is_file(JPATH_SITE . '/media/com_jem/css/custom/' . $variant . '.css')) {
                $styleUri = 'media/com_jem/css/custom/' . $variant . '.css';
            }

            if ($styleUri === '' && is_file(JPATH_SITE . '/media/com_jem/css/custom/' . $css . '.css')) {
                $styleUri = 'media/com_jem/css/custom/' . $css . '.css';
            }
        }

        if ($styleUri === '') {
            $template = (string) $app->getTemplate();
            $templateRoot = $isAdmin ? JPATH_ADMINISTRATOR . '/templates/' : JPATH_THEMES . '/';
            $templateUri  = $isAdmin ? 'administrator/templates/' : 'templates/';
            $templateBase = $templateRoot . $template . '/css/com_jem/';

            if (is_file($templateBase . $variant . '.css')) {
                $styleUri = $templateUri . $template . '/css/com_jem/' . $variant . '.css';
            } elseif (is_file(JPATH_SITE . '/media/com_jem/css/' . $variant . '.css')) {
                $styleUri = 'media/com_jem/css/' . $variant . '.css';
            } elseif ($variant !== $css && is_file($templateBase . $css . '.css')) {
                $styleUri = $templateUri . $template . '/css/com_jem/' . $css . '.css';
            } else {
                $styleUri = 'media/com_jem/css/' . $css . '.css';
            }
        }

        $asset = ($isAdmin ? 'com_jem.admin.' : 'com_jem.frontend.') . str_replace('_', '-', $variant);

        if ($wa->assetExists('style', $asset)) {
            $wa->useStyle($asset);
        } else {
            $wa->registerAndUseStyle($asset, $styleUri);
        }

        if (!$isAdmin) {
            self::$frontendCssAssets[$asset] = $asset;
        }

        return $wa;
    }

    /**
     * Load the optional frontend user override stylesheet once.
     *
     * This file is an additive override layer. It is loaded after the normal
     * component stylesheets used by the current JEM frontend view.
     *
     * @return  void
     */
    static public function loadFrontendUserCss()
    {
        self::loadUserCssFile(
            'jem-user-front.css',
            'com_jem.user.front',
            array_values(self::$frontendCssAssets)
        );
    }

    /**
     * Load the optional module user override stylesheet once.
     *
     * This file is an additive override layer. It is loaded after the normal
     * stylesheet used by a JEM module.
     *
     * @return  void
     */
    static public function loadModuleUserCss()
    {
        self::loadUserCssFile('jem-user-module.css', 'com_jem.user.module');
    }

    /**
     * Load an optional user override CSS file from media/com_jem/css/custom.
     *
     * @param   string  $file          The CSS file name.
     * @param   string  $asset         The WebAssetManager asset name.
     * @param   array   $dependencies  Styles that must be rendered first.
     *
     * @return  void
     */
    protected static function loadUserCssFile($file, $asset, $dependencies = array())
    {
        $path = JPATH_SITE . '/media/com_jem/css/custom/' . $file;

        if (!is_file($path)) {
            return;
        }

        $app = Factory::getApplication();
        $wa  = $app->getDocument()->getWebAssetManager();

        if (method_exists($wa, 'assetExists') && $wa->assetExists('style', $asset)) {
            if ($wa->isAssetActive('style', $asset)) {
                $wa->disableStyle($asset);
            }
            $wa->useStyle($asset);
            return;
        }

        $wa->registerAndUseStyle(
            $asset,
            'media/com_jem/css/custom/' . $file,
            array(),
            array(),
            $dependencies
        );
    }

    /**
     * Get the url to a css file for a module respecting layout style configured in JEM Settings.
     *
     * @param   string  $module  The name of the module
     * @param   string  $css     CSS basename. Empty values use the module's default stylesheet.
     *
     * @since   2.3
     */
    public static function loadModuleStyleSheet($module, $css)
    {
        $app = Factory::getApplication();
        $wa = $app->getDocument()->getWebAssetManager();
        $templateName = $app->getTemplate();
        $css = self::getModuleLayoutName($css);
        $filestyle = $css . '.css';
        $asset = $module . ($css ? '.' . $css : '');
        $styleUri = '';

        //Search for template overrides
        if(file_exists(JPATH_SITE . '/templates/' . $templateName . '/css/' . $module . '/' . $filestyle)) {
            $styleUri = 'templates/' . $templateName . '/css/'. $module . '/' . $filestyle;
        }
        //Search for template overrides
        else if (file_exists(JPATH_SITE . '/templates/' . $templateName . '/html/' . $module . '/' . $filestyle)) {
            $styleUri = 'templates/' . $templateName . '/html/'. $module . '/' . $filestyle;
        }
        //Search in media folder
        else if (file_exists(JPATH_SITE . '/media/' . $module . '/css/' . $filestyle)) {
            $styleUri = 'media/' . $module . '/css/' . $filestyle;
        }
        //Search in the module
        else if (file_exists(JPATH_SITE . '/modules/' . $module . '/tmpl/' . $filestyle)) {
            $styleUri = 'modules/'. $module . '/tmpl/' . $filestyle;
        }
        //Error no css file found
        else {
            JemHelper::addLogEntry("Warning: The file " . $filestyle . " couldn't be found.", __METHOD__);
            return;
        }

        if ($wa->assetExists('style', $asset)) {
            $wa->useStyle($asset);
        } else {
            $wa->registerAndUseStyle($asset, $styleUri);
        }
    }

    static public function loadIconFont()
    {
        $jemsettings = JemHelper::config();
        if ($jemsettings->useiconfont == 1) {
            $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
            $wa->registerAndUseStyle('com_jem.fontawesome', 'com_jem/vendor/fontawesome-free/css/all.min.css');
            $wa->registerAndUseStyle('com_jem.iconfont',    'com_jem/css/jem-icon-font.css');
        }
    }

    static public function defineCenterMap($data = false)
    {
        # retrieve venue
        $venue = $data->getValue('venue');

        if ($venue) {
            # latitude/longitude
            $lat  = $data->getValue('latitude');
            $long = $data->getValue('longitude');

            if ($lat == 0.000000) {
                $lat = null;
            }

            if ($long == 0.000000) {
                $long = null;
            }

            if ($lat && $long) {
                $location = '['.$data->getValue('latitude').','.$data->getValue('longitude').']';
            } else {
                # retrieve address-info
                $postalCode = $data->getValue('postalCode');
                $city       = $data->getValue('city');
                $street     = $data->getValue('street');

                $location = '"'.$street.' '.$postalCode.' '.$city.'"';
            }
            $location = 'location:'.$location.',';
        } else {
            $location = '';
        }

        return $location;
    }

    /**
     * Load Custom CSS
     *
     * @return boolean
     */
    static public function loadCustomCss()
    {
        $app         = Factory::getApplication();
        $document    = $app->getDocument();
        $settings    = self::retrieveCss();
        $jemsettings = self::config();
        $layoutstyle = isset($jemsettings->layoutstyle) ? (int)$jemsettings->layoutstyle : 0;
        $style       = "";

        # background-colors
        $bg_filter            = $settings->get('css_color_bg_filter');
        $bg_h2                = $settings->get('css_color_bg_h2');
        $bg_jem               = $settings->get('css_color_bg_jem');
        $bg_table_th          = $settings->get('css_color_bg_table_th');
        $bg_table_td          = $settings->get('css_color_bg_table_td');
        $bg_table_tr_entry2   = $settings->get('css_color_bg_table_tr_entry2');
        $bg_table_tr_hover    = $settings->get('css_color_bg_table_tr_hover');
        $bg_table_tr_featured = $settings->get('css_color_bg_table_tr_featured');
        # border-colors
        $border_filter        = $settings->get('css_color_border_filter');
        $border_h2            = $settings->get('css_color_border_h2');
        $border_table_th      = $settings->get('css_color_border_table_th');
        $border_table_td      = $settings->get('css_color_border_table_td');
        # font-color
        $font_table_h2        = $settings->get('css_color_font_h2');
        $font_table_th        = $settings->get('css_color_font_table_th');
        $font_table_td        = $settings->get('css_color_font_table_td');
        $font_table_td_a      = $settings->get('css_color_font_table_td_a');
        $filter_selector      = "#jem_filter, div#jem #jem_filter, #jem #jem_filter, #jem.jem_select_contact #jem_filter, #jem.jem_select_venue #jem_filter, #jem.jem_select_users #jem_filter, #jem.jem_select_article #jem_filter";

        switch ($layoutstyle) {
        case 1: // 'Default (Responsive Style)'
            if (!empty($bg_filter)) {
                $style .= $filter_selector . " {background-color:" . $bg_filter . " !important;}";
            }
            if (!empty($bg_h2)) {
                $style .= "div#jem h2 {background-color:".$bg_h2.";}";
            }
            if (!empty($bg_jem)) {
                $style .= "div#jem {background-color:".$bg_jem.";}";
            }
            if (!empty($bg_table_th)) {
                $style .= "div#jem .jem-misc, div#jem .jem-sort-small {background-color:" . $bg_table_th . ";}";
            }
            if (!empty($bg_table_td)) { //Caused by the row-layout of JEM-Responsive, there exist no cells, we use that for row-color
                $style .= "div#jem .eventlist li:nth-child(odd) {background-color:" . $bg_table_td . ";}";
            }
            if (!empty($bg_table_tr_entry2)) {
                $style .= "div#jem .eventlist li:nth-child(even) {background-color:" . $bg_table_tr_entry2 . ";}";
            }
            if (!empty($bg_table_tr_featured)) {
                $style .= "div#jem .eventlist .jem-featured {background-color:" . $bg_table_tr_featured . ";}";
            }
            // Important: :hover must be after .featured to overrule
            if (!empty($bg_table_tr_hover)) {
                $style .= "div#jem .eventlist li:hover {background-color:" . $bg_table_tr_hover . ";}";
            }
            if (!empty($border_filter)) {
                $style .= $filter_selector . " {border-color:" . $border_filter . " !important;}";
            }
            if (!empty($border_h2)) {
                $style .= "div#jem h2 {border: 1px solid " . $border_h2 . ";}";
            }
            if (!empty($border_table_th)) {
                $style .= "div#jem .jem-misc, div#jem .jem-sort-small {border: 1px solid " . $border_table_th . ";}";
            }
            if (!empty($border_table_td)) {
                $style .= "div#jem .jem-event, div#jem .jem-event:first-child {border-color: " . $border_table_td . ";}";
            }
            if (!empty($font_table_h2)) {
                $style .= "div#jem h2 {color:" . $font_table_h2 . ";}";
            }
            if (!empty($font_table_th)) {
                $style .= "div#jem .jem-misc, div#jem .jem-sort-small {color:" . $font_table_th . ";}";
            }
            if (!empty($font_table_td)) {
                $style .= "div#jem .jem-event {color:" . $font_table_td . ";}";
            }
            if (!empty($font_table_td_a)) {
                $style .= "div#jem .jem-event a {color:" . $font_table_td_a . ";}";
            }
            break;
        default: // 'Legacy (Table Style)'
            if (!empty($bg_filter)) {
                $style .= $filter_selector . " {background-color:" . $bg_filter . " !important;}";
            }
            if (!empty($bg_h2)) {
                $style .= "div#jem h2 {background-color:".$bg_h2.";}";
            }
            if (!empty($bg_jem)) {
                $style .= "div#jem {background-color:".$bg_jem.";}";
            }
            if (!empty($bg_table_th)) {
                $style .= "div#jem table.eventtable th {background-color:" . $bg_table_th . ";}";
            }
            if (!empty($bg_table_td)) {
                $style .= "div#jem table.eventtable td {background-color:" . $bg_table_td . ";}";
            }
            if (!empty($bg_table_tr_entry2)) {
                $style .= "div#jem table.eventtable tr.sectiontableentry2 td {background-color:" . $bg_table_tr_entry2 . ";}";
            }
            if (!empty($bg_table_tr_featured)) {
                $style .= "div#jem table.eventtable tr.featured td {background-color:" . $bg_table_tr_featured . ";}";
            }
            // Important: :hover must be after .featured to overrule
            if (!empty($bg_table_tr_hover)) {
                $style .= "div#jem table.eventtable tr:hover td {background-color:" . $bg_table_tr_hover . ";}";
            }
            if (!empty($border_filter)) {
                $style .= $filter_selector . " {border-color:" . $border_filter . " !important;}";
            }
            if (!empty($border_h2)) {
                $style .= "div#jem h2 {border-color:".$border_h2.";}";
            }
            if (!empty($border_table_th)) {
                $style .= "div#jem table.eventtable th {border-color:" . $border_table_th . ";}";
            }
            if (!empty($border_table_td)) {
                $style .= "div#jem table.eventtable td {border-color:" . $border_table_td . ";}";
            }
            if (!empty($font_table_h2)) {
                $style .= "div#jem h2 {color:" . $font_table_h2 . ";}";
            }
            if (!empty($font_table_th)) {
                $style .= "div#jem table.eventtable th {color:" . $font_table_th . ";}";
            }
            if (!empty($font_table_td)) {
                $style .= "div#jem table.eventtable td {color:" . $font_table_td . ";}";
            }
            if (!empty($font_table_td_a)) {
                $style .= "div#jem table.eventtable td a {color:" . $font_table_td_a . ";}";
            }
            break;
        } // switch

        $document->addStyleDeclaration($style);

        return true;
    }

    /**
     * Loads Custom Tags
     *
     * @return boolean
     */
    static public function loadCustomTag()
    {
        // emtpy method
    }

    /**
     * Get a variable from the manifest file (actually, from the manifest cache).
     *
     * @param  $column  manifest_cache(1),params(2)
     * @param  $setting name of setting to retrieve
     * @param  $type    compononent(1), plugin(2)
     * @param  $name    name to search in column name
     */
    static public function getParam($column, $setting, $type, $name)
    {
        switch ($column) {
            case 1:
                $column = 'manifest_cache';
                break;
            case 2:
                $column = 'params';
                break;
        }

        switch ($type) {
            case 1:
                $type = 'component';
                break;
            case 2:
                $type = 'plugin';
                break;
            case 3:
                $type = 'module';
                break;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select(array($column));
        $query->from('#__extensions');
        $query->where(array('name = '.$db->quote($name),'type = '.$db->quote($type)));
        $db->setQuery($query);

        $manifest = json_decode($db->loadResult(), true);
        $result = $manifest[ $setting ];

        if (empty($result)) {
            $result = 'N/A';
        }

        return $result;
    }

    static public function getCountryOptions()
    {
        $options = array();
        $options = array_merge(JemHelperCountries::getCountryOptions(),$options);

        array_unshift($options, HTMLHelper::_('select.option', '0', Text::_('COM_JEM_SELECT_COUNTRY')));

        return $options;
    }

    /**
     * This method transliterates a string into a URL
     * safe string or returns a URL safe UTF-8 string
     * based on the global configuration
     *
     * @param  string  $string  String to process
     *
     * @return string  Processed string
     *
     * @see    ApplicationHelper
     * @since  2.1.7
     */
    static public function stringURLSafe($string)
    {
        return ApplicationHelper::stringURLSafe($string);
    }

    /**
     * This method returns true if a string is within another string.
     *
     * @param  string $masterstring
     * @param  string $string
     * @return boolean
     */
    static public function jemStringContains($masterstring, $string)
    {
        return ($masterstring && $string && strpos($masterstring, $string) !== false);
    }
}
