<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

/**
 * View class for the JEM Settings screen
 *
 * @package JEM
 */
class JemViewSettings extends JemAdminView
{
	protected $form;
	protected $data;
	protected $state;

	public function display($tpl = null)
	{
        $app         = Factory::getApplication();
        $document    = $app->getDocument();
		$form        = $this->get('Form');
		$data        = $this->get('Data');
		$state       = $this->get('State');
		$config      = $this->get('ConfigInfo');
		$jemsettings = $this->get('Data');
		$this->document = $document;

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		// HTMLHelper::_('stylesheet', 'com_jem/colorpicker.css', array(), true);
		$wa = $document->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
		$wa->registerStyle('jem.colorpicker', 'com_jem/colorpicker.css')->useStyle('jem.colorpicker');

		$style = '
		    div.current fieldset.radio input {
		        cursor: pointer;
		    }';
		$document->addStyleDeclaration($style);

		// Check for model errors.
		if ($errors = $this->get('Errors')) {
			$app->enqueueMessage(implode('<br />', $errors), 'error');
			return false;
		}

		// Bind the form to the data.
		if ($form && $data) {
			$form->bind($data);
		}

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			$app->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		// Load Script
		$wa = $app->getDocument()->getWebAssetManager();
		// $document->addScript(Uri::root().'media/com_jem/js/colorpicker.js');
		$wa->useScript('jquery');
		$wa->registerScript('jem.colorpicker_js', 'com_jem/colorpicker.js')->useScript('jem.colorpicker_js');

		// HTMLHelper::_('behavior.modal', 'a.modal');
		// HTMLHelper::_('behavior.tooltip');
		// HTMLHelper::_('behavior.formvalidation');
		// HTMLHelper::_('behavior.framework');
		// HTMLHelper::_('jquery.framework');
		// only admins have access to this view
		if (!JemFactory::getUser()->authorise('core.manage', 'com_jem')) {
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
			$app->redirect('index.php?option=com_jem&view=main');
		}

		// mapping variables
		$this->form        = $form;
		$this->data        = $data;
		$this->state       = $state;
		$this->jemsettings = $jemsettings;
		$this->config      = $config;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @since  1.6
	 */
	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_JEM_SETTINGS_TITLE'), 'settings');
		ToolbarHelper::apply('settings.apply');
		ToolbarHelper::save('settings.save');
		ToolbarHelper::cancel('settings.cancel');

		ToolbarHelper::divider();
		ToolbarHelper::inlinehelp();
		ToolBarHelper::help('settings', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/settings');
	}

	protected function WarningIcon()
	{
		$url = Uri::root();
		// $tip = '<img src="'.$url.'media/system/images/tooltip.png" border="0"  alt="" />';
		$tip = '<span class="icon-info-circle" aria-hidden="true"></span>';

		return $tip;
	}
}
