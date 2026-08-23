<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

// Can't use JPATH_COMPONENT_SITE because factory maybe used in module or plugin!
require_once (JPATH_SITE.'/components/com_jem/classes/user.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/config.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/featurepolicy.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/eventstructureddata.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/venuesnapshot.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/money.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/taxpolicy.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/taxcalculation.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/taxcalculator.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/notificationtemplatecatalog.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/notificationtemplaterenderer.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/notificationtemplateservice.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/notificationservice.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/reminderservice.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/reminderschedulerservice.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/registrationidentity.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/registrationaccesspolicy.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/pricingquote.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/pricedregistration.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/registrationservice.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/registrationtransition.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/waitinglistpromotion.class.php');


/**
 * JEM Factory class
 *
 * @package JEM
 * @since    2.1.5
 */
abstract class JemFactory extends Factory
{
    /**
     * Get a JEM user object.
     *
     * Returns the global {@link JemUser} object, only creating it if it doesn't already exist.
     *
     * @param   integer  $id  The user to load - Must be an integer or null for current user.
     *
     * @return  JemUser object
     *
     * @see     JemUser
     * @since   2.1.5
     */
    public static function getUser($id = null)
    {
        $app = Factory::getApplication();
        if (is_null($id))
        {
            $instance = $app->getSession()->get('user');
            $id = ($instance instanceof User) ? $instance->id : 0;
        }

        return JemUser::getInstance($id);
    }

    /**
     * Get the JEM configuration object.
     *
     * Returns the global {@link JemConfig} object, only creating it if it doesn't already exist.
     *
     * @return  JemConfig object
     *
     * @note    Because parent's getConfig() is limited to php files we don't override this function.
     *
     * @see     JemConfig
     * @since   2.1.6
     */
    public static function getJemConfig()
    {
        return JemConfig::getInstance();
    }

    /**
     * Get the dispatcher.
     *
     * Returns the static {@link JDispatcher} or {@link JEventDispatcher} object, depending on Joomla version.
     *
     * @return  JDispatcher or JEventDispatcher object
     *
     * @see     JDispatcher, JEventDispatcher
     * @since   2.1.7
     */
    public static function getDispatcher()
    {
        return Factory::getApplication();
    }
}
