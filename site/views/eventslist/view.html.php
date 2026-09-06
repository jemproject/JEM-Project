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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

require_once JPATH_SITE . '/components/com_jem/classes/eventfilterconfig.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';

/**
 * Eventslist-View
 */
class JemViewEventslist extends JemView
{
    public function __construct($config = [])
    {
        parent::__construct($config);

        // additional path for common templates + corresponding override path
        $this->addCommonTemplatePath();
    }

    /**
     * Creates the Simple List View
     */
    public function display($tpl = null)
    {
        // Initialize variables
        $app         = Factory::getApplication();
        $jemsettings = JemHelper::config();
        $settings    = JemHelper::globalattribs();
        $menu        = $app->getMenu();
        $menuactive  = $menu->getActive();
        $document    = $app->getDocument();
        $params      = $app->getParams();
        $uri         = Uri::getInstance();
        $jinput      = $app->input;
        $task        = $jinput->getCmd('task', '');
        $print       = $jinput->getBool('print', false);
        $pathway     = $app->getPathWay();
        $user        = JemFactory::getUser();
        $itemid      = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);
        $model = $this->getModel();
        $model->setState('Itemid', $menuactive->id);

        if (method_exists($document, 'getWebAssetManager')) {
            $wa = $document->getWebAssetManager();

            if (!$wa->assetExists('script', 'com_jem.monthpicker')) {
                $wa->registerScript(
                'com_jem.monthpicker',
                'media/com_jem/js/monthpicker-fallback.js',
                    [],
                    ['defer' => true],
                );
            }

            $wa->useScript('com_jem.monthpicker');
        }

        if (method_exists($document, 'getWebAssetManager')) {
            $wa = $document->getWebAssetManager();

            if (!$wa->assetExists('script', 'com_jem.monthpicker')) {
                $wa->registerScript(
                'com_jem.monthpicker',
                'media/com_jem/js/monthpicker-fallback.js',
                    [],
                    ['defer' => true],
                );
            }

            $wa->useScript('com_jem.monthpicker');
        }

        // Load css
        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        if ($print) {
            JemHelper::loadCss('print');
            $document->setMetaData('robots', 'noindex, nofollow');
        }

        //Text filter
        $filter_type = $app->getUserStateFromRequest(
            'com_jem.eventslist.' . $itemid . '.filter_type',
            'filter_type',
            0,
            'int',
        );
        $search = $app->getUserStateFromRequest(
            'com_jem.eventslist.' . $itemid . '.filter_search',
            'filter_search',
            '',
            'string',
        );
        $search_month = $app->getUserStateFromRequest(
            'com_jem.eventslist.' . $itemid . '.filter_month',
            'filter_month',
            '',
            'string',
        );

        //Filter only featured:
        if ($params->get('onlyfeatured')) {
            $this->getModel()->setState('filter.featured', 1);
        }

        // Resolve menu-level item presentation before the model builds its query.
        $itemDisplay = $this->prepareItemDisplay($params, $jemsettings);
        $model->setState('filter.show_contact_names', $itemDisplay['contact']);
        $model->setState('filter.contact_category_mode', $itemDisplay['contact_category']);

        // Get data from model
        $rows = $this->get('Items');

        $eventFilters = $this->prepareEventFilters($model, $params);

        if ($eventFilters['has_editable'] && method_exists($document, 'getWebAssetManager')) {
            $wa = $document->getWebAssetManager();

            if (!$wa->assetExists('script', 'com_jem.event-filters')) {
                $wa->registerScript(
                    'com_jem.event-filters',
                    'media/com_jem/js/event-filters.js',
                    array(),
                    array('defer' => true)
                );
            }

            $wa->useScript('com_jem.event-filters');
        }

        // Keep the table headers aligned with the validated model ordering.
        $lists['order_Dir'] = $model->getState('list.direction', 'ASC');
        $lists['order'] = $model->getState('list.ordering', 'a.dates');

        // Are events available?
        $noevents = !$rows ? 1 : 0;

        // params
        $pagetitle = $params->def(
            'page_title',
            $menuactive ? $menuactive->title : Text::_('COM_JEM_EVENTS'),
        );
        $pageheading = $params->def('page_heading', $params->get('page_title'));
        $pageclass_sfx = $params->get('pageclass_sfx');

        // pathway
        if ($menuactive) {
            $pathwayKeys = array_keys($pathway->getPathway());
            $lastPathwayEntryIndex = end($pathwayKeys);
            $pathway->setItemName($lastPathwayEntryIndex, $menuactive->title);
            //$pathway->setItemName(1, $menuactive->title);
        }

        if ($task == 'archive') {
            $pathway->addItem(
                Text::_('COM_JEM_ARCHIVE'),
                Route::_(
                    'index.php?option=com_jem&view=eventslist&task=archive',
                ),
            );
            $print_link = $uri->toString() . '?task=archive&print=1';
            $pagetitle .= ' - ' . Text::_('COM_JEM_ARCHIVE');
            $pageheading .= ' - ' . Text::_('COM_JEM_ARCHIVE');
            $archive_link = Route::_(
                'index.php?option=com_jem&view=eventslist',
            );
            $params->set('page_heading', $pageheading);
        } else {
            $print_link = $uri->toString() . '?tmpl=component&print=1';
            $archive_link = $uri->toString();
        }

        // Add site name to title if param is set
        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf(
                'JPAGETITLE',
                $app->get('sitename'),
                $pagetitle,
            );
        }
        elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf(
                'JPAGETITLE',
                $pagetitle,
                $app->get('sitename'),
            );
        }

        // Set Page title
        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        // Check if the user has permission to add things
        $permissions = new stdClass();
        $permissions->canAddEvent = $user->can('add', 'event');
        $permissions->canAddVenue = $user->can('add', 'venue');

        // add alternate feed link
        $link = 'index.php?option=com_jem&view=eventslist&format=feed';
        $attribs = ['type' => 'application/rss+xml', 'title' => 'RSS 2.0'];
        $document->addHeadLink(
            Route::_($link . '&type=rss'),
            'alternate',
            'rel',
            $attribs,
        );
        $attribs = ['type' => 'application/atom+xml', 'title' => 'Atom 1.0'];
        $document->addHeadLink(
            Route::_($link . '&type=atom'),
            'alternate',
            'rel',
            $attribs,
        );

        // search filter
        $filters = [];

        if ($jemsettings->showtitle == 1) {
            $filters[] = HTMLHelper::_(
                'select.option',
                '1',
                Text::_('COM_JEM_TITLE'),
            );
        }
        if ($jemsettings->showlocate == 1) {
            $filters[] = HTMLHelper::_(
                'select.option',
                '2',
                Text::_('COM_JEM_VENUE'),
            );
        }
        if ($jemsettings->showcity == 1) {
            $filters[] = HTMLHelper::_(
                'select.option',
                '3',
                Text::_('COM_JEM_CITY'),
            );
        }
        if ($jemsettings->showcat == 1) {
            $filters[] = HTMLHelper::_(
                'select.option',
                '4',
                Text::_('COM_JEM_CATEGORY'),
            );
        }
        if ($jemsettings->showstate == 1) {
            $filters[] = HTMLHelper::_(
                'select.option',
                '5',
                Text::_('COM_JEM_STATE'),
            );
        }
        $lists['filter'] = HTMLHelper::_(
            'select.genericlist',
            $filters,
            'filter_type',
            ['size' => '1', 'class' => 'form-select'],
            'value',
            'text',
            $filter_type,
        );
        $lists['search'] = $search;
        $lists['month'] = $search_month;

        // Create the pagination object
        $pagination = $this->get('Pagination');
        if ($pagination->getAdditionalUrlParam('id') === "0") {
            $pagination->setAdditionalUrlParam('id', null);
        }
        if ($menuactive && isset($menuactive->id)) {
            $pagination->setAdditionalUrlParam('Itemid', $menuactive->id);

            $currentUrl = $uri->toString();
            $baseUrl = 'index.php?option=com_jem&view=eventslist&Itemid=' . $menuactive->id;

            if ($task) {
                $baseUrl .= '&task=' . $task;
            }

            $pagination->setAdditionalUrlParam('option', 'com_jem');
            $pagination->setAdditionalUrlParam('view', 'eventslist');
            $pagination->setAdditionalUrlParam('start', null);
        }

        $this->lists         = $lists;
        $this->rows          = $rows;
        $this->noevents      = $noevents;
        $this->print_link    = $print_link;
        $this->archive_link  = $archive_link;
        $this->params        = $params;
        $this->dellink       = $permissions->canAddEvent; // deprecated
        $this->pagination    = $pagination;
        $this->action        = $uri->toString();
        $this->task          = $task;
        $this->jemsettings   = $jemsettings;
        $this->settings      = $settings;
        $this->permissions   = $permissions;
        $this->eventFilters  = $eventFilters;
        $this->itemDisplay   = $itemDisplay;
        $this->pagetitle     = $pagetitle;
        $this->pageclass_sfx = $pageclass_sfx
            ? htmlspecialchars($pageclass_sfx)
            : $pageclass_sfx;

        $this->_prepareDocument();
        parent::display($tpl);
    }

    /**
     * Prepare the shared frontend filter controls and read-only context.
     *
     * @param   object  $model   Eventslist model.
     * @param   object  $params  Active menu parameters.
     *
     * @return array<string, mixed>
     */
    private function prepareEventFilters($model, $params): array
    {
        $configuration = $model->getState(
            'filter.event_filter_config',
            JemEventFilterConfig::fromParams($params)
        );
        $categoryId = (int) $model->getState('filter.contact_category_id', 0);
        $contactIds = $model->getState('filter.contact_ids', array());
        $contactIds = is_array($contactIds) ? array_values(array_map('intval', $contactIds)) : array();
        $customValues = $model->getState('filter.custom_fields', array());
        $customValues = is_array($customValues) ? $customValues : array();
        $customFields = $this->getEnabledEventCustomFields();
        $context = JemHelper::getJoomlaContactFilterContext($categoryId, $contactIds);
        $visibleRows = array();
        $hasEditable = false;
        $needsCategoryOptions = false;
        $needsContactOptions = false;
        $includeChildren = true;

        foreach ($configuration['rows'] as $row) {
            if ($row['key'] === JemEventFilterConfig::CONTACT_CATEGORY) {
                $includeChildren = $row['condition'] === 'descendants';
            }

            if (!$row['visible']) {
                continue;
            }

            if ($row['key'] === JemEventFilterConfig::CONTACT_CATEGORY) {
                $row['label'] = Text::_('COM_JEM_EVENT_FILTER_CONTACT_CATEGORY');
                $row['effective_value'] = $categoryId;
                $row['display_value'] = $context['category'] ?? '';
            } elseif ($row['key'] === JemEventFilterConfig::CONTACT) {
                $row['label'] = Text::_('COM_JEM_EVENT_FILTER_CONTACT');
                $row['effective_value'] = $contactIds;
                $row['display_value'] = $context['contacts'] ?? '';
            } elseif (isset($customFields[$row['key']])) {
                $definition = $customFields[$row['key']];
                $value = (string) ($customValues[$row['key']]['value'] ?? '');
                $row['label'] = $definition['label'];
                $row['custom_type'] = $definition['type'];
                $row['custom_options'] = $definition['options'];
                $row['effective_value'] = $value;
                $row['display_value'] = $definition['type'] === JemCustomFields::TYPE_LIST
                    ? (string) ($definition['options'][$value] ?? $value)
                    : $value;
            } else {
                continue;
            }

            $visibleRows[] = $row;
            $hasEditable = $hasEditable || $row['editable'];
            $needsCategoryOptions = $needsCategoryOptions
                || ($row['key'] === JemEventFilterConfig::CONTACT_CATEGORY && $row['editable']);
            $needsContactOptions = $needsContactOptions
                || ($row['key'] === JemEventFilterConfig::CONTACT && $row['editable']);
        }

        return array(
            'rows' => $visibleRows,
            'has_visible' => !empty($visibleRows),
            'has_editable' => $hasEditable,
            'category_options' => $needsCategoryOptions
                ? JemHelper::getJoomlaContactCategoryOptions()
                : array(),
            'contact_options' => $needsContactOptions
                ? JemHelper::getJoomlaContactOptions($categoryId, $includeChildren)
                : array(),
            'category_id' => $categoryId,
            'contact_ids' => $contactIds,
        );
    }

    /**
     * Return enabled event custom fields with their translated presentation.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getEnabledEventCustomFields(): array
    {
        $fields = array();

        foreach (JemCustomFields::getOrderedFields('event') as $key) {
            if (!JemEventFilterConfig::isCustomKey($key)) {
                continue;
            }

            $config = JemCustomFields::getFieldConfig('event', $key);

            if (empty($config['enabled'])) {
                continue;
            }

            $index = (int) substr($key, 6);
            $fields[$key] = array(
                'label' => JemCustomFields::getLabel(
                    'event',
                    $key,
                    Text::_('COM_JEM_EVENT_CUSTOM_FIELD' . $index)
                ),
                'type' => (string) ($config['type'] ?? JemCustomFields::TYPE_TEXT),
                'options' => JemCustomFields::parseOptions($config['options'] ?? ''),
            );
        }

        return $fields;
    }

    /**
     * Resolve the metadata shown for every event row in this menu item.
     *
     * @param   object  $params       Active menu parameters.
     * @param   object  $jemsettings  Global JEM settings.
     *
     * @return array<string, bool|int>
     */
    private function prepareItemDisplay($params, $jemsettings): array
    {
        return array(
            'venue' => $this->resolveItemDisplayOption(
                $params->get('eventlist_show_venue', -1),
                (int) $jemsettings->showlocate === 1
            ),
            'city' => $this->resolveItemDisplayOption(
                $params->get('eventlist_show_city', -1),
                (int) $jemsettings->showcity === 1
            ),
            'county' => $this->resolveItemDisplayOption(
                $params->get('eventlist_show_county', -1),
                (int) $jemsettings->showstate === 1
            ),
            'type' => (int) $params->get('eventlist_show_type', 1) === 1,
            'category' => $this->resolveItemDisplayOption(
                $params->get('eventlist_show_category', -1),
                (int) $jemsettings->showcat === 1
            ),
            'contact' => (int) $params->get('eventlist_show_contact', 0) === 1,
            'contact_category' => min(2, max(0, (int) $params->get('eventlist_contact_category_mode', 1))),
        );
    }

    /**
     * Resolve a Show/Hide/Use Global menu option.
     */
    private function resolveItemDisplayOption($value, bool $global): bool
    {
        $value = (int) $value;

        return $value === -1 ? $global : $value === 1;
    }

    /**
     * Prepares the document
     */
    protected function _prepareDocument()
    {
        // TODO: Refactor with parent _prepareDocument() function

        //    $app   = Factory::getApplication();
        //    $menus = $app->getMenu();

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription(
                $this->params->get('menu-meta_description'),
            );
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetadata(
                'keywords',
                $this->params->get('menu-meta_keywords'),
            );
        }

        if ($this->params->get('robots')) {
            $this->document->setMetadata(
                'robots',
                $this->params->get('robots'),
            );
        }
    }
}
?>
