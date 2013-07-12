<?php
/**
 * @version 1.9
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
 * @since 0.9
 */
class JEMControllerCleanup extends JEMController
{
	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'cleaneventimg', 	'delete' );
		$this->registerTask( 'cleanvenueimg', 	'delete' );
		$this->registerTask( 'cleancategoryimg', 	'delete' );
	}

	/**
	 * logic to massdelete unassigned images
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function delete()
	{
		$task = JRequest::getCmd('task');

		if ($task == 'cleaneventimg') {
			$type = JText::_('COM_JEM_EVENT');
		} 
		
		if ($task == 'cleanvenueimg') {
			$type = JText::_('COM_JEM_VENUE');
		} 
		
		if ($task == 'cleancategoryimg') {
			$type = JText::_('COM_JEM_CATEGORY');
		} 
		


		$model = $this->getModel('cleanup');

		$total = $model->delete();

		$link = 'index.php?option=com_jem&view=cleanup';

		$msg = $total.' '.$type.' '.JText::_( 'COM_JEM_IMAGES_DELETED');

		$this->setRedirect( $link, $msg );
 	}
 	
 	 	
 	
  /**
   * 
   *
   * @access public
   * @return void
   * @since 0.9
   */
  function triggerarchive()
  {
    JEMHelper::cleanup(1);

    $link = 'index.php?option=com_jem&view=cleanup';

    $msg = JText::_( 'COM_JEM_AUTOARCHIVE_DONE');

    $this->setRedirect( $link, $msg );
  }
}
?>