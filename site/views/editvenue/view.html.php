<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * HTML View class for the Editevents View
 *
 * @package JEM
 *
 */
class JEMViewEditvenue extends JViewLegacy
{
	/**
	 * Creates the output for venue submissions
	 *
	 *
	 * @param int $tpl
	 */
	function display( $tpl=null )
	{
		$this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl');

		$app = JFactory::getApplication();;

		$user = JFactory::getUser();

		//redirect if not logged in
		if ( !$user->get('id') ) {
			$app->enqueueMessage(JText::_('COM_JEM_NEED_LOGGED_IN'), 'error');
			return false;
		}

		$editor 	= JFactory::getEditor();
		$doc 		= JFactory::getDocument();
		$jemsettings = JEMHelper::config();

		// Get requests
		$id				= JRequest::getInt('id');

		//Get Data from the model
		$row 		= $this->Get('Venue');
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, 'locdescription' );

		JHtml::_('behavior.framework');
		JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');

		//add css file
		$doc->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$doc->addStyleSheet(JURI::root().'media/com_jem/css/geostyle.css');
		$doc->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		$doc->addScript('media/com_jem/js/attachments.js' );
		$doc->addScript('http://maps.googleapis.com/maps/api/js?sensor=false&amp;libraries=places');


		// Noconflict
		$doc->addCustomTag( '<script type="text/javascript">jQuery.noConflict();</script>' );

		// JQuery scripts
		$doc->addScript('http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');
		$doc->addScript(JURI::root().'media/com_jem/js/jquery.geocomplete.js');

		// Get the menu object of the active menu item
		$menu		= $app->getMenu();
		$item		= $menu->getActive();
		$params 	= $app->getParams('com_jem');

		$id ? $title = JText::_( 'COM_JEM_EDIT_VENUE' ) : $title = JText::_( 'COM_JEM_ADD_VENUE' );

		//pathway
		$pathway 	= $app->getPathWay();
		if($item) $pathway->setItemName(1, $item->title);
		$pathway->addItem($title, '');

		//Set Title
		$doc->setTitle($title);

		//editor user
		$editoruser = JEMUser::editoruser();

		//transform <br /> and <br> back to \r\n for non editorusers
		if (!$editoruser) {
			$row->locdescription = JEMHelper::br2break($row->locdescription);
		}

		//Get image
		$limage = JEMImage::flyercreator($row->locimage, 'venue');

		//Set the info image
		$infoimage = JHtml::_('image', 'com_jem/icon-16-hint.png', JText::_( 'COM_JEM_NOTES' ) );

		// country list
		$countries = array();
		$countries[] = JHTML::_('select.option', '', JText::_('COM_JEM_SELECT_COUNTRY'));
		$countries = array_merge($countries, JEMHelper::getCountryOptions());
		$selectedCountry = ($row->id) ? $row->country : $jemsettings->defaultCountry;
		$lists['countries'] = JHTML::_('select.genericlist', $countries, 'country', 'class="inputbox"', 'value', 'text', $selectedCountry);
		unset($countries);

		$this->row				= $row;
		$this->editor			= $editor;
		$this->editoruser		= $editoruser;
		$this->limage			= $limage;
		$this->infoimage		= $infoimage;
		$this->jemsettings		= $jemsettings;
		$this->item				= $item;
		$this->params			= $params;
		$this->lists			= $lists;
		$this->title			= $title;

		$mode2 = JRequest::getVar('mode', '');
		$this->mode				= $mode2;

		$access2 = JEMHelper::getAccesslevelOptions();
		$this->access			= $access2;

		parent::display($tpl);
	}
}
?>