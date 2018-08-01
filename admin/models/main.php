<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Main Model
 *
 * @package JEM
 */
class JemModelMain extends JModelLegacy
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get number of items for given states of a table
	 *
	 * @param  string $tablename Name of the table
	 * @param  array  $map       Maps state name to state number
	 * @return stdClass
	 */
	protected function getStateData($tablename, &$map = null)
	{
		$db = JFactory::getDbo();

		if($map == null) {
			$map = array('published' => 1, 'unpublished' => 0, 'archived' => 2, 'trashed' => -2);
		}

		// Get nr of all states of events
		$query = $db->getQuery(true);
		$query->select(array('published', 'COUNT(published) as num'));
		$query->from($db->quoteName($tablename));
		if ($tablename == "#__jem_categories")
		{
		    $query->where('alias NOT LIKE "root"');
		}
		$query->group('published');

		$db->setQuery($query);
		$result = $db->loadObjectList("published");

		$data = new stdClass();
		$data->total = 0;

		foreach ($map as $key => $value) {
			if ($result) {
				// Check whether we have the current state in the DB result
				if(array_key_exists($value, $result)) {
					$data->$key = $result[$value]->num;
					$data->total += $data->$key;
				} else {
					$data->$key = 0;
				}
			} else {
				$data->$key = 0;
			}
		}

		return $data;
	}

	/**
	 * Returns number of events for all possible states
	 *
	 * @return stdClass
	 */
	public function getEventsData()
	{
		return $this->getStateData('#__jem_events');
	}

	/**
	 * Returns number of venues for all possible states
	 *
	 * @return stdClass
	 */
	public function getVenuesData()
	{
		return $this->getStateData('#__jem_venues');
	}

	/**
	 * Returns number of categories for all possible states
	 *
	 * @return stdClass
	 */
	public function getCategoriesData()
	{
		return $this->getStateData('#__jem_categories');
	}
}
?>