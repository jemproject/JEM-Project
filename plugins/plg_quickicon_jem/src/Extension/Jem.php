<?php
namespace Joomla\Plugin\Quickicon\Jem\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;

class Jem extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onGetIcons($context)
    {
        if ($context !== 'mod_quickicon' || !$this->getApplication()->getIdentity()->authorise('core.manage', 'com_jem')) {
            return [];
        }

        return [
            [
                'link'    => 'index.php?option=com_jem',
                'linkadd' => 'index.php?option=com_jem&task=event.add',
                'image'   => 'icon-calendar',
                'icon'    => 'icon-calendar',
                'text'    => Text::_('PLG_QUICKICON_JEM_JEM'),
                'id'      => 'plg_quickicon_jem'
            ]
        ];
    }
}