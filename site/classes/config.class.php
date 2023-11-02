<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * JemConfig class to handle JEM configuration
 *
 * @package JEM
 */
class JemConfig
{
	/**
	 * Data Object
	 *
	 * @var    Registry object
	 * @since  2.1.6
	 */
	protected $_data;

	/**
	 * Class instance.
	 *
	 * @var    object
	 * @since  2.1.6
	 */
	protected static $instance;

	/**
	 * Returns a reference to the global JemConfig object, only creating it
	 * if it doesn't already exist.
	 *
	 * This method must be invoked as:
	 * <pre>$jemConfig = JemConfig::getInstance();</pre>
	 *
	 * @return  JemConfig  The JemConfig object.
	 *
	 * @since   2.1.6
	 */
	public static function getInstance()
	{
		if (empty(self::$instance))
		{
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @param   mixed  $data  The data to bind to the new Registry object.
	 *
	 * @since   1.0
	 */
	protected function __construct()
	{
		// Instantiate the internal data object.
		$this->_data = new JRegistry($this->loadData());

		// Load data from database
		;
	}

	/**
	 * Gets configuration data as Registry.
	 *
	 * @return  object   An object holding the configuration data
	 *
	 * @since   2.1.6
	 */
	public function toRegistry()
	{
		return $this->_data;
	}

	/**
	 * Gets configuration data as object.
	 *
	 * @return  object   An object holding the configuration data
	 *
	 * @since   2.1.6
	 */
	public function toObject()
	{
		return $this->_data->toObject();
	}

	/**
	 * Loading the table data
	 */
	protected function loadData()
	{
        $db = Factory::getContainer()->get('DatabaseDriver');

		// new table
		$query = $db->getQuery(true);
		$query->select(array($db->quoteName('keyname'), $db->quoteName('value')));
		$query->from('#__jem_config');
		$db->setQuery($query);
		try {
			$list = $db->loadAssocList('keyname', 'value');
		} catch (Exception $e) {}

		if (!empty($list)) {
			$data = (object)$list;
		} else {
			// old table
			$query = $db->getQuery(true);
			$query->select(array('*'));
			$query->from('#__jem_settings');
			$query->where(array('id = 1 '));

			$db->setQuery($query);
			try {
				$data = $db->loadObject();
			} catch (Exception $e) {}
		}

		// Convert the params field to an array.
		if (!empty($data->globalattribs)) {
			$registry = new JRegistry;
			$registry->loadString($data->globalattribs);
			$data->globalattribs = $registry->toObject();
		}

		// Convert Css settings to an array
		if (!empty($data->css)) {
			$registryCss = new JRegistry;
			$registryCss->loadString($data->css);
			$data->css = $registryCss->toObject();
		}

		return $data;
	}

	/**
	 * Bind the data
	 *
	 */
	public function bind($data)
	{
		$reg = new JRegistry($data);
		$this->_data->loadObject($reg->toObject());

		return true;
	}

	/**
	 * Set a singla value.
	 *
	 * @param  string $key   The key.
	 * @param  string $value Value to set.
	 * @return mixed         The value set or null.
	 */
	public function set($key, $value)
	{
		$result = $this->_data->set($key, $value);

		if (!is_null($result)) {
			if (!$this->store()) {
				$result = null;
			}
		}

		return $result;
	}

	/**
	 * Store data
	 *
	 */
	public function store()
	{
		$data = $this->_data->toArray();

		// Convert the params field to an array.
		if (isset($data['globalattribs'])) {
			$registry = new JRegistry($data['globalattribs']);
			$data['globalattribs'] = $registry->toString();
		}

		// Convert Css settings to an array
		if (isset($data['css'])) {
			$registryCss = new JRegistry($data['css']);
			$data['css'] = $registryCss->toString();
		}

		// Store into new table
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select(array($db->quoteName('keyname'), $db->quoteName('value')));
		$query->from('#__jem_config');
		$db->setQuery($query);
		$list = $db->loadAssocList('keyname', 'value');
		$keys = array_keys($list);

		foreach ($data as $k => $v) {
			$query = $db->getQuery(true);
			if (in_array($k, $keys)) {
				if ($v == $list[$k]) {
					continue; // skip if unchanged
				}
				$query->update('#__jem_config');
				$query->where(array($db->quoteName('keyname') . ' = ' . $db->quote($k)));
			} else {
				$query->insert('#__jem_config');
				$query->set(array($db->quoteName('keyname') . ' = ' . $db->quote($k)));
			}
			$query->set(array($db->quoteName('value') . ' = ' . $db->quote($v)));
			$db->setQuery($query);
			$db->execute();
		}

		return true;
	}

}
