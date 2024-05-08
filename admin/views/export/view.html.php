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
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class Export
 *
 * @package JEM
 *
 */
class JemViewExport extends JemAdminView
{

	public function display($tpl = null) {
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$app = Factory::getApplication();
		$document = $app->getDocument();

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = $app->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
		//Cause of group limits we can't use class here to build the categories tree
		$categories = $this->get('Categories');

		//build selectlists
		$categories = JEMCategories::buildcatselect($categories, 'cid[]', null, 0, 'multiple="multiple" size="8" class="inputbox form-control"');

		$this->categories		= $categories;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_JEM_EXPORT'), 'tableexport');

		ToolbarHelper::back();
		ToolbarHelper::divider();
		ToolbarHelper::inlinehelp();
		ToolBarHelper::help('export', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/control-panel/export-data');
	}
}
?>
