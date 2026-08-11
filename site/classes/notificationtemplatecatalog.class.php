<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Authoritative catalogue for JEM notification templates.
 */
final class JemNotificationTemplateCatalog
{
    public const EXTENSION = 'plg_jem_mailer';
    public const SHARED_FOOTER_ID = 'plg_jem_mailer.shared_footer';
    public const SHARED_DISCLAIMER_ID = 'plg_jem_mailer.shared_privacy_disclaimer';

    /**
     * Return every reservation-related template definition in Point 2A.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all()
    {
        static $definitions;

        if ($definitions !== null) {
            return $definitions;
        }

        $definitions = array();

        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_USER_REG_SUBJECT',
            array(
                'PLG_JEM_MAILER_USER_REG_BODY_9',
                'PLG_JEM_MAILER_USER_REG_BODY_A',
                'PLG_JEM_MAILER_USER_REG_ONBEHALF_BODY_A',
                'PLG_JEM_MAILER_USER_REG_ONBEHALF_BODY_B',
            ),
            'registration',
            'user'
        );
        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_REG_SUBJECT',
            array(
                'PLG_JEM_MAILER_ADMIN_REG_BODY_8',
                'PLG_JEM_MAILER_ADMIN_REG_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF_BODY_A',
            ),
            'registration',
            'admin'
        );
        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_USER_REG_WAITING_SUBJECT',
            array(
                'PLG_JEM_MAILER_USER_REG_WAITING_BODY_9',
                'PLG_JEM_MAILER_USER_REG_WAITING_BODY_A',
                'PLG_JEM_MAILER_USER_REG_ONBEHALF_WAITING_BODY_A',
                'PLG_JEM_MAILER_USER_REG_ONBEHALF_WAITING_BODY_B',
            ),
            'waiting_list',
            'user'
        );
        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_REG_WAITING_SUBJECT',
            array(
                'PLG_JEM_MAILER_ADMIN_REG_WAITING_BODY_8',
                'PLG_JEM_MAILER_ADMIN_REG_WAITING_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF_WAITING_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF_WAITING_BODY_A',
            ),
            'waiting_list',
            'admin'
        );
        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_USER_REG_NOT_ATTEND_SUBJECT',
            array(
                'PLG_JEM_MAILER_USER_REG_NOT_ATTEND_BODY_9',
                'PLG_JEM_MAILER_USER_REG_NOT_ATTEND_BODY_A',
                'PLG_JEM_MAILER_USER_REG_ONBEHALF_NOT_ATTEND_BODY_A',
                'PLG_JEM_MAILER_USER_REG_ONBEHALF_NOT_ATTEND_BODY_B',
            ),
            'attendance_status',
            'user'
        );
        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_REG_NOT_ATTEND_SUBJECT',
            array(
                'PLG_JEM_MAILER_ADMIN_REG_NOT_ATTEND_BODY_8',
                'PLG_JEM_MAILER_ADMIN_REG_NOT_ATTEND_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF_NOT_ATTEND_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF_NOT_ATTEND_BODY_A',
            ),
            'attendance_status',
            'admin'
        );
        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_USER_REG_INVITATION_SUBJECT',
            array(
                'PLG_JEM_MAILER_USER_REG_SELF_INVITATION_BODY_9',
                'PLG_JEM_MAILER_USER_REG_SELF_INVITATION_BODY_A',
                'PLG_JEM_MAILER_USER_REG_INVITATION_BODY_A',
                'PLG_JEM_MAILER_USER_REG_INVITATION_BODY_B',
            ),
            'invitation',
            'user'
        );
        self::addFourVariants(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_REG_INVITATION_SUBJECT',
            array(
                'PLG_JEM_MAILER_ADMIN_REG_SELF_INVITATION_BODY_8',
                'PLG_JEM_MAILER_ADMIN_REG_SELF_INVITATION_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_INVITATION_BODY_9',
                'PLG_JEM_MAILER_ADMIN_REG_INVITATION_BODY_A',
            ),
            'invitation',
            'admin'
        );
        self::addTwoVariants(
            $definitions,
            'PLG_JEM_MAILER_USER_REG_UNKNOWN_SUBJECT',
            array(
                'PLG_JEM_MAILER_USER_REG_UNKNOWN_BODY_9',
                'PLG_JEM_MAILER_USER_REG_UNKNOWN_BODY_A',
            ),
            'attendance_status',
            'user'
        );
        self::addTwoVariants(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_REG_UNKNOWN_SUBJECT',
            array(
                'PLG_JEM_MAILER_ADMIN_REG_UNKNOWN_BODY_8',
                'PLG_JEM_MAILER_ADMIN_REG_UNKNOWN_BODY_9',
            ),
            'attendance_status',
            'admin'
        );
        self::addTwoVariants(
            $definitions,
            'PLG_JEM_MAILER_USER_UNREG_SUBJECT',
            array(
                'PLG_JEM_MAILER_USER_UNREG_BODY_9',
                'PLG_JEM_MAILER_USER_UNREG_BODY_A',
            ),
            'cancellation',
            'user'
        );
        self::addTwoVariants(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_UNREG_SUBJECT',
            array(
                'PLG_JEM_MAILER_ADMIN_UNREG_BODY_8',
                'PLG_JEM_MAILER_ADMIN_UNREG_BODY_9',
            ),
            'cancellation',
            'admin'
        );
        self::addOnBehalfVariants(
            $definitions,
            'PLG_JEM_MAILER_USER_UNREG_SUBJECT',
            array(
                'PLG_JEM_MAILER_USER_UNREG_ONBEHALF_BODY_A',
                'PLG_JEM_MAILER_USER_UNREG_ONBEHALF_BODY_B',
            ),
            'cancellation',
            'user'
        );
        self::addOnBehalfVariants(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_UNREG_SUBJECT',
            array(
                'PLG_JEM_MAILER_ADMIN_UNREG_ONBEHALF_BODY_9',
                'PLG_JEM_MAILER_ADMIN_UNREG_ONBEHALF_BODY_A',
            ),
            'cancellation',
            'admin'
        );

        self::addDefinition(
            $definitions,
            'PLG_JEM_MAILER_USER_REG_ON_WAITING_SUBJECT',
            'PLG_JEM_MAILER_USER_REG_ON_WAITING_BODY_9',
            'waiting_list_change',
            'user',
            'self',
            false
        );
        self::addDefinition(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_SUBJECT',
            'PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_BODY_8',
            'waiting_list_change',
            'admin',
            'self',
            false
        );
        self::addDefinition(
            $definitions,
            'PLG_JEM_MAILER_USER_REG_ON_ATTENDING_SUBJECT',
            'PLG_JEM_MAILER_USER_REG_ON_ATTENDING_BODY_9',
            'waiting_list_change',
            'user',
            'self',
            false
        );
        self::addDefinition(
            $definitions,
            'PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_SUBJECT',
            'PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_BODY_8',
            'waiting_list_change',
            'admin',
            'self',
            false
        );

        return $definitions;
    }

    /**
     * Return one definition by native Joomla template identifier.
     */
    public static function get($templateId)
    {
        $definitions = self::all();

        return $definitions[(string) $templateId] ?? null;
    }

    /**
     * Shared, language-specific content appended to notification messages.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function sharedAll()
    {
        return array(
            'footer' => array(
                'section'               => 'footer',
                'id'                    => self::SHARED_FOOTER_ID,
                'subject_key'           => 'PLG_JEM_MAILER_SHARED_FOOTER_TITLE',
                'body_key'              => 'PLG_JEM_MAILER_SHARED_FOOTER_BODY',
                'htmlbody_key'          => 'PLG_JEM_MAILER_SHARED_FOOTER_HTML',
                'allowed_tokens'        => self::sharedTokens(),
                'default_enabled_user'  => true,
                'default_enabled_admin' => false,
            ),
            'disclaimer' => array(
                'section'               => 'disclaimer',
                'id'                    => self::SHARED_DISCLAIMER_ID,
                'subject_key'           => 'PLG_JEM_MAILER_SHARED_DISCLAIMER_TITLE',
                'body_key'              => 'PLG_JEM_MAILER_SHARED_DISCLAIMER_BODY',
                'htmlbody_key'          => 'PLG_JEM_MAILER_SHARED_DISCLAIMER_HTML',
                'allowed_tokens'        => self::sharedTokens(),
                'default_enabled_user'  => false,
                'default_enabled_admin' => false,
            ),
        );
    }

    /**
     * Return one footer or disclaimer definition.
     */
    public static function shared($section)
    {
        $definitions = self::sharedAll();

        return $definitions[(string) $section] ?? null;
    }

    /**
     * Find the definition selected by the current legacy Mailer branch.
     */
    public static function findByLanguageKeys($subjectKey, $bodyKey)
    {
        foreach (self::all() as $definition) {
            if ($definition['subject_key'] === $subjectKey && $definition['body_key'] === $bodyKey) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Return the native template id derived from one existing body key.
     */
    public static function templateIdForBodyKey($bodyKey)
    {
        $name = strtolower((string) preg_replace('/^PLG_JEM_MAILER_/', '', (string) $bodyKey));

        return self::EXTENSION . '.' . $name;
    }

    private static function addFourVariants(array &$definitions, $subjectKey, array $bodyKeys, $workflow, $recipient)
    {
        self::addDefinition($definitions, $subjectKey, $bodyKeys[0], $workflow, $recipient, 'self', false);
        self::addDefinition($definitions, $subjectKey, $bodyKeys[1], $workflow, $recipient, 'self', true);
        self::addDefinition($definitions, $subjectKey, $bodyKeys[2], $workflow, $recipient, 'on_behalf', false);
        self::addDefinition($definitions, $subjectKey, $bodyKeys[3], $workflow, $recipient, 'on_behalf', true);
    }

    private static function addTwoVariants(array &$definitions, $subjectKey, array $bodyKeys, $workflow, $recipient)
    {
        self::addDefinition($definitions, $subjectKey, $bodyKeys[0], $workflow, $recipient, 'self', false);
        self::addDefinition($definitions, $subjectKey, $bodyKeys[1], $workflow, $recipient, 'self', true);
    }

    private static function addOnBehalfVariants(array &$definitions, $subjectKey, array $bodyKeys, $workflow, $recipient)
    {
        self::addDefinition($definitions, $subjectKey, $bodyKeys[0], $workflow, $recipient, 'on_behalf', false);
        self::addDefinition($definitions, $subjectKey, $bodyKeys[1], $workflow, $recipient, 'on_behalf', true);
    }

    private static function addDefinition(
        array &$definitions,
        $subjectKey,
        $bodyKey,
        $workflow,
        $recipient,
        $variant,
        $withComment
    ) {
        $templateId = self::templateIdForBodyKey($bodyKey);
        $definitions[$templateId] = array(
            'id'                    => $templateId,
            'subject_key'           => $subjectKey,
            'body_key'              => $bodyKey,
            'workflow'              => $workflow,
            'recipient'             => $recipient,
            'variant'               => $variant,
            'with_comment'          => (bool) $withComment,
            'allowed_tokens'        => self::allowedTokens(),
            'recommended_tokens'    => array('user_name', 'event_title'),
            'subject_legacy_tokens' => array('site_name'),
            'body_legacy_tokens'    => self::bodyLegacyTokens($recipient, $variant, $withComment),
        );
    }

    private static function allowedTokens()
    {
        return array(
            'user_name',
            'actor_name',
            'comment',
            'event_title',
            'event_date',
            'event_time',
            'venue',
            'city',
            'places',
            'event_description',
            'event_url',
            'event_image_url',
            'venue_image_url',
            'site_name',
        );
    }

    private static function sharedTokens()
    {
        return array(
            'site_name',
            'site_url',
            'privacy_url',
            'contact_email',
        );
    }

    private static function bodyLegacyTokens($recipient, $variant, $withComment)
    {
        $tokens = array('user_name');

        if ($variant === 'on_behalf') {
            $tokens[] = 'actor_name';
        }

        if ($withComment) {
            $tokens[] = 'comment';
        }

        $tokens = array_merge($tokens, array(
            'event_title',
            'event_date',
            'event_time',
            'venue',
            'city',
            'places',
        ));

        if ($recipient === 'user') {
            $tokens[] = 'event_description';
        }

        $tokens[] = 'event_url';
        $tokens[] = 'site_name';

        return $tokens;
    }
}
