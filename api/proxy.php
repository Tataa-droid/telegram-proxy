<?php
// ============================================
// Telegram Proxy - Simple Version
// ============================================

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// Simple test endpoint
// ============================================
$path = isset($_GET['path']) ? $_GET['path'] : '';

// If no path provided, show status
if (empty($path)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Telegram Proxy is running!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ============================================
// Forward to Telegram API
// ============================================
$botToken = isset($_GET['bot_token']) ? $_GET['bot_token'] : '';
$isBot = isset($_GET['is_bot']) && $_GET['is_bot'] === 'true';

if ($isBot && !empty($botToken)) {
    $targetUrl = 'https://api.telegram.org/bot' . $botToken . '/' . ltrim($path, '/');
} else {
    $targetUrl = 'https://api.telegram.org/' . ltrim($path, '/');
}

// Forward request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
?>
