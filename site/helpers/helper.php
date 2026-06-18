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

/**
 * Holds some usefull functions to keep the code a bit cleaner
 */
class JemHelper
{
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

        $articleIds = array();

        foreach ($events as $event) {
            if (!empty($event->article_id)) {
                $articleIds[] = (int) $event->article_id;
            }
        }

        $articleIds = array_values(array_unique(array_filter($articleIds)));

        if (!$articleIds) {
            return array();
        }

        $levels = array_map('intval', $levels);

        if (!$levels) {
            return array();
        }

        $db       = Factory::getContainer()->get('DatabaseDriver');
        $nullDate = $db->quote($db->getNullDate());
        $nowDate  = $db->quote(Factory::getDate()->toSql());
        $query    = $db->getQuery(true);

        $query->select(array(
            $db->quoteName('a.id'),
            $db->quoteName('a.title'),
            $db->quoteName('a.alias'),
            $db->quoteName('a.catid'),
            $db->quoteName('a.created_by')
        ))
            ->from($db->quoteName('#__content', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid') . ' AND ' . $db->quoteName('c.extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('a.id') . ' IN (' . implode(',', $articleIds) . ')')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.access') . ' IN (' . implode(',', $levels) . ')')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('c.access') . ' IN (' . implode(',', $levels) . ')')
            ->where('(' . $db->quoteName('a.publish_up') . ' IS NULL OR ' . $db->quoteName('a.publish_up') . ' = ' . $nullDate . ' OR ' . $db->quoteName('a.publish_up') . ' <= ' . $nowDate . ')')
            ->where('(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' . $db->quoteName('a.publish_down') . ' = ' . $nullDate . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $nowDate . ')');

        if (Multilanguage::isEnabled()) {
            $language = Factory::getApplication()->getLanguage()->getTag();
            $query->where($db->quoteName('a.language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')');
        }

        try {
            $db->setQuery($query);

            return $db->loadObjectList('id') ?: array();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return array();
        }
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
                $query = $db->getQuery(true);

                // Get the last event occurence of each recurring published events, with unlimited repeat, or last date not passed.
                // Ignore published field to prevent duplicate events.
                $query = ' SELECT id, CASE recurrence_first_id WHEN 0 THEN id ELSE recurrence_first_id END AS first_id, '
                       . ' recurrence_number, recurrence_type, recurrence_limit_date, recurrence_limit, recurrence_byday, recurrence_bylastday, '
                       . ' MAX(dates) as dates, MAX(enddates) as enddates, MAX(recurrence_counter) as counter '
                       . ' FROM #__jem_events '
                       . ' WHERE recurrence_type <> "0" '
                       . ' AND CASE  WHEN recurrence_limit_date IS null THEN 1 ELSE NOW() < recurrence_limit_date END '
                       . ' AND recurrence_number <> "0" '
                       . ' GROUP BY first_id'
                       . ' ORDER BY dates DESC';

                $db->SetQuery($query);
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
                            //duplicate categories event relationships
                            $query = ' INSERT INTO #__jem_cats_event_relations (itemid, catid) '
                                   . ' SELECT ' . $db->Quote($new_event->id) . ', catid FROM #__jem_cats_event_relations '
                                   . ' WHERE itemid = ' . $db->Quote($ref_event->id);
                            $db->setQuery($query);

                            if ($db->execute() === false) {
                                // run query always but don't show error message to "normal" users
                                $user = JemFactory::getUser();
                                if($user->authorise('core.manage')) {
                                    echo Text::_('Error saving categories for event "' . $ref_event->title . '" new recurrences\n');
                                }
                            }
                        }

                        $recurrence_row = JemHelper::calculate_recurrence($recurrence_row);
                    }
                }

                //delete outdated events
                if ($jemsettings->oldevent == 1) {
                    $query = 'DELETE FROM #__jem_events WHERE dates > 0 AND '
                           .' DATE_SUB(NOW(), INTERVAL '.(int)$jemsettings->minus.' DAY) > (IF (enddates IS NOT NULL, enddates, dates))';
                    $db->SetQuery($query);
                    $db->execute();
                }

                //Set state archived of outdated events
                if ($jemsettings->oldevent == 2) {
                    $query = 'UPDATE #__jem_events SET published = 2 WHERE dates > 0 AND '
                           .' DATE_SUB(NOW(), INTERVAL '.(int)$jemsettings->minus.' DAY) > (IF (enddates IS NOT NULL, enddates, dates)) '
                           .' AND published = 1';
                    $db->SetQuery($query);
                    $db->execute();
                }

                //Set state trashed of outdated events
                if ($jemsettings->oldevent == 3) {
                    $query = 'UPDATE #__jem_events SET published = -2 WHERE dates > 0 AND '
                           .' DATE_SUB(NOW(), INTERVAL '.(int)$jemsettings->minus.' DAY) > (IF (enddates IS NOT NULL, enddates, dates)) '
                           .' AND published = 1';
                    $db->SetQuery($query);
                    $db->execute();
                }

                //Set state unpublished of outdated events
                if ($jemsettings->oldevent == 4) {
                    $query = 'UPDATE #__jem_events SET published = 0 WHERE dates > 0 AND '
                           .' DATE_SUB(NOW(), INTERVAL '.(int)$jemsettings->minus.' DAY) > (IF (enddates IS NOT NULL, enddates, dates)) '
                           .' AND published = 1';
                    $db->SetQuery($query);
                    $db->execute();
                }

                // Cleanup registrations
                $query = 'DELETE FROM #__jem_register WHERE event NOT IN (SELECT id FROM #__jem_events)';
                $db->SetQuery($query);
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
        switch ($type) {
        case 'event':
        case 'events':
            $folder = 'events';
            $countquery_tmpl = ' SELECT id FROM #__jem_events WHERE datimage = ';
            $imagequery      = ' SELECT datimage AS image, COUNT(*) AS count FROM #__jem_events GROUP BY datimage';
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
            $db->setQuery($countquery_tmpl . $db->quote($filename));
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
     * @param  mixed $type one of 'event', 'venue', 'category', ... or false for all
     *
     * @return bool true on success, false on error
     * @access public
     */
    static public function delete_unused_attachment_files($type = false)
    {
        $jemsettings = JemHelper::config();
        $basepath    = JPATH_SITE.'/'.$jemsettings->attachments_path;
        $db          = Factory::getContainer()->get('DatabaseDriver');
        $res         = true;

        // Get list of all folders matching type (format is "$type$id")
        $folders = Folder::folders($basepath, ($type ? '^'.$type : '.'), false, false, array('.', '..'));

        // Get list of all used attachments of given type
        $fnames = array();
        foreach ($folders as $f) {
            $fnames[] = $db->Quote($f);
        }
        $query = ' SELECT object, file '
               . ' FROM #__jem_attachments ';
        if (!empty($fnames)) {
            $query .= ' WHERE object IN ('.implode(',', $fnames).')';
        }
        $db->setQuery($query);
        $files_used = $db->loadObjectList();
        $usedFiles = array();
        foreach ($files_used as $used) {
            $usedFiles[$used->object.'/'.$used->file] = true;
        }

        // Delete unused files and folders (ignore 'index.html')
        foreach ($folders as $folder) {
            $folderFiles = Folder::files($basepath.'/'.$folder, '.', false, false, array('index.html'), array());
            if (!empty($folderFiles)) {
                foreach ($folderFiles as $file) {
                    if (!array_key_exists($folder.'/'.$file, $usedFiles)) {
                        $res &= File::delete($basepath.'/'.$folder.'/'.$file);
                    }
                }
            }
            $remainingFiles = Folder::files($basepath.'/'.$folder, '.', false, true, array('index.html'), array());
            if (empty($remainingFiles)) {
                $res &= Folder::delete($basepath.'/'.$folder);
            }
        }

        return $res;
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
        $db = Factory::getContainer()->get('DatabaseDriver');

        // get event details for registration
        $query = ' SELECT maxplaces, waitinglist, reservedplaces FROM #__jem_events WHERE id = ' . $db->Quote($event);
        $db->setQuery($query);
        $event_places = $db->loadObject();

        // get attendees after deletion, and their status
        $query = 'SELECT r.id, r.waiting, r.places'
               . ' FROM #__jem_register AS r'
               . ' WHERE r.status = 1 AND r.event = '.$db->Quote($event)
               . ' ORDER BY r.uregdate ASC '
               ;
        $db->SetQuery($query);
        $res = $db->loadObjectList();

        $registered = 0;
        $waitingregs = array();
        foreach ((array) $res as $r)
        {
            if ($r->waiting) {
                $waitingregs[] = $r;
            } else {
                $registered+=$r->places;
            }
        }
        //Add the Reserved Places of the event
        $registered+=$event_places->reservedplaces;

        if (($registered < $event_places->maxplaces) && count($waitingregs))
        {
            $placesavailable = $event_places->maxplaces - $registered;
            // need to bump users to attending status
            foreach ($waitingregs as $waitreg)
            {
                if($waitreg->places <= $placesavailable)
                {
                    $query   = ' UPDATE #__jem_register SET waiting = 0 WHERE id = ' . $waitreg->id;
                    $db->setQuery($query);
                    if ($db->execute() === false)
                    {
                        Factory::getApplication()->enqueueMessage(
                            Text::_(
                                'COM_JEM_FAILED_BUMPING_USERS_FROM_WAITING_TO_CONFIRMED_LIST'
                            ) . ': ' . $db->getErrorMsg(),
                            'warning'
                        );
                    }
                    else
                    {
                        $placesavailable -= $waitreg->places;
                        PluginHelper::importPlugin('jem');
                        $dispatcher = JemFactory::getDispatcher();
                        $res        = $dispatcher->triggerEvent('onUserOnOffWaitinglist', array($waitreg->id));
                    }
                }
            }
        }

        return true;
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
        $user     = JemFactory::getUser();
        $userTz   = $user->getParam('timezone');
        $timeZone = Factory::getConfig()->get('offset');

        /* disabled for now
        if($userTz) {
            $timeZone = $userTz;
        }
        */
        return $timeZone;
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

    static public function icalAddEvent(&$calendartool, $event)
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_jem', JPATH_SITE . '/components/com_jem', null, true);
        $language->load('com_jem', JPATH_ADMINISTRATOR . '/components/com_jem', null, true);
        $language->load('com_jem', JPATH_SITE, null, false);

        $jemsettings   = JemHelper::config();
        $timezone_name = JemHelper::getTimeZoneName();
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
        $location = array();
        if (isset($event->venue) && trim((string) $event->venue) !== '') {
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

        if ($location === '' && $onlineMeetingUrl !== '') {
            $location = !empty($onlineMeetingPlatform['label'])
                ? $onlineMeetingPlatform['label']
                : Text::_('COM_JEM_ONLINE_MEETING');
        }

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
        $layoutSuffix = self::getLayoutStyleSuffix();
        $app      = Factory::getApplication();
        $document = $app->getDocument();
        $uri      = Uri::getInstance();
        $url      = $uri->root();
        $suffix   = $layoutSuffix ? '-' . $layoutSuffix : '';
        $variant  = $css . $suffix;
        $key      = str_replace('-', '_', $variant);
        $baseKey  = str_replace('-', '_', $css);

        $hasVariantSetting = $suffix
            && ($settings->get('css_' . $key . '_usecustom', null) !== null || $settings->get('css_' . $key . '_customfile', null) !== null);

        $configKey = $hasVariantSetting ? $key : $baseKey;

        if ($settings->get('css_' . $configKey . '_usecustom', '0')) {
            $file = (string) $settings->get('css_' . $configKey . '_customfile', '');
            $file = $file ? preg_replace('%^/([^/]*)%', '$1', $file) : '';

            if ($file && File::getExt($file) === 'css' && is_file(JPATH_SITE . '/media/com_jem/css/custom/' . $file)) {
                return $document->addStyleSheet($url . 'media/com_jem/css/custom/' . $file);
            }

            if (is_file(JPATH_SITE . '/media/com_jem/css/custom/' . $variant . '.css')) {
                return $document->addStyleSheet($url . 'media/com_jem/css/custom/' . $variant . '.css');
            }

            if (is_file(JPATH_SITE . '/media/com_jem/css/custom/' . $css . '.css')) {
                return $document->addStyleSheet($url . 'media/com_jem/css/custom/' . $css . '.css');
            }
        }

        if (is_file(JPATH_SITE . '/media/com_jem/css/' . $variant . '.css')) {
            return $document->addStyleSheet($url . 'media/com_jem/css/' . $variant . '.css');
        }

        return $document->addStyleSheet($url . 'media/com_jem/css/' . $css . '.css');
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
        self::loadUserCssFile('jem-user-front.css', 'com_jem.user.front');
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
     * @param   string  $file   The CSS file name.
     * @param   string  $asset  The WebAssetManager asset name.
     *
     * @return  void
     */
    protected static function loadUserCssFile($file, $asset)
    {
        $path = JPATH_SITE . '/media/com_jem/css/custom/' . $file;

        if (!is_file($path)) {
            return;
        }

        $app = Factory::getApplication();
        $wa  = $app->getDocument()->getWebAssetManager();

        if (method_exists($wa, 'assetExists') && $wa->assetExists('style', $asset)) {
            $wa->useStyle($asset);
            return;
        }

        $wa->registerAndUseStyle($asset, 'media/com_jem/css/custom/' . $file);
    }

    /**
     * Get the url to a css file for a module respecting layout style configured in JEM Settings.
     *
     * @param   string  $module  The name of the module
     * @param   string  $css     The name of the css file (in the root path). If null, the name of module is used (in the suffix directory).
     *
     * @since   2.3
     */
    public static function loadModuleStyleSheet($module, $css)
    {
        $app = Factory::getApplication();
        $wa = $app->getDocument()->getWebAssetManager();
        $templateName = $app->getTemplate();
        $filestyle = $css . '.css';

        //Search for template overrides
        if(file_exists(JPATH_BASE . '/templates/' . $templateName . '/css/' . $module . '/' . $filestyle)) {
            $wa->registerAndUseStyle($module . ($css? '.' . $css: ''), 'templates/' . $templateName . '/css/'. $module . '/' . $filestyle);
        }
        //Search for template overrides
        else if (file_exists(JPATH_BASE . '/templates/' . $templateName . '/html/' . $module . '/' . $filestyle)) {
            $wa->registerAndUseStyle($module . ($css? '.' . $css: ''), 'templates/' . $templateName . '/html/'. $module . '/' . $filestyle);
        }
        //Search in media folder
        else if (file_exists(JPATH_BASE . '/media/' . $module . '/css/' . $filestyle)) {
            $wa->registerAndUseStyle($module . ($css? '.' . $css: ''), 'media/' . $module . '/css/' . $filestyle);
        }
        //Search in the module
        else if (file_exists(JPATH_BASE . '/modules/' . $module . '/tmpl/' . $filestyle)) {
            $wa->registerAndUseStyle($module . ($css? '.' . $css: ''), 'modules/'. $module . '/tmpl/' . $filestyle);
        }
        //Error no css file found
        else {
            JemHelper::addLogEntry("Warning: The file " . $filestyle . " couldn't be found.", __METHOD__);
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
