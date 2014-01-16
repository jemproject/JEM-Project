<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM Cleanup screen
 *
 * @package JEM
 *
 */
class JEMViewCleanup extends JViewLegacy {

	public function display($tpl = null) {

		$app = JFactory::getApplication();

		$this->totalcats = $this->get('Countcats');

		//only admins have access to this view
		if (!JFactory::getUser()->authorise('core.manage')) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::_('COM_JEM_ALERTNOTAUTH'));
			$app->redirect('index.php?option=com_jem&view=main');
		}

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Load Script
		JHtml::_('behavior.mootools');

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		JToolBarHelper::title(JText::_('COM_JEM_CLEANUP'), 'housekeeping');

		JToolBarHelper::back();
		JToolBarHelper::divider();
		JToolBarHelper::help('cleanup', true);
	}
}
?>