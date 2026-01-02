<?php
/**
 * JemListEvent is a Plugin to display events in articles.
 * For more information visit joomlaeventmanager.net
 *
 * @package    JEM
 * @subpackage JEM Listevents Plugin
 * @author     JEM Team <info@joomlaeventmanager.net>, Luis Raposo
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;

BaseDatabaseModel::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');
require_once JPATH_SITE.'/components/com_jem/helpers/helper.php';
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');

/**
 * JEM List Events Plugin
 *
 * @since  2.2.2
 */
class PlgContentJemlistevents extends CMSPlugin
{
    /** All options with their default values */
    protected static array $optionDefaults = [
        'type'              => 'unfinished',
        'show_featured'     => 'on',
        'title'             => 'on',
        'cut_title'         => 40,
        'show_date'         => 'on',
        'date_format'       => '',
        'show_time'         => 'on',
        'time_format'       => '',
        'show_enddatetime'  => 'off', // for backward compatibility
        'catids'            => '',
        'show_category'     => 'off',
        'venueids'          => '',
        'show_venue'        => 'off',
        'max_events'        => '5',
        'no_events_msg'     => '',
    ];

    /** Options we have to convert from numbers to 'on'/'off' */
    protected static array $optionConvert = ['show_time', 'show_enddatetime'];

    /** All text tokens with their corresponding option */
    protected static array $optionTokens = [
        'type'        => 'type',
        'featured'    => 'show_featured',
        'title'       => 'title',
        'cuttitle'    => 'cut_title',
        'date'        => 'show_date',
        'time'        => 'show_time',
        'enddatetime' => 'show_enddatetime',
        'catids'      => 'catids',
        'category'    => 'show_category',
        'venueids'    => 'venueids',
        'venue'       => 'show_venue',
        'max'         => 'max_events',
        'noeventsmsg' => 'no_events_msg',
    ];

    /** All text tokens with their corresponding option */
    protected static array $tokenValues = [
        'type'        => ['today', 'unfinished', 'upcoming', 'ongoing', 'archived', 'newest', 'open', 'all'],
        'featured'    => ['off', 'on', 'only'],
        'title'       => ['on', 'link', 'off'],
        'date'        => ['on', 'link', 'off'],
        'time'        => ['on', 'off'],
        'enddatetime' => ['on', 'off'],
        'category'    => ['on', 'link', 'off'],
        'venue'       => ['on', 'link', 'off'],
    ];

    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param array  $config  An array that holds the plugin configuration
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
        $this->loadLanguage('com_jem', JPATH_ADMINISTRATOR.'/components/com_jem');
    }

    /**
     * Plugin that outputs a list of events from JEM
     *
     * @param   string   $context  The context of the content being passed to the plugin.
     * @param   mixed    &$row     An object with a "text" property
     * @param   mixed    $params   Additional parameters.
     * @param   integer  $page     Optional page number. Unused. Defaults to zero.
     *
     * @return  boolean  True on success.
     */
    public function onContentPrepare(string $context, &$row, &$params, $page = 0): bool
    {
        $page = (int)$page;
        // Don't run this plugin when the content is being indexed
        if ($context === 'com_finder.indexer') {
            return true;
        }

        // Simple performance check to determine whether the bot should process further
        if (empty($row->text) || mb_strpos($row->text, 'jemlistevents') === false) {
            return true;
        }

        $this->loadCSS();

        // Expression to search for
        $regex = '/{jemlistevents\s*(.*?)}/i';

        // Check whether the plugin has been unpublished
        if (!$this->params->get('enabled', 1)) {
            $row->text = preg_replace($regex, '', $row->text);
            return true;
        }

        // Find all instances of plugin and put in $matches
        preg_match_all($regex, $row->text, $matches);

        // Plugin only processes if there are any instances of the plugin in the text
        if ($matches) {
            $this->_process($row, $matches, $regex);
        }

        return true;
    }

    /**
     * Load the CSS file for the plugin.
     */
    private function loadCSS(): void
    {
        $templateName = Factory::getApplication()->getTemplate();
        $document = Factory::getApplication()->getDocument();
        $wa = $document->getWebAssetManager();
        $templatePath = JPATH_BASE . '/templates/' . $templateName . '/css/jemlistevents.css';

        if (file_exists($templatePath)) {
            $wa->registerAndUseStyle('jemlistevents', 'templates/' . $templateName . '/css/jemlistevents.css');
        } else {
            $wa->registerAndUseStyle('jemlistevents', 'media/plg_content_jemlistevents/css/jemlistevents.css');
        }
    }

    /**
     * The processing function
     */
    protected function _process(&$content, array &$matches, string $regex): void
    {
        // Get plugin parameters
        $defaults = $this->getDefaultOptions();

        for ($i = 0; $i < count($matches[0]); ++$i) {
            $match = $matches[1][$i];
            $params = $defaults;
            $options = explode(';', $match);

            foreach ($options as $option) {
                $option = str_replace(['[', ']'], '', $option);
                $pair = explode("=", $option, 2);
                if (empty($pair[0]) || empty($pair[1])) {
                    continue;
                }
                $token = strtolower(trim($pair[0]));
                if (preg_match('/[ \'"]*(.*)[ \'"]*/', $pair[1], $m)) {
                    $value = $m[1];
                    if ($this->isValidOption($token, $value)) {
                        $params[self::$optionTokens[$token]] = $value;
                    }
                }
            }

            $eventlist = $this->_load($params);
            $display = $this->_display($eventlist, $params, $i);
            $content->text = str_replace($matches[0][$i], $display, $content->text);
        }
    }

    /**
     * Get the default options with conversions applied.
     */
    private function getDefaultOptions(): array
    {
        $defaults = [];
        foreach (self::$optionDefaults as $k => $v) {
            $defaults[$k] = $this->params->def($k, $v);
            if (in_array($k, self::$optionConvert) && is_numeric($defaults[$k])) {
                $defaults[$k] = ($defaults[$k] == '0') ? 'off' : 'on';
            }
        }

        // Special handling for show_featured
        if (isset($defaults['show_featured'])) {
            $defaults['show_featured'] = $this->convertShowFeatured($defaults['show_featured']);
        }

        return $defaults;
    }

    /**
     * Convert the show_featured parameter.
     */
    private function convertShowFeatured(string $value): string
    {
        if ($value === '0' || $value === 0) {
            return 'off';
        } elseif ($value === '2' || $value === 2) {
            return 'only';
        } else {
            return 'on'; // Default for 1 or any other value
        }
    }

    /**
     * Check if the option is valid.
     */
    private function isValidOption(string $token, string $value): bool
    {
        if (array_key_exists($token, self::$optionTokens)) {
            if (!array_key_exists($token, self::$tokenValues) || in_array($value, self::$tokenValues[$token])) {
                return true;
            }
        }
        return false;
    }

    /**
     * The function that takes care of loading the events.
     */
    protected function _load(array $parameters): array
    {
        $model = BaseDatabaseModel::getInstance('Eventslist', 'JemModel', ['ignore_request' => true]);

        $this->setModelState($model, $parameters);

        // Retrieve the available Events.
        return $model->getItems();
    }

    /**
     * Set the model state based on parameters.
     */
    private function setModelState(BaseDatabaseModel $model, array $parameters): void
    {
        if (isset($parameters['max_events'])) {
            $max = $parameters['max_events'];
            $model->setState('list.limit', ($max > 0) ? $max : 100);
        }

        $this->filterCategories($model, $parameters);
        $this->filterVenues($model, $parameters);
        $this->filterFeatured($model, $parameters);

        $type = $parameters['type'] ?? 'unfinished';
        $db = Factory::getDbo();
        $timestamp = time();

        try {
            $this->applyTypeFilter($model, $type, $timestamp);
        } catch (\Exception $e) {
            $this->handleError($e);
            $model->setState('filter.published', 1);
            $model->setState('filter.orderby', ['a.dates ASC', 'a.times ASC']);
        }

        $model->setState('filter.groupby', ['a.id']);
    }

    /**
     * Filter categories in the model.
     */
    private function filterCategories(BaseDatabaseModel $model, array $parameters): void
    {
        if (!empty($parameters['catids'])) {
            $included_cats = array_map('intval', explode(",", $parameters['catids']));
            $model->setState('filter.category_id', $included_cats);
            $model->setState('filter.category_id.include', 1);
        }
    }

    /**
     * Filter venues in the model.
     */
    private function filterVenues(BaseDatabaseModel $model, array $parameters): void
    {
        if (!empty($parameters['venueids'])) {
            $included_venues = array_map('intval', explode(",", $parameters['venueids']));
            $model->setState('filter.venue_id', $included_venues);
            $model->setState('filter.venue_id.include', 1);
        }
    }

    /**
     * Filter featured events in the model.
     */
    private function filterFeatured(BaseDatabaseModel $model, array $parameters): void
    {
        if (isset($parameters['show_featured'])) {
            switch ($parameters['show_featured']) {
                case 'off':
                    $model->setState('filter.featured', 0);
                    break;
                case 'only':
                    $model->setState('filter.featured', 1);
                    break;
            }
        }
    }

    /**
     * Apply type filter to the model.
     */
    private function applyTypeFilter(BaseDatabaseModel $model, string $type, int $timestamp): void
    {
        $to_date = date('Y-m-d H:i:s', $timestamp);
        $full_end_datetime = 'CONCAT(COALESCE(a.enddates, a.dates), " ", COALESCE(a.endtimes, "23:59:59"))';
        $full_start_datetime = 'CONCAT(a.dates, " ", COALESCE(a.times, "00:00:00"))';

        switch ($type) {
            case 'today':
                $this->applyTodayFilter($model, $to_date);
                break;
            case 'unfinished':
            default:
                $this->applyUnfinishedFilter($model, $to_date, $full_end_datetime);
                break;
            case 'upcoming':
                $this->applyUpcomingFilter($model, $to_date, $full_start_datetime);
                break;
            case 'ongoing':
                $this->applyOngoingFilter($model, $to_date, $full_start_datetime, $full_end_datetime);
                break;
            case 'archived':
                $this->applyArchivedFilter($model);
                break;
            case 'newest':
                $this->applyNewestFilter($model);
                break;
            case 'open':
                $this->applyOpenFilter($model);
                break;
            case 'all':
                $this->applyAllFilter($model);
                break;
        }
    }

    /**
     * Apply today filter to the model.
     */
    private function applyTodayFilter(BaseDatabaseModel $model, string $to_date): void
    {
        $model->setState('filter.published', 1);
        $model->setState('filter.orderby', ['a.dates ASC', 'a.times ASC']);
        $where = ' DATEDIFF (a.dates, "' . $to_date . '") = 0';
        $model->setState('filter.calendar_to', $where);
    }

    /**
     * Apply unfinished filter to the model.
     */
    private function applyUnfinishedFilter(BaseDatabaseModel $model, string $to_date, string $full_end_datetime): void
    {
        $model->setState('filter.published', 1);
        $model->setState('filter.orderby', ['a.dates ASC', 'a.times ASC']);
        $where = '(' . $full_end_datetime . ' > "' . $to_date . '")';
        $model->setState('filter.calendar_to', $where);
    }

    /**
     * Apply upcoming filter to the model.
     */
    private function applyUpcomingFilter(BaseDatabaseModel $model, string $to_date, string $full_start_datetime): void
    {
        $model->setState('filter.published', 1);
        $model->setState('filter.orderby', ['a.dates ASC', 'a.times ASC']);
        $where = '(' . $full_start_datetime . ' > "' . $to_date . '")';
        $model->setState('filter.calendar_to', $where);
    }

    /**
     * Apply ongoing filter to the model.
     */
    private function applyOngoingFilter(BaseDatabaseModel $model, string $to_date, string $full_start_datetime, string $full_end_datetime): void
    {
        $model->setState('filter.published', 1);
        $model->setState('filter.orderby', ['a.dates ASC', 'a.times ASC']);
        $where = '(' . $full_start_datetime . ' <= "' . $to_date . '" AND ' . $full_end_datetime . ' >= "' . $to_date . '")';
        $model->setState('filter.calendar_to', $where);
    }

    /**
     * Apply archived filter to the model.
     */
    private function applyArchivedFilter(BaseDatabaseModel $model): void
    {
        $model->setState('filter.published', 2);
        $model->setState('filter.orderby', ['a.dates DESC', 'a.times DESC']);
    }

    /**
     * Apply newest filter to the model.
     */
    private function applyNewestFilter(BaseDatabaseModel $model): void
    {
        $model->setState('filter.published', 1);
        $model->setState('filter.orderby', ['a.id DESC']);
    }

    /**
     * Apply open filter to the model.
     */
    private function applyOpenFilter(BaseDatabaseModel $model): void
    {
        $model->setState('filter.published', 1);
        $model->setState('filter.orderby', ['a.id DESC']);
        $model->setState('filter.opendates', 2);
    }

    /**
     * Apply all filter to the model.
     */
    private function applyAllFilter(BaseDatabaseModel $model): void
    {
        $model->setState('filter.published', [1, 2]);
        $model->setState('filter.orderby', ['a.dates ASC', 'a.times ASC']);
        $model->setState('filter.opendates', 1);
    }

    /**
     * Handle errors by logging and displaying a user-friendly message.
     */
    private function handleError(\Exception $e): void
    {
        JLog::add(
            sprintf('Error in JemListEvents plugin: %s (File: %s, Line: %s)',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ),
            JLog::ERROR,
            'plg_content_jemlistevents'
        );

        Factory::getApplication()->enqueueMessage(
            Text::_('PLG_CONTENT_JEMLISTEVENTS_ERROR_LOADING_EVENTS'),
            'error'
        );
    }

    /**
     * The function that takes care of displaying the events.
     */
    protected function _display(array $rows, array $parameters, int $listevents_id): string
    {
        include_once JPATH_BASE."/components/com_jem/helpers/route.php";

        $html_list = $this->generateTableStart($listevents_id);

        // Check if there are events
        if (empty($rows)) {
            $rows = []; // to skip foreach without warning
        }

        $n_event = 0;
        $cols_count = $this->calculateColumnsCount($parameters);

        if (count($rows) > 0) {
            $html_list .= $this->generateTableHeader($parameters);
        }

        foreach ($rows as $event) {
            $html_list .= $this->generateTableRow($event, $parameters, $n_event, $listevents_id);
            $n_event++;
            if ((int)$parameters['max_events'] && ($n_event >= (int)$parameters['max_events'])) {
                break;
            }
        }

        if ($n_event === 0) {
            $html_list .= $this->generateNoEventsMessage($parameters, $cols_count);
        }

        $html_list .= '</table></div>';

        return $html_list;
    }

    /**
     * Generate the start of the table HTML.
     */
    private function generateTableStart(int $listevents_id): string
    {
        return '<div class="jemlistevents" id="jemlistevents-' . $listevents_id . '"><table class="table table-hover table-striped">';
    }

    /**
     * Generate the table header HTML.
     */
    private function generateTableHeader(array $parameters): string
    {
        $html = '<thead><tr>';
        $columns = [
            'title' => 'COM_JEM_TITLE',
            'show_date' => 'COM_JEM_DATE',
            'show_venue' => 'COM_JEM_VENUE',
            'show_category' => 'COM_JEM_CATEGORY'
        ];

        foreach ($columns as $param => $translation) {
            if ($parameters[$param] !== 'off') {
                $html .= '<th>' . Text::_($translation) . '</th>';
            }
        }

        if ($parameters['show_time'] !== 'off' &&
            (($parameters['show_date'] === 'off' && $parameters['show_enddatetime'] !== 'off') ||
             ($parameters['show_enddatetime'] === 'off'))) {
            $html .= '<th>' . Text::_('COM_JEM_STARTTIME_SHORT') . '</th>';
        }

        $html .= '</tr></thead>';
        return $html;
    }

    /**
     * Calculate the number of active columns for colspan in "no events" message.
     */
    private function calculateColumnsCount(array $parameters): int
    {
        $cols_count = 0;
        if ($parameters['title'] !== 'off') $cols_count++;
        if ($parameters['show_date'] !== 'off') $cols_count++;
        if ($parameters['show_venue'] !== 'off') $cols_count++;
        if ($parameters['show_category'] !== 'off') $cols_count++;
        if ($parameters['show_time'] !== 'off' &&
            (($parameters['show_date'] === 'off' && $parameters['show_enddatetime'] !== 'off') ||
             ($parameters['show_enddatetime'] === 'off'))) {
            $cols_count++;
        }
        return max(1, $cols_count);
    }

    /**
     * Generate a table row HTML.
     */
    private function generateTableRow(object $event, array $parameters, int $n_event, int $listevents_id): string
    {
        $linkdetails = Route::_(JemHelperRoute::getEventRoute($event->slug));
        $linkdate = Route::_(JemHelperRoute::getRoute($event->dates !== null ? str_replace('-', '', $event->dates) : '', 'day'));
        $linkvenue = Route::_(JemHelperRoute::getVenueRoute($event->venueslug));

        $featured_class = isset($event->featured) && $event->featured ? ' jemlistevent-featured' : '';
        $html = '<tr class="listevent event' . ($n_event + 1) . $featured_class . '">';

        if ($parameters['title'] !== 'off') {
            $html .= $this->generateTitleCell($event, $parameters, $linkdetails);
        }

        $html .= $this->generateDateCells($event, $parameters, $linkdate);
        $html .= $this->generateVenueCell($event, $parameters, $linkvenue);
        $html .= $this->generateCategoryCell($event, $parameters);

        $html .= '</tr>';
        return $html;
    }

    /**
     * Generate the title cell HTML.
     */
    private function generateTitleCell(object $event, array $parameters, string $linkdetails): string
    {
        $html = '<td class="eventtitle" data-label="' . Text::_('COM_JEM_TITLE') . '">';
        if ($parameters['title'] === 'link') {
            $html .= '<a href="' . $linkdetails . '">';
        }
        $fulltitle = htmlspecialchars($event->title, ENT_COMPAT, 'UTF-8');
        $title = mb_strlen($fulltitle) > $parameters['cut_title'] ? mb_substr($fulltitle, 0, $parameters['cut_title']) . '&nbsp;…' : $fulltitle;
        $html .= $title;
        if ($parameters['title'] === 'link') {
            $html .= '</a>';
        }
        $html .= '</td>';
        return $html;
    }

    /**
     * Generate the date cells HTML.
     */
    private function generateDateCells(object $event, array $parameters, string $linkdate): string
    {
        $html = '';
        if (($parameters['show_enddatetime'] === 'off') || ($parameters['show_date'] === 'off')) {
            if ($parameters['show_date'] !== 'off') {
                $html .= '<td class="eventdate" data-label="' . Text::_('COM_JEM_DATE') . '">';
                if ($event->dates) {
                    if ($parameters['show_date'] === 'link') {
                        $html .= '<a href="' . $linkdate . '">';
                    }
                    $html .= JemOutput::formatdate($event->dates, $parameters['date_format']);
                    if ($parameters['show_date'] === 'link') {
                        $html .= '</a>';
                    }
                }
                $html .= '</td>';
            }

            if ($parameters['show_time'] !== 'off') {
                $html .= '<td class="eventtime" data-label="' . Text::_('COM_JEM_STARTTIME') . '">';
                if ($event->times) {
                    $html .= JemOutput::formattime($event->times, $parameters['time_format']);
                }
                if ($event->endtimes && ($parameters['show_enddatetime'] !== 'off')) {
                    $html .= ' - ' . JemOutput::formattime($event->endtimes, $parameters['time_format']);
                }
                $html .= '</td>';
            }
        } else {
            $params = [
                'dateStart' => $event->dates,
                'timeStart' => $event->times,
                'dateEnd' => $event->enddates,
                'timeEnd' => $event->endtimes,
                'dateFormat' => $parameters['date_format'],
                'timeFormat' => $parameters['time_format'],
                'showTime' => $parameters['show_time'] !== 'off',
                'showDayLink' => $parameters['show_date'] === 'link',
            ];

            $html .= '<td class="eventdatetime" data-label="' . Text::_('COM_JEM_STARTTIME_SHORT') . '">';
            $html .= JemOutput::formatDateTime($params);
            $html .= '</td>';
        }
        return $html;
    }

    /**
     * Generate the venue cell HTML.
     */
    private function generateVenueCell(object $event, array $parameters, string $linkvenue): string
    {
        $html = '';
        if ($parameters['show_venue'] !== 'off') {
            $html .= '<td class="eventvenue" data-label="' . Text::_('COM_JEM_VENUE') . '">';
            if ($event->venue) {
                if ($parameters['show_venue'] === 'link') {
                    $html .= '<a href="' . $linkvenue . '">';
                }
                $html .= $event->venue;
                if ($parameters['show_venue'] === 'link') {
                    $html .= '</a>';
                }
            }
            $html .= '</td>';
        }
        return $html;
    }

    /**
     * Generate the category cell HTML.
     */
    private function generateCategoryCell(object $event, array $parameters): string
    {
        $html = '';
        if ($parameters['show_category'] !== 'off') {
            $catlink = $parameters['show_category'] === 'link';
            $html .= '<td class="eventcategory" data-label="' . Text::_('COM_JEM_CATEGORY') . '">';
            if ($event->categories) {
                $html .= implode(", ", JemOutput::getCategoryList($event->categories, $catlink));
            }
            $html .= '</td>';
        }
        return $html;
    }

    /**
     * Generate the "no events" message HTML.
     */
    private function generateNoEventsMessage(array $parameters, int $cols_count): string
    {
        return '<tr><td colspan="' . $cols_count . '" class="no-events-message">' . $parameters['no_events_msg'] . '</td></tr>';
    }
}
