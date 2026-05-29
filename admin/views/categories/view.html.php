<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

/**
 *  View class for the JEM Categories screen
 */
class JemViewCategories extends JemAdminView
{
    protected $items;
    protected $pagination;
    protected $state;

    /**
     * Display the view
     */
    public function display($tpl = null)
    {
        $this->state        = $this->get('State');
        $this->items        = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->app          = Factory::getApplication();
        $this->document     = $this->app->getDocument();
        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            $this->app->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        // Load css
        $wa = $this->app->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

        // Preprocess the list of items to find ordering divisions.
        foreach ($this->items as &$item) {
            $this->ordering[$item->parent_id][] = $item->id;
        }

        // Levels filter.
        $options    = array();
        $options[]    = HTMLHelper::_('select.option', '1', Text::_('J1'));
        $options[]    = HTMLHelper::_('select.option', '2', Text::_('J2'));
        $options[]    = HTMLHelper::_('select.option', '3', Text::_('J3'));
        $options[]    = HTMLHelper::_('select.option', '4', Text::_('J4'));
        $options[]    = HTMLHelper::_('select.option', '5', Text::_('J5'));
        $options[]    = HTMLHelper::_('select.option', '6', Text::_('J6'));
        $options[]    = HTMLHelper::_('select.option', '7', Text::_('J7'));
        $options[]    = HTMLHelper::_('select.option', '8', Text::_('J8'));
        $options[]    = HTMLHelper::_('select.option', '9', Text::_('J9'));
        $options[]    = HTMLHelper::_('select.option', '10', Text::_('J10'));

        $this->f_levels = $options;

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar()
    {

        // Initialise variables.
        $canDo        = null;
        $user        = JemFactory::getUser();

        // Get the results for each action.
        $canDo = JemHelperBackend::getActions(0);
        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $canDelete = $canDo->get('core.delete');
        $showActionDropdown = $canChangeState || ($this->state->get('filter.published') == -2 && $canDelete);

        ToolbarHelper::title(Text::_('COM_JEM_CATEGORIES'), 'elcategories');
        $toolbar = $this->getToolbarInstance();

        if ($canDo->get('core.create')) {
             ToolbarHelper::addNew('category.add');
        }

        if ($canDo->get('core.edit' ) || $canDo->get('core.edit.own')) {
            ToolbarHelper::editList('category.edit');
            ToolbarHelper::divider();
        }

        if ($showActionDropdown && $this->supportsToolbarDropdown($toolbar)) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($canChangeState) {
                $childBar->publish('categories.publish')->listCheck(true);
                $childBar->unpublish('categories.unpublish')->listCheck(true);
                $childBar->archive('categories.archive')->listCheck(true);
            }

            if ($canChangeState && $user->authorise('core.admin')) { // todo: is that correct?
                $childBar->checkin('categories.checkin')->listCheck(true);
            }

            if ($this->state->get('filter.published') == -2 && $canDelete) {
                $childBar->delete('categories.remove', 'JTOOLBAR_EMPTY_TRASH')
                    ->message('COM_JEM_CONFIRM_DELETE')
                    ->listCheck(true);
            } elseif ($canChangeState) {
                $childBar->trash('categories.trash')->listCheck(true);
            }
        } elseif ($showActionDropdown) {
            if ($canChangeState) {
                ToolbarHelper::publishList('categories.publish');
                ToolbarHelper::unpublishList('categories.unpublish');
                ToolbarHelper::archiveList('categories.archive');
            }

            if ($canChangeState && $user->authorise('core.admin')) { // todo: is that correct?
                ToolbarHelper::checkin('categories.checkin');
            }

            if ($this->state->get('filter.published') == -2 && $canDelete) {
                ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'categories.remove', 'JTOOLBAR_EMPTY_TRASH');
            } elseif ($canChangeState) {
                ToolbarHelper::trash('categories.trash');
            }
        }

        if ($canDo->get('core.admin')) {
            ToolbarHelper::divider();
            ToolbarHelper::custom('categories.rebuild', 'refresh.webp', 'refresh_f2.webp', 'JTOOLBAR_REBUILD', false);
        }

        ToolbarHelper::divider();
        ToolBarHelper::help('listcategories', true, 'https://www.joomlaeventmanager.net/documentation/backend/categories');
    }
}
