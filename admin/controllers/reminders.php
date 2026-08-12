<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

class JemControllerReminders extends AdminController
{
    protected $text_prefix = 'COM_JEM_REMINDERS';

    public function getModel($name = 'Reminder', $prefix = 'JemModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }
}
