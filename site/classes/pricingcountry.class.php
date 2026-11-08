<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

require_once __DIR__ . '/config.class.php';

/** Country fallback shared by event pricing validation and commercial quotes. */
final class JemPricingCountry
{
    /**
     * Normalise a configured country; empty/legacy selector values are unset.
     *
     * @param mixed $value Country selector value.
     * @return string Uppercase ISO-shaped country code or an empty string.
     */
    public static function normalise($value): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{2}$/D', $value) === 1 ? $value : '';
    }

    /**
     * Resolve the global tax country, inheriting the existing default if unset.
     *
     * @return string Configured country code, or empty when neither setting is set.
     */
    public static function defaultCountry(): string
    {
        $settings = JemConfig::getInstance()->toRegistry();

        return self::normalise($settings->get('defaultTaxCountry', ''))
            ?: self::normalise($settings->get('defaultCountry', ''));
    }

    /**
     * Prefer the venue country and use the global setting when it is absent.
     *
     * @param mixed $venueCountry Country of the selected venue, if any.
     * @return string Effective country for the event pricing configuration.
     */
    public static function forVenue($venueCountry): string
    {
        return self::normalise($venueCountry) ?: self::defaultCountry();
    }
}
