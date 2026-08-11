<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Pure renderer for legacy and named JEM notification templates.
 */
final class JemNotificationTemplateRenderer
{
    public const FORMAT_STATIC = 'static';
    public const FORMAT_LEGACY = 'legacy';
    public const FORMAT_NAMED = 'named';
    public const FORMAT_MIXED = 'mixed';

    /**
     * Identify the marker syntax used by a resolved template.
     */
    public static function detectFormat($template)
    {
        $template = (string) $template;
        $hasNamed = (bool) preg_match('/\{[a-z][a-z0-9_]*\}/', $template);
        $hasLegacy = (bool) preg_match('/%(?:(?:[1-9][0-9]*)\$)?[sd]/', $template);

        if ($hasNamed && $hasLegacy) {
            return self::FORMAT_MIXED;
        }
        if ($hasNamed) {
            return self::FORMAT_NAMED;
        }
        if ($hasLegacy) {
            return self::FORMAT_LEGACY;
        }

        return self::FORMAT_STATIC;
    }

    /**
     * Convert positional printf markers to their catalogue token names.
     */
    public static function normaliseLegacy($template, array $legacyTokens)
    {
        $template = (string) $template;
        $percentSentinel = "\x00JEM_LITERAL_PERCENT\x00";
        $template = str_replace('%%', $percentSentinel, $template);
        $nextIndex = 0;

        $normalised = preg_replace_callback(
            '/%(?:(?:([1-9][0-9]*))\$)?([sd])/',
            static function ($matches) use ($legacyTokens, &$nextIndex) {
                if (!empty($matches[1])) {
                    $index = (int) $matches[1] - 1;
                } else {
                    $index = $nextIndex++;
                }

                if (!array_key_exists($index, $legacyTokens)) {
                    throw new InvalidArgumentException('Legacy notification placeholder has no catalogue variable.');
                }

                return '{' . $legacyTokens[$index] . '}';
            },
            $template
        );

        return str_replace($percentSentinel, '%', (string) $normalised);
    }

    /**
     * Render one resolved subject or body.
     */
    public static function render($template, array $values, array $legacyTokens = array(), $html = false)
    {
        $template = (string) $template;
        $format = self::detectFormat($template);

        if ($format === self::FORMAT_LEGACY || $format === self::FORMAT_MIXED) {
            $template = self::normaliseLegacy($template, $legacyTokens);
        }

        return (string) preg_replace_callback(
            '/\{([a-z][a-z0-9_]*)\}/',
            static function ($matches) use ($values, $html) {
                $token = $matches[1];
                if (!array_key_exists($token, $values)) {
                    throw new InvalidArgumentException('Missing notification variable {' . $token . '}.');
                }

                $value = $values[$token];
                if (is_array($value) || is_object($value)) {
                    throw new InvalidArgumentException('Notification variable {' . $token . '} must be scalar.');
                }

                $value = $value === null ? '' : (string) $value;

                return $html ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
            },
            $template
        );
    }

    /**
     * Validate a custom template without rendering it.
     *
     * @return array{errors:array<int,string>,warnings:array<int,string>,variables:array<int,string>,format:string}
     */
    public static function validate(
        $template,
        array $allowedTokens,
        array $recommendedTokens = array(),
        $allowLegacy = false
    ) {
        $template = (string) $template;
        $format = self::detectFormat($template);
        $errors = array();
        $warnings = array();
        $variables = self::extractVariables($template);

        if (!$allowLegacy && ($format === self::FORMAT_LEGACY || $format === self::FORMAT_MIXED)) {
            $errors[] = 'legacy_markers_not_allowed';
        }

        if (preg_match_all('/\{([a-z][a-z0-9_-]*)\}/i', $template, $matches)) {
            foreach ($matches[1] as $candidate) {
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $candidate)) {
                    $errors[] = 'invalid_variable:' . $candidate;
                }
            }
        }

        foreach ($variables as $variable) {
            if (!in_array($variable, $allowedTokens, true)) {
                $errors[] = 'unknown_variable:' . $variable;
            }
        }

        foreach ($recommendedTokens as $variable) {
            if (!in_array($variable, $variables, true)) {
                $warnings[] = 'missing_recommended:' . $variable;
            }
        }

        return array(
            'errors'    => array_values(array_unique($errors)),
            'warnings'  => array_values(array_unique($warnings)),
            'variables' => $variables,
            'format'    => $format,
        );
    }

    /**
     * Extract unique named variables in their first-use order.
     */
    public static function extractVariables($template)
    {
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/', (string) $template, $matches);

        return array_values(array_unique($matches[1] ?? array()));
    }
}
