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
use Joomla\CMS\Factory;
/**
 * View class for the JEM import screen
 *
 * @package JEM
 *
 */

//Load pane behavior
jimport('joomla.html.pane');

class JemViewImport extends JemAdminView
{

	public function display($tpl = null) {
		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		// Get data from the model
		$eventfields = $this->get('EventFields');
		$catfields   = $this->get('CategoryFields');
		$venuefields = $this->get('VenueFields');
		$cateventsfields = $this->get('CateventsFields');

		//assign vars to the template
		$this->eventfields 		= $eventfields;
		$this->catfields 		= $catfields;
		$this->venuefields 		= $venuefields;
		$this->cateventsfields 	= $cateventsfields;

		$this->eventlistVersion = $this->get('EventlistVersion');
		$this->eventlistTables 	= $this->get('EventlistTablesCount');
		$this->jemTables 		= $this->get('JemTablesCount');
		$this->existingJemData 	= $this->get('ExistingJemData');

		$app = Factory::getApplication();
		$jinput = $app->input;
		$progress = new stdClass();
		$progress->step 	= $jinput->get('step', 0, 'INT');
		$progress->current 	= $jinput->get('current', 0, 'INT');
		$progress->total 	= $jinput->get('total', 0, 'INT');
		$progress->table 	= $jinput->get('table', '', 'INT');
		$progress->prefix 	= $jinput->get('prefix', null, 'CMD');
		$progress->copyImages = $jinput->get('copyImages', null, 'INT');
		$progress->copyAttachments = $jinput->get('copyAttachments', null, 'INT');
		$progress->fromJ15 = $jinput->get('fromJ15', null, 'INT');
		$this->progress = $progress;
		$this->attachmentsPossible = !empty($this->eventlistTables['eventlist_attachments']);

		// Do not show default prefix #__ but its replacement value
		$this->prefixToShow = $progress->prefix;
		if (empty($this->prefixToShow) || $this->prefixToShow == "#__") {
			$this->prefixToShow = $app->get('dbprefix');
		}

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_JEM_IMPORT'), 'tableimport');

		ToolbarHelper::back();
		ToolbarHelper::divider();
		ToolbarHelper::inlinehelp();
		ToolBarHelper::help('import', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/control-panel/import-data');
	}
}
?>
