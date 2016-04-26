<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


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
		$document	= JFactory::getDocument();

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		//Cause of group limits we can't use class here to build the categories tree
		$categories = $this->get('Categories');

		//build selectlists
		$categories = JEMCategories::buildcatselect($categories, 'cid[]', null, 0, 'multiple="multiple" size="8 class="inputbox"');

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
		JToolBarHelper::title(JText::_('COM_JEM_EXPORT'), 'tableexport');

		JToolBarHelper::back();
		JToolBarHelper::divider();
		JToolBarHelper::help('export', true);
	}
}
?>
