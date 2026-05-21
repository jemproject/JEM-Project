<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
/**
 * View class: Venue
 */
class JemViewVenue extends JemAdminView
{
    public $form;
    public $item;
    public $state;

    public function display($tpl = null)
    {
        // Initialise variables.
        $this->form     = $this->get('Form');
        $this->item     = $this->get('Item');
        $this->state = $this->get('State');

        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        //initialise variables
        $app = Factory::getApplication();
        $this->document = $app->getDocument();
        $this->settings = JemAdmin::config();
        $globalregistry = JemHelper::globalattribs();
        $task           = $app->input->get('task', '');
        $this->task     = $task;
        $wa = $app->getDocument()->getWebAssetManager();

        // Load css
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
        $wa->registerStyle('jem.attachments', 'com_jem/jem-attachments.css')->useStyle('jem.attachments');
        $wa->registerStyle('jem.geostyle', 'com_jem/geostyle.css')->useStyle('jem.geostyle');

        // Load Scripts
        $wa->useScript('jquery');
        $wa->registerScript('jem.attachments', 'com_jem/attachments.js')->useScript('jem.attachments');

        $language = Factory::getApplication()->getLanguage();
        $language = $language->getTag();
        $language = substr($language, 0,2);

        $key = trim($globalregistry->get('global_googleapi', ''));

        $wa->registerScript('jem.jquery_map', 'https://maps.googleapis.com/maps/api/js?'.(!empty($key) ? 'key='.$key.'&amp;' : '').'sensor=false&libraries=places&language='.$language)->useScript('jem.jquery_map');

        $wa->registerScript('jem.geocomplete', 'com_jem/jquery.geocomplete.js')->useScript('jem.geocomplete');

        $access2 = JemHelper::getAccesslevelOptions();
        $this->access = $access2;

        $this->addToolbar();
        parent::display($tpl);
    }


    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user       = JemFactory::getUser();
        $isNew      = ($this->item->id == 0);
        $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        $canDo      = JemHelperBackend::getActions();

        ToolbarHelper::title($isNew ? Text::_('COM_JEM_ADD_VENUE') : Text::_('COM_JEM_EDIT_VENUE'), 'venuesedit');

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
            ToolbarHelper::apply('venue.apply');
            ToolbarHelper::save('venue.save');
        }
        if (!$checkedOut && $canDo->get('core.create')) {
            ToolbarHelper::save2new('venue.save2new');
        }
        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::save2copy('venue.save2copy');
        }

        if (empty($this->item->id))  {
            ToolbarHelper::cancel('venue.cancel');
        } else {
            ToolbarHelper::cancel('venue.cancel', 'JTOOLBAR_CLOSE');
        }

        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolBarHelper::help('editvenues', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/venues/add-venue');
    }
}
