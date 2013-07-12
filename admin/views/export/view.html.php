<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;



/**
 * View class(based on the import screen)
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewExport extends JViewLegacy {

	public function display($tpl = null) {
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= JFactory::getDocument();
		$user 		= JFactory::getUser();

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);

	}

	/*
	 * Add Toolbar
	*/

	protected function addToolbar()
	{

		require_once JPATH_COMPONENT . '/helpers/helper.php';

		//build toolbar
		JToolBarHelper::back();
		JToolBarHelper::title(JText::_('COM_JEM_EXPORT'), 'tableexport');

	}



} // end of class
?>
