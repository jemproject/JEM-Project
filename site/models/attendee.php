<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

/**
 * JEM Component attendee Model
 *
 * @package JEM
 *
 */
class JemModelAttendee extends BaseDatabaseModel
{
	/**
	 * Attendee id
	 *
	 * @var int
	 */
	protected $_id = null;

	/**
	 * Attendee data array
	 *
	 * @var array
	 */
	protected $_data = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$settings = JemHelper::globalattribs();
		$this->regname = $settings->get('global_regname','1');

		$array = Factory::getApplication()->input->get('cid', array(0), 'array');
		$this->setId((int)$array[0]);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access public
	 * @param  int attendee/registration identifier
	 */
	public function setId($id)
	{
		// Set category id and wipe data
		$this->_id = $id;
		$this->_data = null;
	}

	/**
	 * Method to get attendee data
	 *
	 * @access public
	 * @return array
	 */
	public function getData()
	{
		if (!$this->_loadData()) {
			$this->_initData();
		}

		return $this->_data;
	}

	/**
	 * Method to load attendee data
	 *
	 * @access protected
	 * @return boolean  True on success
	 */
	protected function _loadData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = 'SELECT r.*, ' . ($this->regname ? 'u.name' : 'u.username') . ' AS username '
			       . ' FROM #__jem_register AS r '
			       . ' LEFT JOIN #__users AS u ON u.id = r.uid '
			       . ' WHERE r.id = '.$this->_db->quote($this->_id)
			       ;
			$this->_db->setQuery($query);
			$this->_data = $this->_db->loadObject();

			return (boolean) $this->_data;
		}

		return true;
	}

	/**
	 * Method to initialise attendee data
	 *
	 * @access protected
	 * @return boolean  True on success
	 */
	protected function _initData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data)) {
			$data = Table::getInstance('jem_register', '');
			$data->username = null;
			$this->_data = $data;
		}

		return true;
	}

	public function toggle()
	{
		$attendee = $this->getData();

		if (!$attendee->id) {
			$this->setError(Text::_('COM_JEM_MISSING_ATTENDEE_ID'));
			return false;
		}

		$row = Table::getInstance('jem_register', '');
		$row->bind($attendee);
		$row->waiting = $attendee->waiting ? 0 : 1;

		return $row->store();
	}

	/**
	 * Method to store the attendee
	 *
	 * @access public
	 * @return boolean  True on success
	 */
	public function store($data)
	{
		$eventid = $data['event'];

		$row = $this->getTable('jem_register', '');

		// bind it to the table
		if (!$row->bind($data)) {
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($this->_db->getErrorMsg(), 'error');
			return false;
		}

		// sanitise id field
		$row->id = (int) $row->id;

		// Are we saving from an item edit?
		if (!$row->id) {
			$row->uregdate = gmdate('Y-m-d H:i:s');

			$query = ' SELECT e.maxplaces, e.waitinglist, COUNT(r.id) as booked '
			       . ' FROM #__jem_events AS e '
			       . ' INNER JOIN #__jem_register AS r ON r.event = e.id '
			       . ' WHERE e.id = ' . $this->_db->Quote($eventid)
			       . '   AND r.status = 1 AND r.waiting = 0 '
			       . ' GROUP BY e.id ';
			$this->_db->setQuery($query);
			$details = $this->_db->loadObject();

			// put on waiting list ?
			if ($details->maxplaces > 0) // there is a max
			{
				// check if the user should go on waiting list
				if ($details->booked >= $details->maxplaces)
				{
					if (!$details->waitinglist) {
						\Joomla\CMS\Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_REGISTER_EVENT_IS_FULL'), 'warning');
						return false;
					}
					$row->waiting = 1;
				}
			}
		}

		// Make sure the data is valid
		if (!$row->check()) {
			$this->setError($row->getError());
			return false;
		}

		// Store it in the db
		if (!$row->store()) {
			Factory::getApplication()->enqueueMessage($this->_db->getErrorMsg(), 'error');
			return false;
		}

		return $row;
	}
}
?>
