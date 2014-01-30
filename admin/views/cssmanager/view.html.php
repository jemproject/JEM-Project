<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die();

/**
 * View class for the Css-manager screen
 */
class JEMViewCssmanager extends JViewLegacy
{

	protected $files;

	function display($tpl = null)
	{
		$this->files = $this->get('Files');
		$this->statusLinenumber = $this->get('StatusLinenumber');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		$app = JFactory::getApplication();

		// initialise variables
		$document = JFactory::getDocument();
		$user = JFactory::getUser();

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 */
	protected function addToolbar()
	{
		JRequest::setVar('hidemainmenu', true);

		JToolBarHelper::title(JText::_('COM_JEM_CSSMANAGER_TITLE'), 'thememanager');

		JToolBarHelper::cancel('cssmanager.cancel', 'JTOOLBAR_CLOSE');
		JToolBarHelper::divider();
		JToolBarHelper::help('editcss', true);
	}
}