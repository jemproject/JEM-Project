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
 * JEM Component Sampledata Controller
 *
 * @package JEM
 *
 */
class JEMControllerSampledata extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 *
	 */
	function __construct()
	{
		parent::__construct();
	}

 	/**
	 * Process sampledata
	 *
	 * @access public
	 * @return void
	 *
	 */
	function load()
	{
		//get model
		$model 	= $this->getModel('sampledata');
		if (!$model->loaddata()) {
			$msg 	= JText::_( 'COM_JEM_SAMPLEDATA_FAILED' );
		} else {
			$msg 	= JText::_( 'COM_JEM_SAMPLEDATA_SUCCESSFULL' );
		}

		$link 	= 'index.php?option=com_jem&view=jem';

		$this->setRedirect($link, $msg);
 	}
}
?>