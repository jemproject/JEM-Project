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

class JemViewNotifications extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;
    public $languages = array();

    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.notifications.templates')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->languages = $this->getModel()->getLanguages();

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('table.columns');
        ToolbarHelper::title(Text::_('COM_JEM_NOTIFICATIONS'), 'envelope');
        parent::display($tpl);
    }
}
