<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;

require_once __DIR__ . '/eventslist.php';

/**
 * Events Blog model.
 */
class JemModelEventsblog extends JemModelEventslist
{
    protected function populateState($ordering = null, $direction = null)
    {
        parent::populateState($ordering, $direction);

        $app    = Factory::getApplication();
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $params = $app->getParams();
        $input  = $app->input;

        $allowedPeriods = array('all', 'today', 'tomorrow', 'week', 'weekend', 'next-week');
        $defaultPeriod = (string) $params->get('blog_default_period', 'all');
        $period = (int) $params->get('blog_show_date_filter', 1) === 1
            ? $input->getCmd('blog_period', $defaultPeriod)
            : $defaultPeriod;

        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'all';
        }

        $allowedCategories = $this->normaliseParamIds($params->get('blog_filter_categories', array()));
        $allowedVenues     = $this->normaliseParamIds($params->get('blog_filter_venues', array()));
        $allowedTypes      = $this->normaliseParamIds($params->get('blog_filter_types', array()));
        $allowedCountries  = array_values(array_unique(array_filter(array_map(static function ($value) {
            $value = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $value));

            return strlen($value) === 2 ? $value : '';
        }, $this->normaliseParamStrings($params->get('blog_filter_countries', array()))))));

        $categoryId = (int) $params->get('blog_show_category_filter', 1) === 1
            ? max(0, $input->getInt('blog_category', 0))
            : 0;
        $venueId = (int) $params->get('blog_show_venue_filter', 1) === 1
            ? max(0, $input->getInt('blog_venue', 0))
            : 0;
        $typeId = (int) $params->get('blog_show_type_filter', 1) === 1
            ? max(0, $input->getInt('blog_type', 0))
            : 0;
        $country = (int) $params->get('blog_show_country_filter', 1) === 1
            ? strtoupper(substr(preg_replace('/[^A-Z]/i', '', $input->getCmd('blog_country', '')), 0, 2))
            : '';

        $categoryId = $this->limitSelection($categoryId, $allowedCategories);
        $venueId    = $this->limitSelection($venueId, $allowedVenues);
        $typeId     = $this->limitSelection($typeId, $allowedTypes);
        $country    = $allowedCountries && !in_array($country, $allowedCountries, true) ? '' : $country;

        $this->setState('filter.blog_period', $period);
        $this->setState('filter.blog_category', $categoryId);
        $this->setState('filter.blog_venue', $venueId);
        $this->setState('filter.blog_type', $typeId);
        $this->setState('filter.blog_country', $country);
        $this->setState('filter.blog_allowed_categories', $allowedCategories);
        $this->setState('filter.blog_allowed_venues', $allowedVenues);
        $this->setState('filter.blog_allowed_types', $allowedTypes);
        $this->setState('filter.blog_allowed_countries', $allowedCountries);
        $this->setState('filter.opendates', 0);

        if ($categoryId > 0 || $allowedCategories) {
            $this->setState('filter.category_id', $categoryId > 0 ? $categoryId : $allowedCategories);
            $this->setState('filter.category_id.include', true);
        }

        if ($venueId > 0 || $allowedVenues) {
            $this->setState('filter.venue_id', $venueId > 0 ? $venueId : $allowedVenues);
            $this->setState('filter.venue_id.include', true);
        }

        if ($typeId > 0 || $allowedTypes) {
            $this->setState('filter.type_id', $typeId > 0 ? $typeId : $allowedTypes);
        }

        if ($country !== '' || $allowedCountries) {
            $this->setState('filter.country_id', $country !== '' ? array($country) : $allowedCountries);
            $this->setState('filter.country_id.include', true);
        }

        list($start, $end) = $this->getPeriodBounds($period, $app->get('offset', 'UTC'));

        if ($start !== null) {
            $this->setState('filter.calendar_from', '(COALESCE(a.enddates, a.dates) >= ' . $db->quote($start) . ')');
            $this->setState('filter.calendar_to', '(a.dates <= ' . $db->quote($end) . ')');
        } else {
            $this->setState('filter.calendar_from', null);
            $this->setState('filter.calendar_to', null);
        }

        $limit   = max(1, min(100, $input->getInt('limit', (int) $params->get('blog_events_per_page', 12))));
        $startAt = max(0, $input->getInt('limitstart', 0));

        $this->setState('list.limit', $limit);
        $this->setState('list.start', (int) (floor($startAt / $limit) * $limit));
        $this->setState('list.ordering', 'a.dates');
        $this->setState('list.direction', 'ASC');
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.blog_period');
        $id .= ':' . (int) $this->getState('filter.blog_category');
        $id .= ':' . (int) $this->getState('filter.blog_venue');
        $id .= ':' . (int) $this->getState('filter.blog_type');
        $id .= ':' . $this->getState('filter.blog_country');

        return parent::getStoreId($id);
    }

    /**
     * Keep a visitor selection inside the menu item's configured scope.
     */
    private function limitSelection($selected, array $allowed)
    {
        if (!$allowed || $selected === 0) {
            return $selected;
        }

        return in_array($selected, $allowed, true) ? $selected : 0;
    }

    /**
     * Return inclusive civil-date boundaries in the Joomla timezone.
     */
    private function getPeriodBounds($period, $timezone)
    {
        if ($period === 'all') {
            return array(null, null);
        }

        $today = new Date('now', $timezone);
        $today->setTime(0, 0, 0);
        $start = clone $today;
        $end   = clone $today;

        switch ($period) {
            case 'tomorrow':
                $start->modify('+1 day');
                $end = clone $start;
                break;

            case 'week':
                $start->modify('monday this week');
                $end = (clone $start)->modify('+6 days');
                break;

            case 'weekend':
                $start->modify('saturday this week');
                $end = (clone $start)->modify('+1 day');
                break;

            case 'next-week':
                $start->modify('monday next week');
                $end = (clone $start)->modify('+6 days');
                break;
        }

        return array($start->format('Y-m-d'), $end->format('Y-m-d'));
    }
}
