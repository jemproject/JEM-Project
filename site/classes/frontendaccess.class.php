<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Shared access policy for frontend event and venue editors.
 */
abstract class JemFrontendAccess
{
    /**
     * Redirect guests to the Joomla login form and preserve the requested URL.
     *
     * @param  object  $app  Joomla application.
     *
     * @return boolean True when a redirect was issued.
     */
    public static function redirectGuestToLogin($app)
    {
        $user = JemFactory::getUser();

        if (!empty($user->id) && !$user->get('guest', 0)) {
            return false;
        }

        $return = base64_encode(Uri::getInstance()->toString());
        $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
        $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));

        return true;
    }

    /**
     * Read and normalise the frontend record id used by canonical and legacy routes.
     *
     * Both id and a_id are accepted, but conflicting values are rejected.
     *
     * @param  object   $input     Joomla input object.
     * @param  boolean  $required  Whether a positive id is required.
     *
     * @return integer
     *
     * @throws Exception For a missing, malformed or ambiguous id.
     */
    public static function normaliseRecordId($input, $required = false)
    {
        $id = self::readId($input, array('a_id', 'id'), $required);

        $input->set('a_id', $id);
        $input->set('id', $id);

        return $id;
    }

    /**
     * Read an integer id from one or more request keys.
     *
     * @param  object   $input     Joomla input object.
     * @param  array    $keys      Accepted request keys.
     * @param  boolean  $required  Whether a positive id is required.
     *
     * @return integer
     *
     * @throws Exception For a missing, malformed or ambiguous id.
     */
    public static function readId($input, array $keys, $required = false)
    {
        $ids = array();

        foreach ($keys as $key) {
            if (!$input->exists($key)) {
                continue;
            }

            $raw = $input->get($key, null, 'raw');

            if (is_array($raw) || is_object($raw)) {
                throw new Exception(Text::_('COM_JEM_ERROR_INVALID_RECORD_ID'), 400);
            }

            $raw = trim((string) $raw);

            // Accept Joomla's routed "id:alias" form while rejecting partial integers.
            if (!preg_match('/^(0|[1-9][0-9]*)(?::[^\s]*)?$/', $raw, $matches)) {
                throw new Exception(Text::_('COM_JEM_ERROR_INVALID_RECORD_ID'), 400);
            }

            $ids[] = (int) $matches[1];
        }

        $ids = array_values(array_unique($ids));

        if (count($ids) > 1) {
            throw new Exception(Text::_('COM_JEM_ERROR_INVALID_RECORD_ID'), 400);
        }

        $id = $ids ? $ids[0] : 0;

        if ($required && $id < 1) {
            throw new Exception(Text::_('COM_JEM_ERROR_INVALID_RECORD_ID'), 400);
        }

        return $id;
    }

    /**
     * Check create permission using the common JEM/Joomla policy.
     */
    public static function canAdd($user, $type, $categoryIds = false)
    {
        if ($type !== 'event') {
            $categoryIds = false;
        } elseif (!empty($categoryIds)) {
            $categoryIds = array_values(array_filter(array_map('intval', (array) $categoryIds)));
        } else {
            $categoryIds = false;
        }

        return (bool) $user->can('add', $type, false, false, $categoryIds);
    }

    /**
     * Check edit permission and the record's Joomla view level.
     */
    public static function canEdit($user, $type, $item)
    {
        if (!is_object($item) || empty($item->id)) {
            return false;
        }

        $access = isset($item->access) ? (int) $item->access : 0;
        $levels = array_map('intval', $user->getAuthorisedViewLevels());

        if (!in_array($access, $levels, true)) {
            return false;
        }

        return (bool) $user->can(
            'edit',
            $type,
            (int) $item->id,
            isset($item->created_by) ? (int) $item->created_by : 0
        );
    }

    /**
     * Check whether a user may open an event editor selector layout.
     */
    public static function canUseEventSelectors($app, $user, $model, $recordId = 0)
    {
        if ($recordId > 0) {
            $item = $model->getItem($recordId);

            return self::canEdit($user, 'event', $item);
        }

        if (self::canAdd($user, 'event', $app->input->getInt('catid', 0))) {
            return true;
        }

        $heldIds = (array) $app->getUserState('com_jem.edit.event.id', array());

        foreach ($heldIds as $heldId) {
            $heldId = (int) $heldId;

            if ($heldId > 0 && self::canEdit($user, 'event', $model->getItem($heldId))) {
                return true;
            }
        }

        return false;
    }
}
