<?php
/**
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 * View: Import
 */
class JEMViewImport extends JViewLegacy
{

    public function display($tpl = null)
	{

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Get data from the model
		$eventfields 				= $this->get('EventFields');
		$catfields   				= $this->get('CategoryFields');
		$venuefields 				= $this->get('VenueFields');
		$cateventsfields 			= $this->get('CateventsFields');
		$model 						= $this->getModel();

		$this->eventfields 			= $eventfields;
		$this->catfields 			= $catfields;
		$this->venuefields 			= $venuefields;
		$this->cateventsfields 		= $cateventsfields;
		$this->eventlistVersion		= $this->get('EventlistVersion');
		$this->eventlistTables		= $model->eventlistTables($this->get('EventlistVersion'));
		$this->jemTables 			= $this->get('JemTablesCount');
		$this->existingJemData 		= $this->get('ExistingJemData');

		$jinput = JFactory::getApplication()->input;
		$progress = new stdClass();
		$progress->step 				= $jinput->getInt('step', 0);
		$progress->current 				= $jinput->get->getInt('current', 0);
		$progress->total 				= $jinput->get->getInt('total', 0);
		$progress->table 				= $jinput->get->getInt('table', '');
		$progress->prefix 				= $jinput->getCmd('prefix', '');
		$progress->copyImages			= $jinput->getInt('copyImages', 0);
		$progress->copyAttachments		= $jinput->getInt('copyAttachments', 0);

		$this->progress = $progress;

		// Do not show default prefix #__ but its replacement value
		$this->prefixToShow = $progress->prefix;
		if($this->prefixToShow == "#__" || $this->prefixToShow == "") {
			$app = JFactory::getApplication();
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
		JToolBarHelper::custom('import.back','back','back',JText::_('JTOOLBAR_BACK'),false);
		JToolBarHelper::divider();
		JToolBarHelper::help('import', true);
	}

	function WarningIcon()
	{
		$tip = JHtml::_('image', 'system/tooltip.png', null, null, true);

		return $tip;
	}
}
