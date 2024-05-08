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
use Joomla\CMS\Uri\Uri;


/**
 * View class: Venues
 */

 class JemViewVenues extends JemAdminView
{
	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		$user     = JemFactory::getUser();
        $app      = Factory::getApplication();
        $document = $app->getDocument();
        $uri      = Uri::getInstance();
		$url      = $uri->root();
		$settings = JemHelper::globalattribs();

		// Initialise variables.
		$this->items      = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state      = $this->get('State');
		$this->settings   = $settings;

		$params = $this->state->get('params');

		// highlighter
		$highlighter = $settings->get('highlight','0');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			$app->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = $app->getDocument()->getWebAssetManager();
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		// Add Scripts
		$document->addScript('https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');

		if ($highlighter) {
			$document->addScript($url.'media/com_jem/js/highlighter.js');
			$style = '.red, .red a { color:red; }';
			$document->addStyleDeclaration($style);
		}

		// add filter selection for the search
		$filters = array();
		$filters[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_VENUE'));
		$filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_CITY'));
		$filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_STATE'));
		$filters[] = HTMLHelper::_('select.option', '4', Text::_('COM_JEM_COUNTRY'));
		$filters[] = HTMLHelper::_('select.option', '5', Text::_('JALL'));
		$lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox form-select'), 'value', 'text', $this->state->get('filter_type'));

		//assign data to template
		$this->lists = $lists;
		$this->user  = $user;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_JEM_VENUES'), 'venues');

		$canDo = JemHelperBackend::getActions(0);

		/* create */
		if (($canDo->get('core.create'))) {
			ToolbarHelper::addNew('venue.add');
		}

		/* edit */
		if (($canDo->get('core.edit'))) {
			ToolbarHelper::editList('venue.edit');
			ToolbarHelper::divider();
		}

		/* state */
		if ($canDo->get('core.edit.state')) {
			if ($this->state->get('filter.state') != 2) {
				ToolbarHelper::publishList('venues.publish');
				ToolbarHelper::unpublishList('venues.unpublish');
				ToolbarHelper::divider();
			}
		}

		if ($canDo->get('core.edit.state')) {
			ToolbarHelper::checkin('venues.checkin');
		}

		/* delete-trash */
		if ($canDo->get('core.delete')) {
			ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'venues.remove', 'JACTION_DELETE');
		}

		ToolbarHelper::divider();
        ToolBarHelper::help('listvenues', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/venues');
	}
}
