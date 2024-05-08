<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @subpackage JEM Comments Plugin
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

include_once(JPATH_SITE . '/components/com_jem/helpers/route.php');

class plgJEMComments extends CMSPlugin
{

    /**
     * Constructor
     *
     * @param   object  $subject  The object to observe
     * @param   array   $config   An array that holds the plugin configuration
     *
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }


    /**
     * This method handles the supported comment systems
     *
     * @access    public
     *
     * @param   int  $event_id     Integer Event identifier
     * @param   int  $event_title  String Event title
     *
     * @return    boolean
     *
     */
    public function onEventEnd($event_id, $event_title = '')
    {
        //simple, skip if processing not needed
        if (!$this->params->get('commentsystem', '0')) {
            return '';
        }

        $res = '';

        //jcomments integration
        if ($this->params->get('commentsystem') == 2) {
            if (file_exists(JPATH_SITE . '/components/com_jcomments/jcomments.php')) {
                require_once(JPATH_SITE . '/components/com_jcomments/jcomments.php');
                $res .= '<div class="jcomments">';
                $res .= JComments::show($event_id, 'com_jem', $event_title);
                $res .= '</div>';
            }
        }

        return $res;
    }
}

?>