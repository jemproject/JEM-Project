<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Immutable-style result of a JEM access evaluation.
 *
 * Permission checks previously returned only a boolean. This value object keeps
 * that final result and also explains how it was reached:
 *
 * - allowed: final boolean consumed by the legacy JemUser::can() API;
 * - code: stable machine-readable outcome such as ACTION_NOT_ALLOWED;
 * - stage: evaluation phase which granted or blocked the request;
 * - source: subsystem responsible for the result (Joomla ACL, JEM setting,
 *   ownership, JEM group, category or record view level);
 * - action/resource/resourceId: operation and target evaluated;
 * - httpStatus/messageKey: safe default response for an HTTP controller;
 * - reasons: internal checks that contributed to the result;
 * - details: internal diagnostic context. Consumers must not expose it to an
 *   unauthorised visitor because it can contain protected record identifiers.
 *
 * Use toArray(false) for the deliberately generic public response and
 * toArray(true) only for administrator diagnostics or access logs.
 */
final class JemAccessDecision
{
    public const ALLOWED = 'ALLOWED';
    public const AUTHENTICATION_REQUIRED = 'AUTHENTICATION_REQUIRED';
    public const INVALID_ACTION = 'INVALID_ACTION';
    public const INVALID_RESOURCE_TYPE = 'INVALID_RESOURCE_TYPE';
    public const RECORD_NOT_FOUND = 'RECORD_NOT_FOUND';
    public const VIEW_LEVEL_DENIED = 'VIEW_LEVEL_DENIED';
    public const ACTION_NOT_ALLOWED = 'ACTION_NOT_ALLOWED';
    public const NOT_RECORD_OWNER = 'NOT_RECORD_OWNER';
    public const CATEGORY_NOT_FOUND = 'CATEGORY_NOT_FOUND';
    public const CATEGORY_VIEW_DENIED = 'CATEGORY_VIEW_DENIED';
    public const JEM_GROUP_REQUIRED = 'JEM_GROUP_REQUIRED';
    public const JEM_GROUP_ACTION_DENIED = 'JEM_GROUP_ACTION_DENIED';

    protected $allowed;
    protected $code;
    protected $stage;
    protected $source;
    protected $action;
    protected $resource;
    protected $resourceId;
    protected $httpStatus;
    protected $messageKey;
    protected $reasons;
    protected $details;

    /**
     * @param boolean      $allowed     Final access result.
     * @param string       $code        Stable machine-readable result code.
     * @param string       $stage       Evaluation stage.
     * @param string       $source      Subsystem which produced the result.
     * @param string|array $action      Requested action or actions.
     * @param string       $resource    event or venue.
     * @param integer      $resourceId  Target record id, or zero for a new item.
     * @param integer      $httpStatus  Suggested safe HTTP status.
     * @param string       $messageKey  Joomla language key for the public message.
     * @param array        $reasons     Internal contributing checks.
     * @param array        $details     Internal diagnostic context.
     */
    private function __construct(
        $allowed,
        $code,
        $stage,
        $source,
        $action,
        $resource,
        $resourceId,
        $httpStatus,
        $messageKey,
        array $reasons = array(),
        array $details = array()
    ) {
        $this->allowed = (bool) $allowed;
        $this->code = (string) $code;
        $this->stage = (string) $stage;
        $this->source = (string) $source;
        $this->action = $action;
        $this->resource = (string) $resource;
        $this->resourceId = (int) $resourceId;
        $this->httpStatus = (int) $httpStatus;
        $this->messageKey = (string) $messageKey;
        $this->reasons = array_values($reasons);
        $this->details = $details;
    }

    /**
     * Create an allowed decision.
     */
    public static function allow($stage, $source, $action, $resource, $resourceId = 0, array $reasons = array(), array $details = array())
    {
        return new self(
            true,
            self::ALLOWED,
            $stage,
            $source,
            $action,
            $resource,
            $resourceId,
            200,
            '',
            $reasons,
            $details
        );
    }

    /**
     * Create a denied decision.
     */
    public static function deny($code, $stage, $source, $action, $resource, $resourceId = 0, array $reasons = array(), array $details = array())
    {
        return new self(
            false,
            $code,
            $stage,
            $source,
            $action,
            $resource,
            $resourceId,
            self::defaultHttpStatus($code),
            self::defaultMessageKey($code),
            $reasons,
            $details
        );
    }

    /**
     * Boolean result used by JemUser::can() and existing extensions.
     */
    public function isAllowed()
    {
        return $this->allowed;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getStage()
    {
        return $this->stage;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function getResourceId()
    {
        return $this->resourceId;
    }

    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    public function getMessageKey()
    {
        return $this->messageKey;
    }

    public function getReasons()
    {
        return $this->reasons;
    }

    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Export the result.
     *
     * @param boolean $includeInternal Include the decision code, evaluation
     *                                 path, target and diagnostic context. This
     *                                 must be false for untrusted visitors.
     */
    public function toArray($includeInternal = false)
    {
        $result = array(
            'allowed'    => $this->allowed,
            'httpStatus' => $this->httpStatus,
            'messageKey' => $this->messageKey,
        );

        if ($includeInternal) {
            $result['code'] = $this->code;
            $result['stage'] = $this->stage;
            $result['source'] = $this->source;
            $result['action'] = $this->action;
            $result['resource'] = $this->resource;
            $result['resourceId'] = $this->resourceId;
            $result['reasons'] = $this->reasons;
            $result['details'] = $this->details;
        }

        return $result;
    }

    /**
     * Map internal denial codes to safe public HTTP statuses.
     */
    protected static function defaultHttpStatus($code)
    {
        switch ($code) {
            case self::AUTHENTICATION_REQUIRED:
                return 401;
            case self::INVALID_ACTION:
            case self::INVALID_RESOURCE_TYPE:
                return 400;
            case self::RECORD_NOT_FOUND:
            case self::VIEW_LEVEL_DENIED:
            case self::CATEGORY_NOT_FOUND:
            case self::CATEGORY_VIEW_DENIED:
                // Do not disclose the existence of a record outside the user's view levels.
                return 404;
            default:
                return 403;
        }
    }

    /**
     * Map result codes to deliberately generic public messages.
     * Detailed reasons remain available only through the internal result fields.
     */
    protected static function defaultMessageKey($code)
    {
        switch ($code) {
            case self::INVALID_ACTION:
            case self::INVALID_RESOURCE_TYPE:
                return 'COM_JEM_ERROR_INVALID_ACCESS_REQUEST';
            case self::RECORD_NOT_FOUND:
            case self::VIEW_LEVEL_DENIED:
            case self::CATEGORY_NOT_FOUND:
            case self::CATEGORY_VIEW_DENIED:
                return 'COM_JEM_ACCESS_RECORD_NOT_FOUND';
            case self::AUTHENTICATION_REQUIRED:
                return 'COM_JEM_ACCESS_AUTHENTICATION_REQUIRED';
            default:
                return 'COM_JEM_ACCESS_ACTION_NOT_ALLOWED';
        }
    }
}
