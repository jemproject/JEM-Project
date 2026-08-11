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
 * Registration history list view.
 */
class JemViewRegistrationhistory extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;
    public $actions = array();
    public $sources = array();

    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.registrations.history')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $model = $this->getModel();
        $this->actions = $model->getFilterValues('action');
        $this->sources = $model->getFilterValues('source');

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('table.columns');
        ToolbarHelper::title(Text::_('COM_JEM_REGISTRATION_HISTORY'), 'history');
        parent::display($tpl);
    }
}
