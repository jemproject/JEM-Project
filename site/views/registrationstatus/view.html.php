<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/classes/registrationstatusview.class.php';

/** Frontend operational overview, scoped to manageable events. */
class JemViewRegistrationstatus extends JemView
{
    use JemRegistrationStatusView;

    /** @param string|null $tpl Layout suffix. @return void */
    public function display($tpl = null)
    {
        $this->prepareReport();
        $this->setLayout('default');
        parent::display($tpl);
    }
}
