<?php
/**
 * @package    JEM
 * @subpackage JEM Content Plugin
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

/**
 * JEM Content Plugin
 *
 * @package    JEM.Plugin
 * @subpackage Content.jem
 * @since          1.9.6
 */
class plgContentJem extends CMSPlugin
{
    /**
     * Dissolve recurrence sets where deleted event is referred to as first.
     *
     * @param   string    The context for the content passed to the plugin.
     * @param   object    The data relating to the content that was deleted.
     *
     * @since    1.9.6
     */
    public function onContentAfterDelete($context, $data)
    {
        // Skip plugin if we are deleting something other than events
        if (($context != 'com_jem.event') || empty($data->id)) {
            return;
        }

        // event maybe first of recurrence set -> dissolve complete set
        JemHelper::dissolve_recurrence($data->id);

        return;
    }
}
