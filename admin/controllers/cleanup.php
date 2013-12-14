<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Cleanup Controller
 *
 * @package JEM
 *
 */
class JEMControllerCleanup extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 *
	 */
	function __construct()
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
	function delete()
	{
		$task = JRequest::getCmd('task');
		$model = $this->getModel('cleanup');

		if ($task == 'cleaneventimg') {
			$total = $model->delete($model::EVENTS);
		} elseif ($task == 'cleanvenueimg') {
			$total = $model->delete($model::VENUES);
		} elseif ($task == 'cleancategoryimg') {
			$total = $model->delete($model::CATEGORIES);
		}

		$link = 'index.php?option=com_jem&view=cleanup';
		$msg = JText::sprintf('COM_JEM_IMAGES_DELETED', $total);

		$this->setRedirect($link, $msg);
	}


	/**
	 * logic to truncate table cats_relations
	 *
	 * @access public
	 * @return void
	 *
	 */
	function cleanupCatsEventRelations()
	{
		$model = $this->getModel('cleanup');
		$model->cleanupCatsEventRelations();

		$link = 'index.php?option=com_jem&view=cleanup';
		$msg = JText::_('COM_JEM_CLEANUP_CLEANUP_CATSEVENT_RELS_DONE');

		$this->setRedirect($link, $msg);
	}


	/**
	 * Truncates JEM tables with exception of settings table
	 */
	public function truncateAllData() {
		$model = $this->getModel('cleanup');
		$model->truncateAllData();

		$link = 'index.php?option=com_jem&view=cleanup';
		$msg = JText::_('COM_JEM_CLEANUP_TRUNCATE_ALL_DATA_DONE');

		$this->setRedirect($link, $msg);
	}


	/**
	 * Triggerarchive + Recurrences
	 *
	 * @access public
	 * @return void
	 *
	 */
	function triggerarchive()
	{
		JEMHelper::cleanup(1);

		$link = 'index.php?option=com_jem&view=cleanup';
		$msg = JText::_('COM_JEM_AUTOARCHIVE_DONE');

		$this->setRedirect($link, $msg);
	}
}
?>