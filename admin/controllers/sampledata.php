<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Sampledata Controller
 * @package JEM
 */
class JemControllerSampledata extends JControllerLegacy
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Process sampledata
	 */
	public function load()
	{
		$model = $this->getModel('sampledata');

		if (!$model->loadData()) {
			$msg = JText::_('COM_JEM_SAMPLEDATA_FAILED');
		} else {
			$msg = JText::_('COM_JEM_SAMPLEDATA_SUCCESSFULL');
		}

		$link = 'index.php?option=com_jem&view=main';

		$this->setRedirect($link, $msg);
 	}
}
?>