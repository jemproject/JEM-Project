<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

// API base URL
$feedUrl = 'https://j5.datenablage.info/index.php?option=com_ajax&plugin=jemembed&group=content&venue=link&title=link&format=json&category=link&token=qwe&max=10';

// &type=upcoming
// &cuttitle=30
// &venue=link
// &category=link
// &date=on
// &title=link
// &max=10


$ch = curl_init($feedUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FAILONERROR, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    // Display CURL errors
    echo json_encode([
        'error' => 'Proxy error: ' . curl_error($ch),
        'url' => $feedUrl
    ]);
} elseif ($httpCode >= 400) {
    // Handle HTTP errors
    echo json_encode([
        'error' => 'HTTP error: ' . $httpCode,
        'url' => $feedUrl
    ]);
} else {
    // Check if the response is valid JSON
    $decoded = json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'error' => 'Invalid JSON: ' . json_last_error_msg(),
            'url' => $feedUrl
        ]);
    } else {
        // Success: Valid JSON received
        echo $response;
    }
}

curl_close($ch);
?>