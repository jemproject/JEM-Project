<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Events Blog view.
 */
class JemViewEventsblog extends JemView
{
    public $categoryId = 0;
    public $country = '';
    public $itemId = 0;
    public $period = 'all';
    public $typeId = 0;
    public $venueId = 0;

    public function display($tpl = null)
    {
        $app        = Factory::getApplication();
        $document   = $app->getDocument();
        $menu       = $app->getMenu()->getActive();
        $params     = $app->getParams();
        $state      = $this->get('State');
        $rows       = $this->get('Items');
        $pagination = $this->get('Pagination');
        $itemId     = $menu ? (int) $menu->id : $app->input->getInt('Itemid', 0);

        JemHelper::loadCss('jem');
        JemHelper::loadCss('eventsblog');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        $pageTitle = $params->def('page_title', $menu ? $menu->title : Text::_('COM_JEM_EVENTSBLOG_TITLE'));
        $params->def('page_heading', $pageTitle);
        $document->setTitle($pageTitle);
        $document->setMetaData('title', $pageTitle);

        if ($params->get('menu-meta_description')) {
            $document->setDescription((string) $params->get('menu-meta_description'));
        }

        foreach ($rows as $row) {
            $row->eventLink = Route::_(JemHelperRoute::getEventRoute($row->slug));
            $image = !empty($row->datimage) ? JemImage::flyercreator($row->datimage, 'event', $row->image_path ?? '') : false;
            if ((!$image || empty($image['original'])) && !empty($row->locimage)) {
                $image = JemImage::flyercreator($row->locimage, 'venue', $row->venue_image_path ?? '');
            }
            $row->blogHasImage = $image && !empty($image['original']);
            $row->blogImage = $image && !empty($image['original'])
                ? Uri::root() . ltrim($image['original'], '/')
                : Uri::root() . 'media/com_jem/images/noimage.webp';
            $row->registrationOpen = JemHelper::isEventRegistrationOpen($row);
            $row->availabilityState = JemOutput::getEffectiveTicketAvailability($row);
        }

        foreach (array(
            'blog_period'   => (string) $state->get('filter.blog_period', 'all'),
            'blog_category' => (int) $state->get('filter.blog_category', 0),
            'blog_venue'    => (int) $state->get('filter.blog_venue', 0),
            'blog_type'     => (int) $state->get('filter.blog_type', 0),
            'blog_country'  => (string) $state->get('filter.blog_country', ''),
        ) as $key => $value) {
            if ($value !== '' && $value !== 0) {
                $pagination->setAdditionalUrlParam($key, $value);
            }
        }

        if ($itemId) {
            $pagination->setAdditionalUrlParam('Itemid', $itemId);
        }

        $this->rows          = array_values($rows);
        $this->pagination    = $pagination;
        $this->params        = $params;
        $this->jemsettings   = JemHelper::config();
        $this->action        = Route::_('index.php?option=com_jem&view=eventsblog' . ($itemId ? '&Itemid=' . $itemId : ''), false);
        $this->categories    = $this->getCategoryOptions((array) $state->get('filter.blog_allowed_categories', array()));
        $this->venues        = $this->getVenueOptions((array) $state->get('filter.blog_allowed_venues', array()));
        $this->types         = $this->getTypeOptions((array) $state->get('filter.blog_allowed_types', array()));
        $this->countries     = $this->getCountryOptions((array) $state->get('filter.blog_allowed_countries', array()));
        $this->period        = (string) $state->get('filter.blog_period', 'all');
        $this->categoryId    = (int) $state->get('filter.blog_category', 0);
        $this->venueId       = (int) $state->get('filter.blog_venue', 0);
        $this->typeId        = (int) $state->get('filter.blog_type', 0);
        $this->country       = (string) $state->get('filter.blog_country', '');
        $this->itemId        = $itemId;
        $this->pageclass_sfx = htmlspecialchars((string) $params->get('pageclass_sfx', ''), ENT_QUOTES, 'UTF-8');

        parent::display($tpl);
    }

    private function getCategoryOptions(array $allowedIds = array())
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $levels = array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels());
        $query  = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('catname', 'text')))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', $levels) . ')')
            ->order($db->quoteName('catname') . ' ASC');

        if ($allowedIds) {
            $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')');
        }

        return $db->setQuery($query)->loadObjectList() ?: array();
    }

    private function getVenueOptions(array $allowedIds = array())
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $levels = array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels());
        $query  = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('venue', 'text')))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', $levels) . ')')
            ->order($db->quoteName('venue') . ' ASC');

        if ($allowedIds) {
            $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')');
        }

        return $db->setQuery($query)->loadObjectList() ?: array();
    }

    private function getTypeOptions(array $allowedIds = array())
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $levels = array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels());
        $query  = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('name', 'text')))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = 1')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', $levels) . ')')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        if ($allowedIds) {
            $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')');
        }

        return $db->setQuery($query)->loadObjectList() ?: array();
    }

    private function getCountryOptions(array $allowedCountries = array())
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $levels = array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels());
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('v.country', 'value'))
            ->select($db->quoteName('ct.name', 'text'))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->join('LEFT', $db->quoteName('#__jem_countries', 'ct') . ' ON ' . $db->quoteName('ct.iso2') . ' = ' . $db->quoteName('v.country'))
            ->where($db->quoteName('v.published') . ' = 1')
            ->where($db->quoteName('v.access') . ' IN (' . implode(',', $levels) . ')')
            ->where($db->quoteName('v.country') . ' <> ' . $db->quote(''))
            ->order($db->quoteName('ct.name') . ' ASC');

        if ($allowedCountries) {
            $query->where($db->quoteName('v.country') . ' IN (' . implode(',', array_map(array($db, 'quote'), $allowedCountries)) . ')');
        }

        return $db->setQuery($query)->loadObjectList() ?: array();
    }
}
