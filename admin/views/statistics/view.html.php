<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewStatistics extends HtmlView
{
    public function display($tpl = null)
    {
        $permissions = array(
            'events' => JemHelperBackend::can('event', 'access'),
            'venues' => JemHelperBackend::can('venue', 'access'),
            'registrations' => JemHelperBackend::canManage('jem.attendees.manage'),
        );

        $model = $this->getModel();
        $this->filters = $model->getFilters();
        $this->filterOptions = $model->getFilterOptions();
        $this->statistics = $model->getDashboardData($this->filters, $permissions);
        $this->permissions = $permissions;

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        ToolbarHelper::title(Text::_('COM_JEM_STATISTICS_TITLE'), 'chart');
        ToolbarHelper::help('statistics', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel');

        parent::display($tpl);
    }
}
