<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Plugin based on the Joomla! update notification plugin
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;


/**
 * JEM Quickicon Plugin
 *
 */
class plgQuickiconJEMquickicon extends JPlugin
{
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
    }

    public function onGetIcons($context)
    {
        if ($context != $this->params->get('context', 'mod_quickicon') ||
            !Factory::getUser()->authorise('core.manage', 'com_jem') ||
            !file_exists(JPATH_ADMINISTRATOR . '/components/com_jem/helpers/helper.php')) {
            return;
        }

        $useIcons = version_compare(JVERSION, '3.0', '>');
        $icon     = 'com_jem/icon-48-home.png'; // which means '/media/com_jem/images/icon-48-home.png'
        $text     = $this->params->get('displayedtext');
        if (empty($text)) {
            $text = Text::_('Joomla Event Manager');
        }

        return array(
            array(
            	'access'  => array('core.manage', 'com_jem'),	
                'link'    => Route::_('index.php?option=com_jem'),
                'linkadd' => Route::_('index.php?option=com_jem&task=event.add'),
                'image'   => 'icon-calendar',
                'icon'    => '',
                'text'    => $text,
                'id'      => 'plg_quickicon_jemquickicon',
                'group'   => 'JEM'
            )
        );
    }
}
