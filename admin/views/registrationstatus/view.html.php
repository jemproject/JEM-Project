<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

require_once JPATH_SITE . '/components/com_jem/classes/registrationstatusview.class.php';

/** Backend operational overview using the same data policy as the frontend. */
class JemViewRegistrationstatus extends JemAdminView
{
    use JemRegistrationStatusView;

    /** @param string|null $tpl Layout suffix. @return void */
    public function display($tpl = null)
    {
        $this->prepareReport();
        ToolbarHelper::title(Text::_('COM_JEM_RS_TITLE'), 'users');
        parent::display($tpl);
    }
}
