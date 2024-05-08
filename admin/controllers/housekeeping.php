<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Housekeeping-Controller
 */
class JemControllerHousekeeping extends BaseController
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask('cleaneventimg', 	'delete');
		$this->registerTask('cleanvenueimg', 	'delete');
		$this->registerTask('cleancategoryimg',	'delete');
	}

	/**
	 * logic to massdelete unassigned images
	 *
	 * @access public
	 * @return void
	 *
	 */
	public function delete()
	{
		// Check for request forgeries
		Session::checkToken('get') or jexit('Invalid Token');

		$task = Factory::getApplication()->input->get('task', '');
		$model = $this->getModel('housekeeping');

		if ($task == 'cleaneventimg') {
			$total = $model->delete($model::EVENTS);
		} elseif ($task == 'cleanvenueimg') {
			$total = $model->delete($model::VENUES);
		} elseif ($task == 'cleancategoryimg') {
			$total = $model->delete($model::CATEGORIES);
		}

		$link = 'index.php?option=com_jem&view=housekeeping';
		$msg = Text::sprintf('COM_JEM_HOUSEKEEPING_IMAGES_DELETED', $total);

		$this->setRedirect($link, $msg);
	}

	/**
	 * logic to truncate table cats_relations
	 *
	 * @access public
	 * @return void
	 *
	 */
	public function cleanupCatsEventRelations()
	{
		// Check for request forgeries
		Session::checkToken('get') or jexit('Invalid Token');

		$model = $this->getModel('housekeeping');
		$model->cleanupCatsEventRelations();

		$link = 'index.php?option=com_jem&view=housekeeping';
		$msg = Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS_DONE');

		$this->setRedirect($link, $msg);
	}

	/**
	 * Truncates JEM tables with exception of settings table
	 */
	public function truncateAllData()
	{
		// Check for request forgeries
		Session::checkToken('get') or jexit('Invalid Token');

		$model = $this->getModel('housekeeping');
		$model->truncateAllData();

		$link = 'index.php?option=com_jem&view=housekeeping';
		$msg = Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_DONE');

		$this->setRedirect($link, $msg);
	}

	/**
	 * Triggerarchive + Recurrences
	 *
	 * @access public
	 * @return void
	 *
	 */
	public function triggerarchive()
	{
		// Check for request forgeries
		Session::checkToken('get') or jexit('Invalid Token');

		JemHelper::cleanup(1);

		$link = 'index.php?option=com_jem&view=housekeeping';
		$msg = Text::_('COM_JEM_HOUSEKEEPING_AUTOARCHIVE_DONE');

		$this->setRedirect($link, $msg);
	}
}
?>
