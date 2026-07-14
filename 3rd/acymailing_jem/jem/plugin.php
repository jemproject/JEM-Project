<?php
/**
 * @package    JEM
 * @subpackage AcyMailing 10 integration
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use AcyMailing\Core\AcymPlugin;
use AcyMailing\Helpers\TabHelper;

/**
 * AcyMailing 10 dynamic-content add-on for JEM.
 */
class plgAcymJem extends AcymPlugin
{
    public function __construct()
    {
        parent::__construct();

        $this->cms = 'Joomla';
        $this->addonDefinition = [
            'name' => 'JEM Events',
            'description' => '- Insert JEM events in emails<br>- Insert upcoming JEM events automatically by category',
            'documentation' => 'https://www.joomlaeventmanager.net/',
            'category' => 'Events management',
            'level' => 'starter',
        ];
        $this->installed = acym_isExtensionActive('com_jem');
        // JEM category ID 1 is the technical tree root; show its children.
        $this->rootCategoryId = 1;

        $this->pluginDescription->name = 'JEM Events';
        $this->pluginDescription->title = 'Insert JEM events';
        $this->pluginDescription->category = 'Events management';
        $this->pluginDescription->description = $this->addonDefinition['description'];
        $this->pluginDescription->icon = ACYM_DYNAMICS_URL.basename(__DIR__).'/icon.png';

        if (!$this->installed) {
            $this->settings = ['not_installed' => '1'];

            return;
        }

        acym_loadLanguageFile('com_jem', JPATH_SITE);
        acym_loadLanguageFile('com_jem', JPATH_ADMINISTRATOR);

        $this->displayOptions = [
            'title' => ['ACYM_TITLE', true],
            'date' => ['ACYM_DATE', true],
            'venue' => ['Venue', true],
            'description' => ['ACYM_DESCRIPTION', false],
        ];
        $this->initCustomView();
        $this->settings = [
            'custom_view' => [
                'type' => 'custom_view',
                'tags' => array_merge($this->displayOptions, $this->replaceOptions, $this->elementOptions),
            ],
            'hidepast' => [
                'type' => 'switch',
                'label' => 'ACYM_HIDE_PAST_EVENTS',
                'value' => 1,
            ],
            'front' => [
                'type' => 'select',
                'label' => 'ACYM_FRONT_ACCESS',
                'value' => 'all',
                'data' => [
                    'all' => 'ACYM_ALL_ELEMENTS',
                    'author' => 'ACYM_ONLY_AUTHORS_ELEMENTS',
                    'hide' => 'ACYM_DONT_SHOW',
                ],
            ],
            'itemid' => [
                'type' => 'text',
                'label' => 'JEM menu item ID',
                'value' => '',
            ],
        ];
    }

    public function getPossibleIntegrations(): ?object
    {
        if (!acym_isAdmin() && $this->getParam('front', 'all') === 'hide') {
            return null;
        }

        return $this->pluginDescription;
    }

    public function getStandardStructure(string &$customView): void
    {
        $tag = new stdClass();
        $tag->id = 0;

        $format = new stdClass();
        $format->tag = $tag;
        $format->title = '{title}';
        $format->afterTitle = '{date}{venue}';
        $format->afterArticle = '{readmore}';
        $format->imagePath = '{image}';
        $format->description = '{description}';
        $format->link = '{link}';
        $customView = '<div class="acymailing_content jem-acym-event">'.$this->pluginHelper->getStandardDisplay($format).'</div>';
    }

    public function initReplaceOptionsCustomView(): void
    {
        $this->replaceOptions = [
            'link' => ['ACYM_LINK'],
            'image' => ['ACYM_IMAGE'],
            'readmore' => ['ACYM_READ_MORE'],
        ];
    }

    public function initElementOptionsCustomView(): void
    {
        $event = acym_loadObject('SELECT event.* FROM #__jem_events AS event ORDER BY event.id DESC');
        if (empty($event)) {
            return;
        }

        foreach ($event as $field => $value) {
            $this->elementOptions[$field] = [$field];
        }
    }

    public function insertionOptions(?object $defaultValues = null): void
    {
        $this->defaultValues = $defaultValues;
        $this->categories = acym_loadObjectList(
            'SELECT id, parent_id, catname AS title'
            .' FROM #__jem_categories'
            .' WHERE published = 1'
            .' ORDER BY parent_id, ordering, catname',
            'id'
        );

        $tabHelper = new TabHelper();
        $displayOptions = $this->getDisplayOptions();

        $identifier = $this->name;
        $tabHelper->startTab(
            acym_translation('ACYM_ONE_BY_ONE'),
            !empty($this->defaultValues->defaultPluginTab) && $identifier === $this->defaultValues->defaultPluginTab
        );
        $this->displaySelectionZone($this->getFilteringZone().$this->prepareListing());
        $this->pluginHelper->displayOptions($displayOptions, $identifier, 'individual', $this->defaultValues);
        $tabHelper->endTab();

        $identifier = 'auto'.$this->name;
        $tabHelper->startTab(
            acym_translation('ACYM_BY_CATEGORY'),
            !empty($this->defaultValues->defaultPluginTab) && $identifier === $this->defaultValues->defaultPluginTab
        );

        $categoryOptions = [
            [
                'title' => 'ACYM_ORDER_BY',
                'type' => 'select',
                'name' => 'order',
                'options' => [
                    'dates' => 'ACYM_DATE',
                    'title' => 'ACYM_TITLE',
                    'id' => 'ACYM_ID',
                    'rand' => 'ACYM_RANDOM',
                ],
                'default' => 'dates',
                'defaultdir' => 'asc',
            ],
            [
                'title' => 'Open-date events',
                'type' => 'select',
                'name' => 'opendates',
                'options' => [
                    'exclude' => 'Exclude events without a date',
                    'include' => 'Include events without a date',
                    'only' => 'Only events without a date',
                ],
                'default' => 'exclude',
            ],
            [
                'title' => 'Featured events only',
                'type' => 'boolean',
                'name' => 'featured',
                'default' => false,
            ],
        ];
        $this->autoContentOptions($categoryOptions, 'event');
        $this->autoCampaignOptions($categoryOptions);

        $this->displaySelectionZone($this->getCategoryListing());
        $this->pluginHelper->displayOptions(
            array_merge($displayOptions, $categoryOptions),
            $identifier,
            'grouped',
            $this->defaultValues
        );
        $tabHelper->endTab();
        $tabHelper->display('plugin');
    }

    public function prepareListing(): string
    {
        $this->querySelect = 'SELECT DISTINCT event.id, event.title, event.dates, venue.venue ';
        $this->query = 'FROM #__jem_events AS event '
            .'LEFT JOIN #__jem_venues AS venue ON venue.id = event.locid ';
        $this->filters = ['event.published = 1'];
        $this->searchFields = ['event.id', 'event.title', 'event.alias', 'event.introtext', 'venue.venue'];
        $this->pageInfo->order = 'event.dates';
        $this->elementIdTable = 'event';
        $this->elementIdColumn = 'id';

        if ($this->getParam('hidepast', '1') === '1') {
            $today = acym_escapeDB(date('Y-m-d'));
            $this->filters[] = '(event.dates IS NULL OR COALESCE(event.enddates, event.dates) >= '.$today.')';
        }

        parent::prepareListing();

        if (!empty($this->pageInfo->filter_cat)) {
            $this->query .= 'INNER JOIN #__jem_cats_event_relations AS relation ON relation.itemid = event.id ';
            $this->filters[] = 'relation.catid = '.intval($this->pageInfo->filter_cat);
        }

        $listingOptions = [
            'header' => [
                'title' => ['label' => 'ACYM_TITLE', 'size' => '5'],
                'dates' => ['label' => 'ACYM_DATE', 'size' => '3', 'type' => 'date'],
                'venue' => ['label' => 'Venue', 'size' => '3'],
                'id' => ['label' => 'ACYM_ID', 'size' => '1', 'class' => 'text-center'],
            ],
            'id' => 'id',
            'rows' => $this->getElements(),
        ];

        return $this->getElementsListing($listingOptions);
    }

    public function replaceContent(object &$email): void
    {
        $this->replaceMultiple($email);
        $this->replaceOne($email);
    }

    public function generateByCategory(object &$email): object
    {
        $tags = $this->pluginHelper->extractTags($email, 'auto'.$this->name);
        $this->tags = [];

        if (empty($tags)) {
            return $this->generateCampaignResult;
        }

        foreach ($tags as $oneTag => $parameter) {
            if (isset($this->tags[$oneTag])) {
                continue;
            }

            $query = 'SELECT DISTINCT event.id FROM #__jem_events AS event';
            $where = ['event.published = 1'];
            $selectedCategories = $this->getSelectedArea($parameter);

            if (!empty($selectedCategories)) {
                $query .= ' INNER JOIN #__jem_cats_event_relations AS relation ON relation.itemid = event.id';
                $where[] = 'relation.catid IN ('.implode(',', array_map('intval', $selectedCategories)).')';
            }

            $nowSql = date('Y-m-d H:i:s');
            $where[] = '(event.publish_up IS NULL OR event.publish_up <= '.acym_escapeDB($nowSql).')';
            $where[] = '(event.publish_down IS NULL OR event.publish_down >= '.acym_escapeDB($nowSql).')';

            $openDates = empty($parameter->opendates) ? 'exclude' : (string) $parameter->opendates;
            $fromDate = empty($parameter->from)
                ? date('Y-m-d')
                : acym_date(acym_replaceDate($parameter->from), 'Y-m-d');
            $dateFilter = !empty($parameter->addcurrent)
                ? 'COALESCE(event.enddates, event.dates) >= '.acym_escapeDB($fromDate)
                : 'event.dates >= '.acym_escapeDB($fromDate);

            if ($openDates === 'only') {
                $where[] = 'event.dates IS NULL';
            } elseif ($openDates === 'include') {
                $where[] = '(event.dates IS NULL OR '.$dateFilter.')';
            } else {
                $where[] = 'event.dates IS NOT NULL';
                $where[] = $dateFilter;
            }

            if ($openDates !== 'only' && !empty($parameter->to)) {
                $toDate = acym_date(acym_replaceDate($parameter->to), 'Y-m-d');
                $toFilter = 'event.dates <= '.acym_escapeDB($toDate);
                $where[] = $openDates === 'include' ? '(event.dates IS NULL OR '.$toFilter.')' : $toFilter;
            }

            if (!empty($parameter->featured)) {
                $where[] = 'event.featured = 1';
            }

            if (!empty($parameter->onlynew)) {
                $lastGenerated = $this->getLastGenerated((int) $email->id);
                if (!empty($lastGenerated)) {
                    $where[] = 'event.created > '.acym_escapeDB(acym_date($lastGenerated, 'Y-m-d H:i:s', false));
                }
            }

            $query .= ' WHERE ('.implode(') AND (', $where).')';
            $this->tags[$oneTag] = $this->finalizeCategoryFormat($query, $parameter, 'event');
        }

        return $this->generateCampaignResult;
    }

    public function replaceIndividualContent(object $tag): string
    {
        acym_loadLanguageFile('com_jem', JPATH_SITE, $this->emailLanguage);

        $query = 'SELECT event.*, venue.venue, venue.city, venue.state, venue.country'
            .' FROM #__jem_events AS event'
            .' LEFT JOIN #__jem_venues AS venue ON venue.id = event.locid'
            .' WHERE event.published = 1 AND event.id = '.intval($tag->id);
        $event = $this->initIndividualContent($tag, $query);

        if (empty($event)) {
            return '';
        }

        $display = $tag->display;
        $itemId = !empty($tag->itemid) ? intval($tag->itemid) : intval($this->getParam('itemid', 0));
        $link = $this->finalizeLink($this->getEventLink($event, $itemId), $tag);
        $title = in_array('title', $display, true) ? (string) $event->title : '';
        $date = in_array('date', $display, true) ? $this->formatEventDate($event) : '';
        $venue = in_array('venue', $display, true) ? $this->formatVenue($event) : '';

        $description = '';
        if (in_array('description', $display, true) && !empty($event->introtext)) {
            $description = (string) $event->introtext;
        }

        $afterTitle = '';
        if ($date !== '') {
            $afterTitle .= '<p class="jem-acym-date"><strong>'.acym_escape($date).'</strong></p>';
        }
        if ($venue !== '') {
            $afterTitle .= '<p class="jem-acym-venue">'.acym_escape($venue).'</p>';
        }

        $readMore = '';
        if (!empty($tag->readmore)) {
            $readMore = '<a class="acymailing_readmore_link" target="_blank" href="'.acym_escape($link).'">'
                .'<span class="acymailing_readmore">'.acym_escape(acym_translation('ACYM_READ_MORE')).'</span></a>';
        }

        $image = '';
        if (in_array('image', $display, true)) {
            $eventImage = !empty($event->fullimage) ? $event->fullimage : $event->datimage;
            $image = $this->getEventImagePath((string) $eventImage);
        }

        $format = new stdClass();
        $format->tag = $tag;
        $format->title = $title;
        $format->afterTitle = $afterTitle;
        $format->afterArticle = $readMore;
        $format->imagePath = $image;
        $format->altImage = $title;
        $format->description = $description;
        $format->link = $link;

        $result = '<div class="acymailing_content jem-acym-event">'.$this->pluginHelper->getStandardDisplay($format).'</div>';
        $variables = $this->getCustomLayoutVars($event);
        $variables['{title}'] = (string) $event->title;
        $variables['{date}'] = $date === '' ? '' : '<p class="jem-acym-date"><strong>'.acym_escape($date).'</strong></p>';
        $variables['{venue}'] = $venue === '' ? '' : '<p class="jem-acym-venue">'.acym_escape($venue).'</p>';
        $variables['{description}'] = $description;
        $variables['{image}'] = $image;
        $variables['{link}'] = $link;
        $variables['{readmore}'] = $readMore;

        return $this->finalizeElementFormat($result, $tag, $variables);
    }

    private function getDisplayOptions(): array
    {
        return [
            [
                'title' => 'ACYM_DISPLAY',
                'type' => 'checkbox',
                'name' => 'display',
                'options' => [
                    'title' => ['ACYM_TITLE', true],
                    'date' => ['ACYM_DATE', true],
                    'venue' => ['Venue', true],
                    'image' => ['ACYM_IMAGE', true],
                    'description' => ['ACYM_DESCRIPTION', false],
                ],
            ],
            ['title' => 'ACYM_CLICKABLE_TITLE', 'type' => 'boolean', 'name' => 'clickable', 'default' => true],
            ['title' => 'ACYM_CLICKABLE_IMAGE', 'type' => 'boolean', 'name' => 'clickableimg', 'default' => true],
            ['title' => 'ACYM_READ_MORE', 'type' => 'boolean', 'name' => 'readmore', 'default' => true],
            [
                'title' => 'ACYM_TRUNCATE',
                'type' => 'intextfield',
                'isNumber' => 1,
                'name' => 'wrap',
                'text' => 'ACYM_TRUNCATE_AFTER',
                'default' => 0,
            ],
            ['title' => 'ACYM_DISPLAY_PICTURES', 'type' => 'pictures', 'name' => 'pictures'],
            [
                'title' => 'JEM menu item ID',
                'type' => 'intextfield',
                'isNumber' => 1,
                'name' => 'itemid',
                'text' => 'Itemid',
                'default' => intval($this->getParam('itemid', 0)),
            ],
        ];
    }

    private function getEventLink(object $event, int $itemId): string
    {
        $slug = intval($event->id).(empty($event->alias) ? '' : ':'.$event->alias);
        if ($itemId > 0) {
            return 'index.php?option=com_jem&view=event&id='.$slug.'&Itemid='.$itemId;
        }

        $routeHelper = JPATH_SITE.'/components/com_jem/helpers/route.php';
        if (is_file($routeHelper)) {
            require_once $routeHelper;
        }

        if (class_exists('JEMHelperRoute')) {
            return JEMHelperRoute::getEventRoute($slug);
        }

        return 'index.php?option=com_jem&view=event&id='.$slug;
    }

    private function formatEventDate(object $event): string
    {
        $outputHelper = JPATH_SITE.'/components/com_jem/classes/output.class.php';
        if (is_file($outputHelper)) {
            require_once $outputHelper;
        }

        if (class_exists('JemOutput')) {
            $formattedDate = (string) JemOutput::formatLongDateTime(
                $event->dates,
                $event->times,
                $event->enddates,
                $event->endtimes
            );

            // JemOutput returns presentation markup, while the AcyMailing
            // template escapes this value as text before inserting it.
            return trim(strip_tags($formattedDate));
        }

        return trim(trim((string) $event->dates).' '.trim((string) $event->times));
    }

    private function formatVenue(object $event): string
    {
        if (empty($event->venue)) {
            return '';
        }

        return (string) $event->venue.(empty($event->city) ? '' : ', '.$event->city);
    }

    private function getEventImagePath(string $image): string
    {
        $image = ltrim(trim($image), '/\\');
        if ($image === '') {
            return '';
        }

        if (strpos($image, '/') === false && strpos($image, '\\') === false) {
            return 'images/jem/events/'.$image;
        }

        return str_replace('\\', '/', $image);
    }
}
