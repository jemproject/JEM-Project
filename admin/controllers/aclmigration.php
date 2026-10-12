<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

class JemControllerAclmigration extends BaseController
{
    public function resume(): void
    {
        JemHelper::requirePostToken();
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_jem')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        require_once JPATH_COMPONENT_ADMINISTRATOR . '/classes/aclmigration.class.php';
        $result = JemAclMigration::run(Factory::getContainer()->get('DatabaseDriver'));
        $message = Text::_($result['complete'] ? 'COM_JEM_ACL_MIGRATION_COMPLETE' : 'COM_JEM_ACL_MIGRATION_PENDING');
        if ($result['error'] !== '') {
            $message .= ' ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
        }
        $this->setRedirect('index.php?option=com_jem', $message, $result['complete'] ? 'message' : 'warning');
    }
}
