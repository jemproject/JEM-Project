<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/notificationtemplates.php';

/**
 * Main Notifications model.
 *
 * The former Notificationtemplates model remains available so bookmarks and
 * third-party administrator links keep working during the 5.1 transition.
 */
class JemModelNotifications extends JemModelNotificationtemplates
{
}
