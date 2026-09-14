<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Resolves venue-detail map menu overrides against the global map service.
 */
final class JemVenueMapPolicy
{
    /**
     * @param   string   $display            Menu-item display preference
     * @param   integer  $globalMapService   Global JEM map service
     * @param   boolean  $allowMenuOverride  Whether the active menu owns the venue page
     *
     * @return  array
     */
    public static function resolve($display, $globalMapService, $allowMenuOverride = true)
    {
        $globalMapService = (int) $globalMapService;
        if (!in_array($globalMapService, array(0, 1, 2, 3, 4, 5), true)) {
            $globalMapService = 0;
        }

        $display = strtolower(trim((string) $display));
        if ($display === 'hide') {
            $display = 'none';
        } elseif ($display === 'link') {
            $display = 'link_button';
        }

        if (!$allowMenuOverride || !in_array($display, array('global', 'none', 'link_text', 'link_button', 'map'), true)) {
            $display = 'global';
        }

        if ($display === 'global') {
            if (in_array($globalMapService, array(1, 4), true)) {
                $effectiveDisplay = 'link_text';
            } elseif (in_array($globalMapService, array(2, 3, 5), true)) {
                $effectiveDisplay = 'map';
            } else {
                $effectiveDisplay = 'none';
            }

            return array(
                'display'  => $effectiveDisplay,
                'service'  => $globalMapService,
                'provider' => in_array($globalMapService, array(1, 2, 3), true) ? 'google' : 'osm',
            );
        }

        if ($display === 'none') {
            return array('display' => 'none', 'service' => 0, 'provider' => 'osm');
        }

        $useGoogle = in_array($globalMapService, array(1, 2, 3), true);
        if ($display === 'link_text' || $display === 'link_button') {
            return array(
                'display'  => $display,
                'service'  => $useGoogle ? 1 : 4,
                'provider' => $useGoogle ? 'google' : 'osm',
            );
        }

        $service = $useGoogle ? ($globalMapService === 3 ? 3 : 2) : 5;

        return array(
            'display'  => 'map',
            'service'  => $service,
            'provider' => $useGoogle ? 'google' : 'osm',
        );
    }
}
