<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class JemModelRegistration extends BaseDatabaseModel
{
    public function getItem($id = null)
    {
        $id = $id === null ? Factory::getApplication()->input->getInt('id') : (int) $id;
        $userId = (int) Factory::getApplication()->getIdentity()->id;
        if ($id < 1 || $userId < 1) {
            return null;
        }
        return (new JemNotificationService($this->getDatabase()))->getOwnedRegistration($id, $userId);
    }
}
