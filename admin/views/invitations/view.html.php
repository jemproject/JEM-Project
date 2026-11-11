<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/classes/invitationview.class.php';
/** Invitation composer for this Joomla client. */
class JemViewInvitations extends JemAdminView
{
    use JemInvitationView;
    /** @param string|null $tpl Layout suffix. @return void */
    public function display($tpl = null)
    {
        $this->prepareInvitations();
        parent::display($tpl);
    }
}
