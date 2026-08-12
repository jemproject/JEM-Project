<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class JemModelTaxrates extends ListModel
{
    public function __construct($config = array())
    {
        $config['filter_fields'] = $config['filter_fields'] ?? array(
            'id', 'a.id', 'code', 'a.code', 'name', 'a.name', 'tax_type', 'a.tax_type',
            'rate', 'a.rate', 'country_code', 'a.country_code', 'valid_from', 'a.valid_from',
            'valid_until', 'a.valid_until', 'published', 'a.published', 'ordering', 'a.ordering',
        );
        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $defaultCountry = strtoupper(trim((string) (JemAdmin::config()->defaultCountry ?? '')));
        if (preg_match('/^[A-Z]{2}$/D', $defaultCountry) !== 1) {
            $defaultCountry = '';
        }

        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter_search', 'filter_search'));
        $this->setState('filter.state', $this->getUserStateFromRequest($this->context . '.filter_state', 'filter_state', '', 'string'));
        $this->setState('filter.tax_type', $this->getUserStateFromRequest($this->context . '.filter_tax_type', 'filter_tax_type', '', 'cmd'));
        $this->setState('filter.country_code', $this->getUserStateFromRequest($this->context . '.filter_country_code', 'filter_country_code', $defaultCountry, 'cmd'));
        parent::populateState('a.ordering', 'asc');
    }

    protected function getStoreId($id = '')
    {
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.state') . ':' . $this->getState('filter.tax_type') . ':' . $this->getState('filter.country_code'));
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.*')
            ->select($db->quoteName('u.name', 'author_name'))
            ->from($db->quoteName('#__jem_tax_rates', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'));

        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(a.code LIKE ' . $search . ' OR a.name LIKE ' . $search . ')');
        }
        $state = $this->getState('filter.state');
        if (is_numeric($state)) {
            $query->where('a.published = ' . (int) $state);
        } elseif ($state === '') {
            $query->where('a.published IN (0, 1)');
        }
        $type = trim((string) $this->getState('filter.tax_type'));
        if ($type !== '') {
            $query->where('a.tax_type = ' . $db->quote($type));
        }
        $countryCode = strtoupper(trim((string) $this->getState('filter.country_code')));
        if ($countryCode !== '') {
            $query->where('a.country_code = ' . $db->quote($countryCode));
        }

        $order = $this->state->get('list.ordering', 'a.ordering');
        $direction = strtoupper($this->state->get('list.direction', 'ASC'));
        if (!in_array($order, $this->filter_fields, true)) {
            $order = 'a.ordering';
        }
        if (!in_array($direction, array('ASC', 'DESC'), true)) {
            $direction = 'ASC';
        }

        return $query->order($db->escape($order) . ' ' . $direction);
    }

    public function getCountries(): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('t.country_code', 'value'))
            ->select('COALESCE(' . $db->quoteName('c.name') . ', ' . $db->quoteName('t.country_code') . ') AS ' . $db->quoteName('text'))
            ->from($db->quoteName('#__jem_tax_rates', 't'))
            ->join('LEFT', $db->quoteName('#__jem_countries', 'c') . ' ON ' . $db->quoteName('c.iso2') . ' = ' . $db->quoteName('t.country_code'))
            ->where($db->quoteName('t.country_code') . " <> ''")
            ->order($db->quoteName('text') . ' ASC');
        $db->setQuery($query);

        return (array) $db->loadObjectList();
    }
}
