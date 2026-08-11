<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Registration history detail view.
 */
class JemViewRegistrationhistoryentry extends JemAdminView
{
    public $item;
    public $timeline;

    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.registrations.history')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->item = $this->get('Item');
        $this->timeline = $this->get('Timeline');
        if (!$this->item) {
            throw new Exception(Text::_('COM_JEM_REGISTRATION_HISTORY_ENTRY_NOT_FOUND'), 404);
        }

        ToolbarHelper::title(Text::_('COM_JEM_REGISTRATION_HISTORY_DETAIL'), 'history');
        ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_jem&view=registrationhistory');
        parent::display($tpl);
    }
}
