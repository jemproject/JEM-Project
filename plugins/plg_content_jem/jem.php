<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
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
class plgContentJem extends CSMPlugin
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
