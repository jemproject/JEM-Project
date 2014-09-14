<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM Updatecheck screen
 *
 * @package JEM
 *
 */
class JEMViewUpdatecheck extends JViewLegacy {


	public function display($tpl = null)
	{
		//Get data from the model
		$updatedata      	= $this->get('Updatedata');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Load script
		JHtml::_('behavior.framework');

		//assign data to template
		$this->updatedata	= $updatedata;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		JToolBarHelper::title(JText::_('COM_JEM_UPDATECHECK_TITLE'), 'settings');

		JToolBarHelper::back();
		JToolBarHelper::divider();
		JToolBarHelper::help('update', true);
	}
}