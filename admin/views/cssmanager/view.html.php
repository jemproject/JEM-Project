<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die();

/**
 * View class for the Css-manager screen
 */
class JemViewCssmanager extends JemAdminView
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
		$user = JemFactory::getUser();

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
		JToolBarHelper::title(JText::_('COM_JEM_CSSMANAGER_TITLE'), 'thememanager');

		JToolBarHelper::back();
		JToolBarHelper::divider();
		JToolBarHelper::help('editcss', true);
	}
}