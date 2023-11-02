<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;
/**
 * View class for the Css-manager screen
 */
class JemViewCssmanager extends JemAdminView
{

	protected $files;

	public function display($tpl = null)
	{
		$this->files = $this->get('Files');
		$this->statusLinenumber = $this->get('StatusLinenumber');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		$app = Factory::getApplication();

		// initialise variables
		$this->document = $app->getDocument();
		$user = JemFactory::getUser();

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = $app->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 */
	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_JEM_CSSMANAGER_TITLE'), 'thememanager');

		// ToolbarHelper::back();
		ToolbarHelper::custom('cssmanager.back', 'back', 'back', Text::_('COM_JEM_ATT_BACK'), false);
		ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolBarHelper::help('editcss', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/control-panel/css-manager');
	}
}
