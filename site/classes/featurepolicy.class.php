<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Central capability policy for the JEM usage profiles.
 *
 * Profiles decide which completed product capabilities are available. They do
 * not replace Joomla ACL and changing a profile never mutates functional data.
 */
final class JemFeaturePolicy
{
    public const PROFILE_ESSENTIAL = 'essential';
    public const PROFILE_ADVANCED = 'advanced';
    public const PROFILE_COMMERCE = 'commerce';

    public const FEATURE_PROGRAMMES = 'programmes';
    public const FEATURE_VENUE_HIERARCHY = 'venue_hierarchy';
    public const FEATURE_VENUE_CAPACITY = 'venue_capacity';
    public const FEATURE_SPACE_SCHEDULING = 'space_scheduling';
    public const FEATURE_CAPACITY_REGISTRATION = 'capacity_registration';
    public const FEATURE_NOTIFICATION_AUTOMATION = 'notification_automation';
    public const FEATURE_PRICING = 'pricing';
    public const FEATURE_PAYMENTS = 'payments';
    public const FEATURE_TICKETING = 'ticketing';

    private const SELECTABLE_PROFILES = array(
        self::PROFILE_ESSENTIAL,
        self::PROFILE_ADVANCED,
    );

    private const CAPABILITIES = array(
        self::PROFILE_ESSENTIAL => array(),
        self::PROFILE_ADVANCED => array(
            self::FEATURE_PROGRAMMES,
            self::FEATURE_VENUE_HIERARCHY,
            self::FEATURE_VENUE_CAPACITY,
            self::FEATURE_SPACE_SCHEDULING,
            self::FEATURE_CAPACITY_REGISTRATION,
            self::FEATURE_NOTIFICATION_AUTOMATION,
        ),
        self::PROFILE_COMMERCE => array(
            self::FEATURE_PROGRAMMES,
            self::FEATURE_VENUE_HIERARCHY,
            self::FEATURE_VENUE_CAPACITY,
            self::FEATURE_SPACE_SCHEDULING,
            self::FEATURE_CAPACITY_REGISTRATION,
            self::FEATURE_NOTIFICATION_AUTOMATION,
            self::FEATURE_PRICING,
            self::FEATURE_PAYMENTS,
            self::FEATURE_TICKETING,
        ),
    );

    /** @var string */
    private $profile;

    public function __construct($profile = self::PROFILE_ESSENTIAL)
    {
        $this->profile = self::normaliseStoredProfile($profile);
    }

    /**
     * Resolve the current policy from JEM configuration.
     */
    public static function current(): self
    {
        $profile = self::PROFILE_ESSENTIAL;

        if (class_exists('JemConfig')) {
            try {
                $profile = (string) JemConfig::getInstance()->toRegistry()->get(
                    'operating_profile',
                    self::PROFILE_ESSENTIAL
                );
            } catch (Throwable $error) {
                $profile = self::PROFILE_ESSENTIAL;
            }
        }

        return new self($profile);
    }

    /**
     * Return a safe profile for stored legacy or damaged configuration.
     */
    public static function normaliseStoredProfile($profile): string
    {
        $profile = strtolower(trim((string) $profile));

        return array_key_exists($profile, self::CAPABILITIES)
            ? $profile
            : self::PROFILE_ESSENTIAL;
    }

    /**
     * Validate a profile selected by an administrator in this release.
     *
     * @throws InvalidArgumentException
     */
    public static function normaliseSelectableProfile($profile): string
    {
        $profile = strtolower(trim((string) $profile));

        if (!in_array($profile, self::SELECTABLE_PROFILES, true)) {
            throw new InvalidArgumentException('COM_JEM_OPERATING_PROFILE_NOT_AVAILABLE');
        }

        return $profile;
    }

    public static function isSelectable($profile): bool
    {
        return in_array(strtolower(trim((string) $profile)), self::SELECTABLE_PROFILES, true);
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    public function isEssential(): bool
    {
        return $this->profile === self::PROFILE_ESSENTIAL;
    }

    public function isAdvanced(): bool
    {
        return $this->profile === self::PROFILE_ADVANCED;
    }

    public function allows($feature): bool
    {
        if (!self::isSelectable($this->profile)) {
            return false;
        }

        return in_array((string) $feature, self::CAPABILITIES[$this->profile], true);
    }
}
