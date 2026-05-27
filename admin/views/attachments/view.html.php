<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for the JEM Attachments screen.
 */
class JemViewAttachments extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;

    public function display($tpl = null)
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
        $wa->useScript('table.columns');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_ATTACHMENTS'), 'attachment');
        $toolbar = $this->getToolbarInstance();

        $canDo = JemHelperBackend::getActions(0);
        $canEdit = $canDo->get('core.edit');
        $canDelete = $canDo->get('core.delete');

        if ($canEdit) {
            ToolbarHelper::editList('attachment.edit');
            ToolbarHelper::divider();
        }

        if (($canDelete || $canDo->get('core.manage')) && $this->supportsToolbarDropdown($toolbar)) {
            $dropdown = $toolbar->dropdownButton('attachment-actions')
                ->text('JTOOLBAR_ACTIONS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(false);
            $childBar = $dropdown->getChildToolbar();

            if ($canDelete) {
                $childBar->delete('attachments.delete', 'JACTION_DELETE')
                    ->message('COM_JEM_ATTACHMENTS_DELETE_RECORDS_CONFIRM')
                    ->listCheck(true);
                $childBar->delete('attachments.deleteFiles', 'COM_JEM_ATTACHMENTS_DELETE_RECORDS_AND_FILES')
                    ->message('COM_JEM_ATTACHMENTS_DELETE_RECORDS_AND_FILES_CONFIRM')
                    ->listCheck(true);
            }

            if ($canDo->get('core.manage')) {
                $childBar->standardButton('export')
                    ->text('COM_JEM_EXPORT')
                    ->task('attachments.export')
                    ->icon('icon-download');
            }
        } else {
            if ($canDelete) {
                ToolbarHelper::deleteList('COM_JEM_ATTACHMENTS_DELETE_RECORDS_CONFIRM', 'attachments.delete', 'JACTION_DELETE');
                ToolbarHelper::custom('attachments.deleteFiles', 'delete', 'delete', 'COM_JEM_ATTACHMENTS_DELETE_RECORDS_AND_FILES', true);
            }

            if ($canDo->get('core.manage')) {
                ToolbarHelper::custom('attachments.export', 'download', 'download', 'COM_JEM_EXPORT', false);
            }
        }

        ToolbarHelper::divider();
        ToolbarHelper::help('attachments', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/attachments');
    }
}
