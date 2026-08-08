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

require_once __DIR__ . '/accessdecision.class.php';

/**
 * Shared access policy for frontend event and venue editors.
 */
abstract class JemFrontendAccess
{
    /**
     * Enforce an already-computed frontend view permission.
     *
     * Raw downloads do not pass through the HTML view, so they must apply the
     * same record or view-level decision before writing calendar or PDF data.
     * Guests are redirected to login; authenticated users receive HTTP 403.
     *
     * @param  boolean  $allowed          Whether the current user may view the resource.
     * @param  object   $app              Joomla application.
     * @param  string   $guestMessageKey  Language key shown before the login redirect.
     *
     * @return boolean True when access is allowed, false when a guest redirect was issued.
     *
     * @throws Exception When an authenticated user is not authorised.
     */
    public static function enforceViewAccess($allowed, $app, $guestMessageKey = 'COM_JEM_LOGIN_TO_ACCESS')
    {
        if ($allowed) {
            return true;
        }

        $user = JemFactory::getUser();

        if ($user->get('guest') || !$user->get('id')) {
            $app->enqueueMessage(Text::_($guestMessageKey), 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Uri::getInstance()->toString()), false));

            return false;
        }

        throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }

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
     * Explain a create permission decision using the common JEM/Joomla policy.
     *
     * @return JemAccessDecision
     */
    public static function decideAdd($user, $type, $categoryIds = false)
    {
        if ($type !== 'event') {
            $categoryIds = false;
        } elseif (!empty($categoryIds)) {
            $categoryIds = array_values(array_filter(array_map('intval', (array) $categoryIds)));
        } else {
            $categoryIds = false;
        }

        if (method_exists($user, 'getAccessDecision')) {
            return $user->getAccessDecision('add', $type, false, false, $categoryIds);
        }

        // Compatibility for third-party user decorators which only implement can().
        return self::fromLegacyBoolean(
            (bool) $user->can('add', $type, false, false, $categoryIds),
            'add',
            $type
        );
    }

    /**
     * Backwards-compatible boolean create check.
     */
    public static function canAdd($user, $type, $categoryIds = false)
    {
        return self::decideAdd($user, $type, $categoryIds)->isAllowed();
    }

    /**
     * Explain an edit permission decision, including the record view level.
     *
     * @return JemAccessDecision
     */
    public static function decideEdit($user, $type, $item)
    {
        if (!is_object($item) || empty($item->id)) {
            return JemAccessDecision::deny(
                JemAccessDecision::RECORD_NOT_FOUND,
                'record',
                'jem_record',
                'edit',
                $type
            );
        }

        $access = isset($item->access) ? (int) $item->access : 0;
        $levels = array_map('intval', $user->getAuthorisedViewLevels());

        if (!in_array($access, $levels, true)) {
            return JemAccessDecision::deny(
                JemAccessDecision::VIEW_LEVEL_DENIED,
                'record_view_level',
                'joomla_view_level',
                'edit',
                $type,
                (int) $item->id,
                array(array(
                    'code' => JemAccessDecision::VIEW_LEVEL_DENIED,
                    'stage' => 'record_view_level',
                    'source' => 'joomla_view_level',
                    'action' => 'edit',
                )),
                array('requiredViewLevel' => $access)
            );
        }

        $arguments = array(
            'edit',
            $type,
            (int) $item->id,
            isset($item->created_by) ? (int) $item->created_by : 0,
        );

        if (method_exists($user, 'getAccessDecision')) {
            return $user->getAccessDecision(...$arguments);
        }

        return self::fromLegacyBoolean((bool) $user->can(...$arguments), 'edit', $type, (int) $item->id);
    }

    /**
     * Backwards-compatible boolean edit check.
     */
    public static function canEdit($user, $type, $item)
    {
        return self::decideEdit($user, $type, $item)->isAllowed();
    }

    /**
     * Enforce a detailed decision using its deliberately safe public response.
     * Internal reasons and details are not included in the exception message.
     */
    public static function enforce(JemAccessDecision $decision)
    {
        if (!$decision->isAllowed()) {
            throw new Exception(Text::_($decision->getMessageKey()), $decision->getHttpStatus());
        }

        return $decision;
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

    /**
     * Adapt legacy third-party boolean policies to the detailed contract.
     */
    protected static function fromLegacyBoolean($allowed, $action, $type, $recordId = 0)
    {
        if ($allowed) {
            return JemAccessDecision::allow(
                'legacy_policy',
                'legacy_boolean',
                $action,
                $type,
                $recordId
            );
        }

        return JemAccessDecision::deny(
            JemAccessDecision::ACTION_NOT_ALLOWED,
            'legacy_policy',
            'legacy_boolean',
            $action,
            $type,
            $recordId
        );
    }
}
