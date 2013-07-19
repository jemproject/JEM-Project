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
 * View class for the JEM event screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewEvent extends JViewLegacy {

	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		if($this->getLayout() == 'addvenue') {
			$this->_displayaddvenue($tpl);
			return;
		}

		//Load behavior
		jimport('joomla.html.pane');
		JHTML::_('behavior.tooltip');

		//initialise variables
		$editor 	= JFactory::getEditor();
		$db 		= JFactory::getDBO();
		$document	= JFactory::getDocument();
		$user 		= JFactory::getUser();
		$jemsettings = JEMAdmin::config();
		/*$acl		= JFactory::getACL();*/

		$nullDate 	= $db->getNullDate();

		//get vars
		$cid		= JRequest::getVar( 'cid' );
		$task		= JRequest::getVar('task');
		//$url 		= $app->isAdmin() ? $app->getSiteURL() : JURI::base();
		$url 		= JURI::root();

		//add the custom stylesheet and the javascript
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');
		$document->addScript($url.'media/com_jem/js/eventscreen.js' );
		$document->addScript($url.'media/com_jem/js/attachments.js' );
		$document->addScript($url.'media/com_jem/js/seo.js');
		$document->addScript($url.'media/com_jem/js/recurrence.js');
		// include the unlimited script
		$document->addScript($url.'media/com_jem/js/unlimited.js');

		//get data from model
		$model		= $this->getModel();
		$row		= $this->get( 'Data' );
		$categories = JEMCategories::getCategoriesTree(1);
		$selectedcats = $this->get( 'Catsselected' );

		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->titel.' '.JText::_( 'COM_JEM_EDITED_BY_ANOTHER_ADMIN' ));
				$app->redirect( 'index.php?option=com_jem&view=events' );
			}
		}

		//make data safe
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, 'datdescription' );

		//build selectlists
		$Lists = array();
		$Lists['category'] = JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8"');

		//build venue select js and load the view
		$js = "
		function elSelectVenue(id, venue) {
			document.getElementById('a_id').value = id;
			document.getElementById('a_name').value = venue;
			window.parent.SqueezeBox.close();
		}";


		$linkcsel = 'index.php?option=com_jem&amp;view=contactelement&amp;tmpl=component';

		$linkvsel = 'index.php?option=com_jem&amp;view=venueelement&amp;tmpl=component';
		$linkvadd = 'index.php?option=com_jem&amp;task=event.showaddvenue&amp;tmpl=component';
		$document->addScriptDeclaration($js);

		JHTML::_('behavior.modal', 'a.modal');

		$venueselect = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"a_name\" value=\"$row->venue\" disabled=\"disabled\" /></div>";
		$venueselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECT')."\" href=\"$linkvsel\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECT')."</a></div></div>\n";
		$venueselect .= "\n<input type=\"hidden\" id=\"a_id\" name=\"locid\" value=\"$row->locid\" />";
		$venueselect .= "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"window.open('$linkvadd', 'popup', 'width=750,height=400,scrollbars=yes,toolbar=no,status=no,resizable=yes,menubar=no,location=no,directories=no,top=10,left=10')\" value=\"".JText::_('COM_JEM_ADD')."\" />";
		$venueselect .= "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"elSelectVenue(0, '".JText::_('COM_JEM_NO_VENUE')."' );\" value=\"".JText::_('COM_JEM_NO_VENUE')."\" onblur=\"seo_switch()\" />";

		// build venue select js and load the view
		$js = "
		function elSelectContact(id, contactid) {
			document.getElementById('a_id2').value = id;
			document.getElementById('a_name2').value = contactid;
			window.parent.SqueezeBox.close();
		}";

		$document->addScriptDeclaration($js);

		$contactselect = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"a_name2\" value=\"$row->contactname\" disabled=\"disabled\" /></div>";
		$contactselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECT')."\" href=\"$linkcsel\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECT')."</a></div></div>\n";
		$contactselect .= "\n<input type=\"hidden\" id=\"a_id2\" name=\"contactid\" value=\"$row->contactid\" />";
		$contactselect .= "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"elSelectContact(0, '".JText::_('COM_JEM_NO_CONTACT')."' );\" value=\"".JText::_('COM_JEM_NO_CONTACT')."\" onblur=\"seo_switch()\" />";

		//build image select js and load the view
		$js = "
		function elSelectImage(image, imagename) {
			document.getElementById('a_image').value = image;
			document.getElementById('a_imagename').value = imagename;
			document.getElementById('imagelib').src = '../images/jem/events/' + image;
			window.parent.SqueezeBox.close();
		}";

		$link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task=eventimg&amp;tmpl=component';
		$link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task=selecteventimg&amp;tmpl=component';
		$document->addScriptDeclaration($js);
		$imageselect = "\n<input style=\"background: #ffffff;\" type=\"text\" id=\"a_imagename\" value=\"$row->datimage\" disabled=\"disabled\" onchange=\"javascript:if (document.forms[0].a_imagename.value!='') {document.imagelib.src='../images/jem/events/' + document.forms[0].a_imagename.value} else {document.imagelib.src='../images/blank.png'}\"; /><br />";

		$imageselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_UPLOAD')."\" href=\"$link\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_UPLOAD')."</a></div></div>\n";
		$imageselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECTIMAGE')."\" href=\"$link2\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECTIMAGE')."</a></div></div>\n";

		$imageselect .= "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"elSelectImage('', '".JText::_('COM_JEM_SELECTIMAGE')."' );\" value=\"".JText::_('COM_JEM_RESET')."\" />";
		$imageselect .= "\n<input type=\"hidden\" id=\"a_image\" name=\"datimage\" value=\"$row->datimage\" />";

		$js = "
		function elResetHits(id) {
			document.getElementById('a_hits').value = id;
		}";

		$document->addScriptDeclaration($js);

		$resethits = "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"elResetHits(0, '".JText::_('COM_JEM_NO_HITS')."' );\" value=\"".JText::_('COM_JEM_NO_HITS')."\" onblur=\"seo_switch()\" />";

		// recurrence type
		$rec_type = array();
		$rec_type[] = JHTML::_('select.option', 0, JText::_ ( 'COM_JEM_NOTHING' ));
		$rec_type[] = JHTML::_('select.option', 1, JText::_ ( 'COM_JEM_DAYLY' ));
		$rec_type[] = JHTML::_('select.option', 2, JText::_ ( 'COM_JEM_WEEKLY' ));
		$rec_type[] = JHTML::_('select.option', 3, JText::_ ( 'COM_JEM_MONTHLY' ));
		$rec_type[] = JHTML::_('select.option', 4, JText::_ ( 'COM_JEM_WEEKDAY' ));
		$Lists['recurrence_type'] = JHTML::_('select.genericlist', $rec_type, 'recurrence_type', '', 'value', 'text', $row->recurrence_type);

		//assign vars to the template
		$this->Lists 		= $Lists;
		$this->row 			= $row;
		$this->imageselect 	= $imageselect;
		$this->venueselect 	= $venueselect;
		$this->resethits = $resethits;
		$this->contactselect 	= $contactselect;
		$this->editor 		= $editor;
		$this->task 		= $task;
		$this->nullDate 	= $nullDate;
		$this->jemsettings 	= $jemsettings;
		$access2 = JEMHelper::getAccesslevelOptions();
		$this->access 		= $access2;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Creates the output for the add venue screen
	 *
	 *
	 */
	public function _displayaddvenue($tpl)
	{
		//initialise variables
		$editor 	= JFactory::getEditor();
		$document	= JFactory::getDocument();
		$uri 		= JFactory::getURI();
		$jemsettings = JEMAdmin::config();

		//add css and js to document
		JHTML::_('behavior.modal', 'a.modal');
		JHTML::_('behavior.tooltip');

		//Build the image select functionality
		$js = "
		function elSelectImage(image, imagename) {
			document.getElementById('a_image').value = image;
			document.getElementById('a_imagename').value = imagename;
			window.parent.SqueezeBox.close();
		}";

		$link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task=venueimg&amp;tmpl=component';
		$link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task=selectvenueimg&amp;tmpl=component';
		$document->addScriptDeclaration($js);
		$imageselect = "\n<input style=\"background: #ffffff;\" type=\"text\" id=\"a_imagename\" value=\"".JText::_('COM_JEM_SELECTIMAGE')."\" disabled=\"disabled\" onchange=\"javascript:if (document.forms[0].a_imagename.value!='') {document.imagelib.src='../images/jem/venues/' + document.forms[0].a_imagename.value} else {document.imagelib.src='../images/blank.png'}\"; /><br />";

		$imageselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_UPLOAD')."\" href=\"$link\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_UPLOAD')."</a></div></div>\n";
		$imageselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECTIMAGE')."\" href=\"$link2\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECTIMAGE')."</a></div></div>\n";

		$imageselect .= "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"elSelectImage('', '".JText::_('COM_JEM_SELECTIMAGE')."' );\" value=\"".JText::_('COM_JEM_RESET')."\" />";
		$imageselect .= "\n<input type=\"hidden\" id=\"a_image\" name=\"locimage\" value=\"".JText::_('COM_JEM_SELECTIMAGE')."\" />";

		$countries = array();
		$countries[] = JHTML::_('select.option', '', JText::_('COM_JEM_SELECT_COUNTRY'));
		$countries = array_merge($countries, JEMHelper::getCountryOptions());
		$selectedCountry = $jemsettings->defaultCountry;
		$lists['countries'] = JHTML::_('select.genericlist', $countries, 'country', 'class="inputbox"', 'value', 'text', $selectedCountry);
		unset($countries);

		//set published
		$published = 1;

		//assign to template
		$this->editor 		= $editor;
		$this->imageselect 	= $imageselect;
		$this->published 	= $published;

		$uri2 = $uri->toString();
		$this->request_url 	= $uri2;
		$this->jemsettings 	= $jemsettings;
		$this->lists 		= $lists;

		parent::display($tpl);
	}

	/*
	 * Add Toolbar
	*/
	protected function addToolbar()
	{

		
		/* variables */
		$app = JFactory::getApplication();
		$input = $app->input;
		$input->set('hidemainmenu', 1);

		$user		= JFactory::getUser();
		$userId		= $user->get('id');

		
		$checkedOut	= !($this->row->checked_out == 0 || $this->row->checked_out == $userId);
		$canDo		= JEMHelperBackend::getActions(0);
		
		/*$isNew		= ($this->row->id == 0);*/
		/*JToolBarHelper::title($isNew ? JText::_('COM_JEM_ADD_EVENT') : JText::_('COM_JEM_EDIT_EVENT'), 'eventedit');*/
		
		
		//get vars
		$cid		= JRequest::getVar( 'cid' );
		$task		= JRequest::getVar('task');

		//build toolbar
		if ($task == 'copy') {
			JToolBarHelper::title( JText::_( 'COM_JEM_COPY_EVENT'), 'eventedit');
		} elseif ( $cid ) {
			JToolBarHelper::title( JText::_( 'COM_JEM_EDIT_EVENT' ), 'eventedit' );
		} else {
			JToolBarHelper::title( JText::_( 'COM_JEM_ADD_EVENT' ), 'eventedit' );
		}
		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) 
		{
		JToolBarHelper::apply('event.apply');
		JToolBarHelper::spacer();
		JToolBarHelper::save('event.save');
		JToolBarHelper::spacer();
		}
		
	
		JToolBarHelper::cancel('event.cancel', 'JTOOLBAR_CLOSE');
		
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'editevents', true );
	}
}
?>