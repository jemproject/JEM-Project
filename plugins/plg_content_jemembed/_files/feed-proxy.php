<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

// Basis-URL mit eventuellen Parametern
$baseUrl = 'https://j5.datenablage.info/index.php?option=com_ajax&plugin=jemembed&group=content&format=json&token=qqwwee112233&type=upcoming&max=10&catids=1,2,3&title=link&date=on&venue=link&category=link&cuttitle=30';

// Standard-Parameter (werden durch $baseUrl-Parameter überschrieben)
$defaultParams = [
    'token' => 'qwert',   // Wird überschrieben, falls in $baseUrl vorhanden
    'type' => 'past',     // Wird überschrieben, falls in $baseUrl vorhanden
    'max' => 10,
    'catids' => '1,2,3',
    'title' => 'link',
    'venue' => 'link'
];

// 1. Parse Parameter aus $baseUrl
$urlParts = parse_url($baseUrl);
parse_str($urlParts['query'] ?? '', $baseParams);

// 2. Merge: $baseParams überschreibt $defaultParams
$finalParams = array_merge($defaultParams, $baseParams);

// 3. URL neu zusammensetzen (ohne doppelte Parameter)
$cleanUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];
$feedUrl = $cleanUrl . '?' . http_build_query($finalParams);

// Debug: Logge finale URL (optional)
file_put_contents('proxy.log', "Final URL: $feedUrl\n", FILE_APPEND);

// cURL-Request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $feedUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// ... (Rest des cURL-Codes wie zuvor)

echo $response;
?>