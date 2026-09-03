<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Event View
 */
class JemViewEvent extends JemAdminView
{
    public $form;
    public $item;
    public $state;

    public function display($tpl = null)
    {
        // Initialise variables.
        $this->item  = $this->get('Item');

        $isNew = empty($this->item->id);
        $allowed = $isNew
            ? JemHelperBackend::can('event', 'create')
            : JemHelperBackend::can('event', 'edit', $this->item);

        if (!$allowed) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->form  = $this->get('Form');
        $this->state = $this->get('State');

        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        //initialise variables
        $jemsettings    = JemHelper::config();
        $app            = Factory::getApplication();
        $this->document = $app->getDocument();
        $user           = JemFactory::getUser();
        $this->settings = JemAdmin::config();
        $task           = $app->input->get('task', '');
        $this->task     = $task;
        $uri            = Uri::getInstance();
        $url            = $uri->root();

        // Load css
        $wa = $app->getDocument()->getWebAssetManager();
        JemHelper::loadCss('jem-attachments');

        // Load scripts
        $wa->useScript('jquery');
        $wa->registerScript('jem.attachments', 'com_jem/attachments.js')->useScript('jem.attachments');
        $wa->registerScript('jem.recurrence', 'com_jem/recurrence.js')->useScript('jem.recurrence');
        $wa->registerScript('jem.unlimited', 'com_jem/unlimited.js')->useScript('jem.unlimited');
        $wa->registerScript('jem.seo', 'com_jem/seo.js')->useScript('jem.seo');

        $access2           = JemHelper::getAccesslevelOptions();
        $this->access      = $access2;
        $this->jemsettings = $jemsettings;
        $this->addToolbar();
        parent::display($tpl);
    }


    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user         = JemFactory::getUser();
        $isNew        = ($this->item->id == 0);
        $checkedOut   = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        $canEdit      = !$isNew && JemHelperBackend::can('event', 'edit', $this->item);
        $canCreate    = JemHelperBackend::can('event', 'create');
        $canSave      = !$checkedOut && (($isNew && $canCreate) || $canEdit);
        $canSave2New  = !$checkedOut && $canCreate;
        $canSave2Copy = !$checkedOut && !$isNew && $canEdit && $canCreate;
        $cancelText   = $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE';

        ToolBarHelper::title($isNew ? Text::_('COM_JEM_ADD_EVENT') : Text::_('COM_JEM_EDIT_EVENT'), 'eventedit');

        if ($canSave) {
            ToolBarHelper::apply('event.apply');

            $toolbar = Toolbar::getInstance('toolbar');
            $saveGroup = $toolbar->dropdownButton('save-group')
                ->toggleSplit(true)
                ->icon('icon-save')
                ->buttonClass('btn btn-success')
                ->listCheck(false);

            $childBar = $saveGroup->getChildToolbar();
            $childBar->save('event.save');

            if ($canSave2New) {
                $childBar->save2new('event.save2new');
            }

            if ($canSave2Copy) {
                $childBar->save2copy('event.save2copy');
            }
        }

        ToolBarHelper::cancel('event.cancel', $cancelText);

        ToolBarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolBarHelper::help('editevents', true, 'https://www.joomlaeventmanager.net/documentation/backend/events/add-event');
    }
}
