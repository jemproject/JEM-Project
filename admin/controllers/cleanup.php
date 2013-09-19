<?php
/**
 * @version 1.9.1
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
		$this->registerTask('cleancategoryimg', 	'delete');
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

		if ($task == 'cleaneventimg') {
			$type = JText::_('COM_JEM_EVENT');
		} elseif ($task == 'cleanvenueimg') {
			$type = JText::_('COM_JEM_VENUE');
		} elseif ($task == 'cleancategoryimg') {
			$type = JText::_('COM_JEM_CATEGORY');
		}

		$model = $this->getModel('cleanup');
		$total = $model->delete();

		$link = 'index.php?option=com_jem&view=cleanup';
		// TODO: Use translation with variable
		$msg = $total.' '.$type.' '.JText::_('COM_JEM_IMAGES_DELETED');

		$this->setRedirect($link, $msg);
	}


	/**
	 * logic to truncate table cats_relations
	 *
	 * @access public
	 * @return void
	 *
	 */
	function truncatecats()
	{
		$model = $this->getModel('cleanup');
		$model->truncatecats();

		$link = 'index.php?option=com_jem&view=cleanup';
		$msg = JText::_('COM_JEM_CLEANUP_TRUNCATECATSEVENTREF_DONE');

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