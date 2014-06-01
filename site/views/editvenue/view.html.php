<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 * Editvenue-View
 */
class JemViewEditvenue extends JViewLegacy
{
	protected $form;
	protected $item;
	protected $return_page;
	protected $state;

	/**
	 * Editvenue-View
	 */
	public function display($tpl = null)
	{
		// Initialise variables.
		$jemsettings = JemHelper::config();
		$app         = JFactory::getApplication();
		$user        = JFactory::getUser();
		$document    = JFactory::getDocument();
		$model       = $this->getModel();
		$menu        = $app->getMenu();
		$menuitem    = $menu->getActive();
		$pathway     = $app->getPathway();
		$url         = JURI::root();

		$language    = JFactory::getLanguage();
		$language    = $language->getTag();
		$language    = substr($language, 0,2);

		// Get model data.
		$this->state  = $this->get('State');
		$this->item   = $this->get('Item');
		$this->params = $this->state->get('params');

		// Create a shortcut for $item and params.
		$item = $this->item;
		$params = $this->params;

		$this->form = $this->get('Form');
		$this->return_page = $this->get('ReturnPage');

		// check for guest
		if (!$user || $user->id == 0) {
			$app->enqueueMessage(JText::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		if (empty($this->item->id)) {
			// Check if the user has access to the form
			$maintainer = JemUser::venuegroups('add');
			$delloclink = JemUser::validate_user($jemsettings->locdelrec, $jemsettings->deliverlocsyes);

			if ($maintainer || $delloclink) {
				$dellink = true;
			} else {
				$dellink = false;
			}

			$authorised = $user->authorise('core.create','com_jem') || $dellink;
		} else {
			// Check if user can edit
			$maintainer = JemUser::venuegroups('edit');
			$genaccess  = JemUser::editaccess($jemsettings->venueowner, $this->item->created_by, $jemsettings->venueeditrec, $jemsettings->venueedit);

			if ($maintainer || $genaccess) {
				$edit = true;
			} else {
				$edit = false;
			}

			$authorised = $this->item->params->get('access-edit') || $edit;
		}

		if ($authorised !== true) {
			$app->enqueueMessage(JText::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
		                                && $menuitem->query['view']   == 'editvenue'
		                                && 0 == $item->id); // menu item is always for new venues

		$title = ($item->id == 0) ? JText::_('COM_JEM_EDITVENUE_VENUE_ADD')
		                          : JText::sprintf('COM_JEM_EDITVENUE_VENUE_EDIT', $item->venue);

		if ($useMenuItemParams) {
			$pagetitle = $menuitem->title ? $menuitem->title : $title;
			$params->def('page_title', $pagetitle);
			$params->def('page_heading', $pagetitle);
			$pathway->setItemName(1, $pagetitle);

			// Load layout from menu item if one is set else from venue if there is one set
			if (isset($menuitem->query['layout'])) {
				$this->setLayout($menuitem->query['layout']);
			} elseif ($layout = $item->params->get('venue_layout')) {
				$this->setLayout($layout);
			}

			$item->params->merge($params);
		} else {
			$pagetitle = $title;
			$params->set('page_title', $pagetitle);
			$params->set('page_heading', $pagetitle);
			$params->set('show_page_heading', 1); // ensure page heading is shown
			$params->set('introtext', ''); // there is no introtext in that case
			$params->set('showintrotext', 0);
			$pathway->addItem($pagetitle, ''); // link not required here so '' is ok

			// Check for alternative layouts (since we are not in an edit-venue menu item)
			// Load layout from venue if one is set
			if ($layout = $item->params->get('venue_layout')) {
				$this->setLayout($layout);
			}

			$temp = clone($params);
			$temp->merge($item->params);
			$item->params = $temp;
		}

		if (!empty($this->item) && isset($this->item->id)) {
			// $this->item->images = json_decode($this->item->images);
			// $this->item->urls = json_decode($this->item->urls);

			$tmp = new stdClass();
			// $tmp->images = $this->item->images;
			// $tmp->urls = $this->item->urls;
			$this->form->bind($tmp);
		}

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseWarning(500, implode("\n", $errors));
			return false;
		}

		JHtml::_('behavior.framework');
		JHtml::_('behavior.formvalidation');
		JHtml::_('behavior.tooltip');

		$access2 		= JemHelper::getAccesslevelOptions();
		$this->access	= $access2;

		// Load css
		JemHelper::loadCss('geostyle');
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		// Load script
		JHtml::_('script', 'com_jem/attachments.js', false, true);
		JHtml::_('script', 'com_jem/other.js', false, true);
		$document->addScript('http://maps.googleapis.com/maps/api/js?sensor=false&amp;libraries=places&language='.$language);

		// Noconflict
		$document->addCustomTag( '<script type="text/javascript">jQuery.noConflict();</script>' );

		// JQuery scripts
		$document->addScript('http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');
		JHtml::_('script', 'com_jem/jquery.geocomplete.js', false, true);

		$this->pageclass_sfx	= htmlspecialchars($item->params->get('pageclass_sfx'));
		$this->jemsettings		= $jemsettings;
		$this->limage 			= JemImage::flyercreator($this->item->locimage, 'venue');
		$this->infoimage		= JHtml::_('image', 'com_jem/icon-16-hint.png', JText::_('COM_JEM_NOTES'), NULL, true);

		$this->user = $user;

		$this->_prepareDocument();
		parent::display($tpl);
	}


	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		$app = JFactory::getApplication();

		$title = $this->params->get('page_title');
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}
		$this->document->setTitle($title);

		// TODO: Is it useful to have meta data in an edit view?
		//       Also shouldn't be "robots" set to "noindex, nofollow"?
		if ($this->params->get('menu-meta_description')) {
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->params->get('menu-meta_keywords')) {
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots')) {
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}
	}
}
?>
