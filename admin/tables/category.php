<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Nested;
use Joomla\Registry\Registry;

jimport('joomla.database.tablenested');

/**
 * Category Table
 */
class JemTableCategory extends Nested
{
    public function __construct(&$db)
    {
        parent::__construct('#__jem_categories', 'id', $db);

        if (self::addRoot() !== false) {
            return;
        }
    }

    /**
     * Method to delete a node and, optionally, its child nodes from the table.
     *
     * @param  integer  $pk        The primary key of the node to delete.
     * @param  boolean  $children  True to delete child nodes, false to move them up a level.
     *
     * @return boolean
     */
    public function delete($pk = null, $children = false)
    {
        return parent::delete($pk, $children);
    }

    /**
     * Add the root node to an empty table.
     *
     * @return integer  The id of the new root node.
     */
    public function addRoot()
    {
        if (self::getRootId() !== false) {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Insert columns.
        $columns = [
            'parent_id', 'lft', 'rgt', 'level',
            'catname', 'alias', 'access', 'title', 'published'
        ];

        // Insert values.
        $values = [
            0, 0, 1, 0,
            $db->quote('root'),
            $db->quote('root'),
            1,
            $db->quote('root'),
            1
        ];

        // Prepare the insert query.
        $query
        ->insert($db->quoteName('#__jem_categories'))
        ->columns($db->quoteName($columns))
        ->values(implode(',', $values));

        $db->setQuery($query);
        $db->execute();

        return $db->insertid();
    }

    /**
     * Try to insert first, ignore if already exists.
     *
     * @param  boolean  $updateNulls
     * @return int|null
     */
    public function insertIgnore($updateNulls = false)
    {
        $ret = $this->_insertIgnoreObject($this->_tbl, $this, $this->_tbl_key);

        if ($ret < 0) {
            $this->setError(get_class($this).'::store failed - '.$this->_db->getError());
        }

        return $ret;
    }

    /**
     * Insert object into table using INSERT IGNORE.
     * Empty strings are skipped to avoid invalid DATETIME values.
     *
     * @param  string  $table
     * @param  object  $object
     * @param  string  $keyName
     *
     * @return int
     */
    protected function _insertIgnoreObject($table, &$object, $keyName = NULL)
    {
        $fmtsql = 'INSERT IGNORE INTO '.$this->_db->quoteName($table).' (%s) VALUES (%s) ';
        $fields = [];
        $values = [];

        foreach (get_object_vars($object) as $k => $v) {
            // Skip internal, complex, null or empty-string values
            if (
                $k[0] === '_' ||
                is_array($v) ||
                is_object($v) ||
                $v === null ||
                $v === ''
            ) {
                continue;
            }

            $fields[] = $this->_db->quoteName($k);
            $values[] = $this->_db->quote($v);
        }

        if (empty($fields)) {
            return 0;
        }

        $this->_db->setQuery(sprintf($fmtsql, implode(',', $fields), implode(',', $values)));

        try {
            $this->_db->execute();
        } catch (\RuntimeException $e) {
            return -1;
        }

        $id = $this->_db->insertid();

        if ($keyName && $id) {
            $object->$keyName = $id;
        }

        return $this->_db->getAffectedRows();
    }

    /**
     * Overloaded check function.
     *
     * @return boolean
     */
    public function check()
    {
        // Check for a title.
        if (trim($this->catname) === '') {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_MUSTCONTAIN_A_TITLE_CATEGORY'));
            return false;
        }

        $this->alias = trim($this->alias);

        if ($this->alias === '') {
            $this->alias = $this->catname;
        }

        $this->alias = JemHelper::stringURLSafe($this->alias);

        if (trim(str_replace('-', '', $this->alias)) === '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        return true;
    }

    /**
     * Overloaded bind function.
     *
     * @param  array   $array
     * @param  string  $ignore
     *
     * @return mixed
     */
    public function bind($array, $ignore = '')
    {
        if (isset($array['params']) && is_array($array['params'])) {
            $registry = new Registry;
            $registry->loadArray($array['params']);
            $array['params'] = (string) $registry;
        }

        if (isset($array['metadata']) && is_array($array['metadata'])) {
            $registry = new Registry;
            $registry->loadArray($array['metadata']);
            $array['metadata'] = (string) $registry;
        }

        if (isset($array['rules']) && is_array($array['rules'])) {
            $rules = new JAccessRules($array['rules']);
            $this->setRules($rules);
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Overloaded store to set created/modified timestamps and user IDs.
     *
     * @param  boolean  $updateNulls
     * @return boolean
     */
    public function store($updateNulls = false)
    {
        $date = Factory::getDate();
        $user = JemFactory::getUser();

        if ($this->id) {
            // Existing category
            $this->modified_time = $date->toSql();
            $this->modified_user_id = $user->get('id');
        } else {
            // New category
            $this->created_time = $date->toSql();
            $this->created_user_id = $user->get('id');
        }

        // Ensure alias uniqueness within parent
        $table = Table::getInstance(
            'Category',
            'JEMTable',
            ['dbo' => Factory::getContainer()->get('DatabaseDriver')]
        );

        if (
            $table->load(['alias' => $this->alias, 'parent_id' => $this->parent_id]) &&
            ($table->id != $this->id || $this->id == 0)
        ) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_CATEGORY_UNIQUE_ALIAS'));
            return false;
        }

        return parent::store($updateNulls);
    }

    /**
     * CSV import validation.
     *
     * @return boolean
     */
    public function checkCsvImport()
    {
        foreach (get_object_vars($this) as $k => $v) {
            if (
                is_array($v) ||
                is_object($v) ||
                $v === null ||
                $k[0] === '_'
            ) {
                continue;
            }

            // Normalize invalid datetime values
            if (strpos($v, '0000-00-00') !== false) {
                $this->$k = null;
            }
        }

        return true;
    }

    /**
     * Store CSV import.
     *
     * @param  boolean  $updateNulls
     * @return boolean
     */
    public function storeCsvImport($updateNulls = false)
    {
        // Initialise variables.
        $k = $this->_tbl_key;

        // If a primary key exists update the object, otherwise insert it.
        if ($this->$k) {
            $stored = $this->_db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
        } else {
            $stored = $this->_db->insertObject($this->_tbl, $this, $this->_tbl_key);
        }

        // If the store failed return false.
        if (!$stored) {
            $this->setError(
                Text::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this))
            );
            return false;
        }

        if ($this->_locked) {
            $this->_unlock();
        }

        return true;
    }
}
