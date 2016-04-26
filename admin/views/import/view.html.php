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
 * View class for the JEM import screen
 *
 * @package JEM
 *
 */
class JemViewImport extends JemAdminView
{

	public function display($tpl = null) {
		//Load pane behavior
		jimport('joomla.html.pane');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Load script
		JHtml::_('behavior.framework');

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

		$app = JFactory::getApplication();
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
			$this->prefixToShow = $app->getCfg('dbprefix');
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
		JToolBarHelper::title(JText::_('COM_JEM_IMPORT'), 'tableimport');

		JToolBarHelper::back();
		JToolBarHelper::divider();
		JToolBarHelper::help('import', true);
	}
}
?>