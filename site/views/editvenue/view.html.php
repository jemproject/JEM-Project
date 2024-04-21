<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
/**
 * Editvenue-View
 */
class JemViewEditvenue extends JemView
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
		$settings    = JemHelper::globalattribs();
		$app         = Factory::getApplication();
		$user        = JemFactory::getUser();
		$document    = $app->getDocument();
		$model       = $this->getModel();
		$menu        = $app->getMenu();
		$menuitem    = $menu->getActive();
		$pathway     = $app->getPathway();
		$url         = Uri::root();

		$language    = Factory::getApplication()->getLanguage();
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

		// check for data error
		if (empty($item)) {
			$app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
			return false;
		}

		// check for guest
		if (!$user || $user->id == 0) {
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		if (empty($item->id)) {
			// Check if the user has access to the form
			$authorised = $user->can('add', 'venue');
		} else {
			// Check if user can edit
			$authorised = $user->can('edit', 'venue', $item->id, $item->created_by);
		}

		if ($authorised !== true) {
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
		                                && $menuitem->query['view']   == 'editvenue'
		                                && 0 == $item->id); // menu item is always for new venues

		$title = ($item->id == 0) ? Text::_('COM_JEM_EDITVENUE_VENUE_ADD')
		                          : Text::sprintf('COM_JEM_EDITVENUE_VENUE_EDIT', $item->venue);

		if ($useMenuItemParams) {
			$pagetitle = $menuitem->title ? $menuitem->title : $title;
			$params->def('page_title', $pagetitle);
			$params->def('page_heading', $pagetitle);
      $pathwayKeys = array_keys($pathway->getPathway());
      $lastPathwayEntryIndex = end($pathwayKeys);
      $pathway->setItemName($lastPathwayEntryIndex, $menuitem->title);
      //$pathway->setItemName(1, $menuitem->title);

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

		$publisher = $user->can('publish', 'venue', $item->id, $item->created_by);

		if (!empty($this->item) && isset($this->item->id)) {
			// $this->item->images = json_decode($this->item->images);
			// $this->item->urls = json_decode($this->item->urls);

			$tmp = new stdClass();
			// $tmp->images = $this->item->images;
			// $tmp->urls = $this->item->urls;
			$this->form->bind($tmp);
		}

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'warning');
			return false;
		}

		// HTMLHelper::_('behavior.framework');
		// HTMLHelper::_('behavior.formvalidation');
		// HTMLHelper::_('behavior.tooltip');

		$access2      = JemHelper::getAccesslevelOptions(true);
		$this->access = $access2;

		// Load css
		JemHelper::loadCss('geostyle');
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		// Load script
		
		// HTMLHelper::_('script', 'com_jem/attachments.js', false, true);
		// HTMLHelper::_('script', 'com_jem/other.js', false, true);
		$document->addScript($url.'media/com_jem/js/attachments.js');
		$document->addScript($url.'media/com_jem/js/other.js');
		$key = trim($settings->get('global_googleapi', ''));
		// $document->addScript('https://maps.googleapis.com/maps/api/js?'.(!empty($key) ? 'key='.$key.'&amp;' : '').'sensor=false&libraries=places&language='.$language);

		// Noconflict
		$document->addCustomTag( '<script type="text/javascript">jQuery.noConflict();</script>' );

		// JQuery scripts
		$document->addScript('https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');

		// HTMLHelper::_('script', 'com_jem/jquery.geocomplete.js', false, true);
		// $document->addScript($url.'media/com_jem/js/jquery.geocomplete.js');
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerScript('jem.jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js')->useScript('jem.jquery');
		$wa->registerScript('jem.jquery_map', 'https://maps.googleapis.com/maps/api/js?'.(!empty($key) ? 'key='.$key.'&amp;' : '').'sensor=false&libraries=places&language='.$language)->useScript('jem.jquery_map');
		$wa->registerScript('jem.geocomplete', 'com_jem/jquery.geocomplete.js')->useScript('jem.geocomplete');
		// No permissions required/useful on this view
		$permissions = new stdClass();

		$pageclass_sfx 		 = $item->params->get('pageclass_sfx');
		$this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
		$this->jemsettings   = $jemsettings;
		$this->settings      = $settings;
		$this->permissions   = $permissions;
		$this->limage        = JemImage::flyercreator($this->item->locimage, 'venue');
		$this->infoimage     = HTMLHelper::_('image', 'com_jem/icon-16-hint.png', Text::_('COM_JEM_NOTES'), NULL, true);
		$this->user          = $user;

		if (!$publisher) {
			$this->form->setFieldAttribute('published', 'default', 0);
			$this->form->setFieldAttribute('published', 'readonly', 'true');
		}

		// configure image field: show max. file size, and possibly mark field as required
		$tip = Text::_('COM_JEM_UPLOAD_IMAGE');
		if ((int)$jemsettings->sizelimit > 0) {
			$tip .= ' <br/>' . Text::sprintf('COM_JEM_MAX_FILE_SIZE_1', (int)$jemsettings->sizelimit);
		}
		$this->form->setFieldAttribute('userfile', 'description', $tip);
		if ($jemsettings->imageenabled == 2) {
			$this->form->setFieldAttribute('userfile', 'required', 'true');
		}

		$this->_prepareDocument();
		parent::display($tpl);
	}


	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		$app = Factory::getApplication();

		$title = $this->params->get('page_title');
		if ($app->get('sitename_pagetitles', 0) == 1) {
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
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
