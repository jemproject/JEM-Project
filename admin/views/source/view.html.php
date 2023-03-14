<?php
/**
 * @version 2.3.10
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

/**
 * Source view
 *
 */
class JemViewSource extends JemAdminView
{
	protected $form;
	protected $ftp;
	protected $source;
	protected $state;
	protected $template;

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		// Initialise variables.
		$this->form     = $this->get('Form');
		$this->ftp      = JClientHelper::setCredentialsFromRequest('ftp');
		$this->source   = $this->get('Source');
		$this->state    = $this->get('State');
		$this->template = $this->get('Template');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 */
	protected function addToolbar()
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$user  = JemFactory::getUser();
		$canDo = JemHelperBackend::getActions(0);

		ToolbarHelper::title(Text::_('COM_JEM_CSSMANAGER_EDIT_FILE'), 'thememanager');

		// Can save the item.
		if ($canDo->get('core.edit')) {
			ToolbarHelper::apply('source.apply');
			ToolbarHelper::save('source.save');
		}

		ToolbarHelper::cancel('source.cancel', 'JTOOLBAR_CLOSE');
		ToolbarHelper::divider();
		ToolbarHelper::help('editcss', true);
	}
}
