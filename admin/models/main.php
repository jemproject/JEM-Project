<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * JEM Component Main Model
 *
 * @package JEM
 */
class JemModelMain extends BaseDatabaseModel
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
		$db = Factory::getContainer()->get('DatabaseDriver');

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
