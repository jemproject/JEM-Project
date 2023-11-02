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
use Joomla\CMS\Uri\Uri;


/**
 * Events-View
 */

class JemViewEvents extends JemAdminView
{
	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		$app            = Factory::getApplication();
		$document       = $app->getDocument();
		$user 			= JemFactory::getUser();
		$settings 		= JemHelper::globalattribs();
		$jemsettings 	= JemAdmin::config();
		$uri            = Uri::getInstance();
		$url 			= $uri->root();

		// Initialise variables.
		$this->items		= $this->get('Items');
		$this->pagination	= $this->get('Pagination');
		$this->state		= $this->get('State');

		// Retrieving params
		$params = $this->state->get('params');

		// highlighter
		$highlighter = $settings->get('highlight','0');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		// Load Scripts
		$this->document->addScript('https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');

		if ($highlighter) {
			$this->document->addScript($url.'media/com_jem/js/highlighter.js');
			$style = '
			    .red, .red a {
			    color:red;}
			    ';
			$this->document->addStyleDeclaration($style);
		}

		//add style to description of the tooltip (hastip)
		// HTMLHelper::_('behavior.tooltip');

		// add filter selection for the search
		$filters = array();
		$filters[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_EVENT_TITLE'));
		$filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE'));
		$filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY'));
		$filters[] = HTMLHelper::_('select.option', '4', Text::_('COM_JEM_CATEGORY'));
		$filters[] = HTMLHelper::_('select.option', '5', Text::_('COM_JEM_STATE'));
		$filters[] = HTMLHelper::_('select.option', '6', Text::_('COM_JEM_COUNTRY'));
		$filters[] = HTMLHelper::_('select.option', '7', Text::_('JALL'));
		$lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox form-select m-0','onChange'=>"this.form.submit()"), 'value', 'text', $this->state->get('filter_type'));

		//assign data to template
		$this->lists		= $lists;
		$this->user			= $user;
		$this->jemsettings  = $jemsettings;
		$this->settings		= $settings;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		ToolBarHelper::title(Text::_('COM_JEM_EVENTS'), 'events');

		/* retrieving the allowed actions for the user */
		$canDo = JemHelperBackend::getActions(0);

		/* create */
		if (($canDo->get('core.create'))) {
			ToolBarHelper::addNew('event.add');
		}

		/* edit */
		if (($canDo->get('core.edit'))) {
			ToolBarHelper::editList('event.edit');
			ToolBarHelper::divider();
		}

		/* state */
		if ($canDo->get('core.edit.state')) {
			if ($this->state->get('filter_state') != 2) {
				ToolBarHelper::publishList('events.publish', 'JTOOLBAR_PUBLISH', true);
				ToolBarHelper::unpublishList('events.unpublish', 'JTOOLBAR_UNPUBLISH', true);
				ToolBarHelper::custom('events.featured', 'featured.png', 'featured_f2.png', 'JFEATURED', true);
			}

			if ($this->state->get('filter_state') != -1) {
				ToolBarHelper::divider();
				if ($this->state->get('filter_state') != 2) {
					ToolBarHelper::archiveList('events.archive');
				} elseif ($this->state->get('filter_state') == 2) {
					ToolBarHelper::unarchiveList('events.publish');
				}
			}
		}

		if ($canDo->get('core.edit.state')) {
			ToolBarHelper::checkin('events.checkin');
		}

		if ($this->state->get('filter_state') == -2 && $canDo->get('core.delete')) {
			ToolBarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'events.delete', 'JTOOLBAR_EMPTY_TRASH');
		} elseif ($canDo->get('core.edit.state')) {
			ToolBarHelper::trash('events.trash');
		}

		ToolBarHelper::divider();
		ToolBarHelper::help('listevents', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/events');
	}
}
?>
