<?php

declare(strict_types=1);

/**
 * Render only the production geolocation snippets and control IDs for browser tests.
 * Joomla, database access and remote map services are deliberately unnecessary.
 */

/**
 * Evaluate the template's own PHP output expressions with deterministic display values.
 *
 * @param string $fragment Reviewed template fragment, not request input.
 * @return string Rendered JavaScript or HTML attribute.
 */
function renderMapLocationFragment(string $fragment): string
{
    $map_id = '__MAP_ID__';
    $youAreHere = 'You are here';
    $mylocMarker = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    $encodeMapText = static fn (string $key): string => json_encode($key, JSON_THROW_ON_ERROR);
    ob_start();
    try {
        eval('?>' . $fragment);

        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$root = rtrim($argv[1] ?? dirname(__DIR__, 2), '/\\');
$templates = [
    'module' => 'modules/mod_jem_map/tmpl/default.php',
    'events' => 'site/views/eventsmap/tmpl/default.php',
    'responsive' => 'site/views/eventsmap/tmpl/responsive.php',
    'venues' => 'site/views/venuesmap/tmpl/default.php',
];
$result = [];
foreach ($templates as $kind => $path) {
    $source = file_get_contents($root . '/' . $path);
    $count = preg_match_all('/var locationMarker = null;.*?(?=\s*<\?php\s*\$heatPoints)/s', $source, $chunks);
    if ($count !== 2) {
        throw new RuntimeException('Expected Google and Leaflet location handlers in ' . $path);
    }
    $ids = [];
    foreach (['locate-me-btn', 'location-help', 'permission-instructions'] as $key) {
        if (!preg_match('/id="(' . preg_quote($key, '/') . '[^"\n]*)"/', $source, $match)) {
            throw new RuntimeException('Missing location control in ' . $path);
        }
        $ids[$key] = renderMapLocationFragment($match[1]);
    }
    foreach (['google', 'osm'] as $index => $provider) {
        $result[] = ['kind' => $kind, 'provider' => $provider, 'ids' => $ids,
            'code' => renderMapLocationFragment($chunks[0][$index])];
    }
}
echo json_encode($result, JSON_THROW_ON_ERROR);
