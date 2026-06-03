<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Filesystem\Path;

/**
 * Model-Attachments
 */
class JemModelAttachments extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'file', 'a.file',
                'name', 'a.name',
                'object', 'a.object',
                'object_type',
                'linked_title',
                'linked_published',
                'access', 'a.access', 'access_level',
                'frontend', 'a.frontend',
                'created', 'a.created',
                'created_by_name',
            );
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter_search', 'filter_search');
        $this->setState('filter_search', $search);

        $type = $this->getUserStateFromRequest($this->context . '.filter_type', 'filter_type', '', 'cmd');
        $this->setState('filter_type', $type);

        $frontend = $this->getUserStateFromRequest($this->context . '.filter_frontend', 'filter_frontend', '', 'string');
        $this->setState('filter_frontend', $frontend);

        $access = $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access', 0, 'int');
        $this->setState('filter.access', $access);

        parent::populateState('a.created', 'desc');
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter_search');
        $id .= ':' . $this->getState('filter_type');
        $id .= ':' . $this->getState('filter_frontend');
        $id .= ':' . $this->getState('filter.access');

        return parent::getStoreId($id);
    }

    protected function getListQuery($applyFilters = true)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $objectType = 'CASE '
            . ' WHEN ' . $db->quoteName('e.id') . ' IS NOT NULL THEN ' . $db->quote('event')
            . ' WHEN ' . $db->quoteName('v.id') . ' IS NOT NULL THEN ' . $db->quote('venue')
            . ' WHEN ' . $db->quoteName('c.id') . ' IS NOT NULL THEN ' . $db->quote('category')
            . ' ELSE ' . $db->quote('other')
            . ' END';

        $linkedTitle = 'CASE '
            . ' WHEN ' . $db->quoteName('e.id') . ' IS NOT NULL THEN ' . $db->quoteName('e.title')
            . ' WHEN ' . $db->quoteName('v.id') . ' IS NOT NULL THEN ' . $db->quoteName('v.venue')
            . ' WHEN ' . $db->quoteName('c.id') . ' IS NOT NULL THEN ' . $db->quoteName('c.catname')
            . ' ELSE NULL END';

        $linkedPublished = 'CASE '
            . ' WHEN ' . $db->quoteName('e.id') . ' IS NOT NULL THEN ' . $db->quoteName('e.published')
            . ' WHEN ' . $db->quoteName('v.id') . ' IS NOT NULL THEN ' . $db->quoteName('v.published')
            . ' WHEN ' . $db->quoteName('c.id') . ' IS NOT NULL THEN ' . $db->quoteName('c.published')
            . ' ELSE NULL END';

        $query->select('a.*')
            ->select($objectType . ' AS ' . $db->quoteName('object_type'))
            ->select($linkedTitle . ' AS ' . $db->quoteName('linked_title'))
            ->select($linkedPublished . ' AS ' . $db->quoteName('linked_published'))
            ->select($db->quoteName('vl.title', 'access_level'))
            ->select($db->quoteName('u.name', 'created_by_name'))
            ->from($db->quoteName('#__jem_attachments', 'a'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'e') . ' ON ' . $db->quoteName('a.object') . ' = CONCAT(' . $db->quote('event') . ', ' . $db->quoteName('e.id') . ')')
            ->join('LEFT', $db->quoteName('#__jem_venues', 'v') . ' ON ' . $db->quoteName('a.object') . ' = CONCAT(' . $db->quote('venue') . ', ' . $db->quoteName('v.id') . ')')
            ->join('LEFT', $db->quoteName('#__jem_categories', 'c') . ' ON ' . $db->quoteName('a.object') . ' = CONCAT(' . $db->quote('category') . ', ' . $db->quoteName('c.id') . ')')
            ->join('LEFT', $db->quoteName('#__viewlevels', 'vl') . ' ON ' . $db->quoteName('vl.id') . ' = ' . $db->quoteName('a.access'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'));

        if ($applyFilters) {
            $search = $this->getState('filter_search');
            if (!empty($search)) {
                if (stripos($search, 'id:') === 0) {
                    $query->where($db->quoteName('a.id') . ' = ' . (int) substr($search, 3));
                } else {
                    $search = $db->quote('%' . $db->escape($search, true) . '%');
                    $query->where('('
                        . $db->quoteName('a.file') . ' LIKE ' . $search
                        . ' OR ' . $db->quoteName('a.name') . ' LIKE ' . $search
                        . ' OR ' . $db->quoteName('a.description') . ' LIKE ' . $search
                        . ' OR ' . $db->quoteName('a.object') . ' LIKE ' . $search
                        . ' OR ' . $db->quoteName('e.title') . ' LIKE ' . $search
                        . ' OR ' . $db->quoteName('v.venue') . ' LIKE ' . $search
                        . ' OR ' . $db->quoteName('c.catname') . ' LIKE ' . $search
                        . ')');
                }
            }

            $type = $this->getState('filter_type');
            if (in_array($type, array('event', 'venue', 'category'), true)) {
                $query->where($db->quoteName('a.object') . ' LIKE ' . $db->quote($type . '%'));
            }

            $frontend = $this->getState('filter_frontend');
            if (in_array((string) $frontend, array('0', '1'), true)) {
                $query->where($db->quoteName('a.frontend') . ' = ' . (int) $frontend);
            }

            if ($access = $this->getState('filter.access')) {
                $query->where($db->quoteName('a.access') . ' = ' . (int) $access);
            }
        }

        $orderCol = $this->state->get('list.ordering', 'a.created');
        $orderDir = $this->state->get('list.direction', 'desc');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function getItems()
    {
        $items = parent::getItems();

        return $this->enrichItems($items);
    }

    public function exportCsv($cid = array())
    {
        $db = $this->getDatabase();
        $query = $this->getListQuery(false);
        $cid = array_values(array_filter(array_map('intval', (array) $cid)));

        if (!empty($cid)) {
            $query->where($db->quoteName('a.id') . ' IN (' . implode(',', $cid) . ')');
        }

        $db->setQuery($query);
        $items = $this->enrichItems($db->loadObjectList() ?: array());

        $csv = fopen('php://output', 'w');
        fputcsv($csv, array(
            'id',
            'file',
            'name',
            'description',
            'object',
            'object_type',
            'object_id',
            'linked_title',
            'linked_published',
            'access',
            'access_level',
            'frontend',
            'created',
            'created_by',
            'created_by_name',
            'file_status',
            'file_size',
        ), ';', '"', '\\');

        foreach ($items as $item) {
            $fileStatus = !$item->file_path_safe ? 'unsafe' : ($item->file_exists ? 'exists' : 'missing');

            fputcsv($csv, array(
                $item->id,
                $item->file,
                $item->name,
                $item->description,
                $item->object,
                $item->object_type,
                $item->object_id,
                $item->linked_title,
                $item->linked_published,
                $item->access,
                $item->access_level,
                $item->frontend,
                $item->created,
                $item->created_by,
                $item->created_by_name,
                $fileStatus,
                $item->file_size,
            ), ';', '"', '\\');
        }

        fclose($csv);
    }

    private function enrichItems($items)
    {
        $jemsettings = JemHelper::config();
        $basePath = Path::clean(JPATH_SITE . '/' . trim((string) $jemsettings->attachments_path));
        $baseCheck = rtrim(strtolower($basePath), '\\/') . DIRECTORY_SEPARATOR;

        foreach ($items as $item) {
            $item->object_id = 0;

            if (preg_match('/^([a-z]+)([0-9]+)$/i', (string) $item->object, $matches)) {
                $item->object_type = strtolower($matches[1]);
                $item->object_id = (int) $matches[2];
            }

            $item->file_exists = false;
            $item->file_size = null;
            $item->file_path_safe = false;

            if ((string) $item->file !== '' && basename((string) $item->file) === (string) $item->file) {
                $path = Path::clean($basePath . '/' . $item->object . '/' . $item->file);
                $item->file_path_safe = strpos(strtolower($path), $baseCheck) === 0;

                if ($item->file_path_safe && is_file($path)) {
                    $item->file_exists = true;
                    $item->file_size = filesize($path);
                }
            }
        }

        return $items;
    }
}
