<?php
// ============================================
// Telegram Proxy - Main Handler
// ============================================

require_once 'config.php';

// Set CORS headers
setCorsHeaders();
handlePreflight();

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'];
if (!checkRateLimit($clientIp)) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'error' => 'rate_limit',
        'message' => 'Too many requests. Please wait a moment.'
    ]);
    exit();
}

// ============================================
// Parse request
// ============================================
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';
$botToken = isset($_GET['bot_token']) ? $_GET['bot_token'] : '';
$isBot = isset($_GET['is_bot']) && $_GET['is_bot'] === 'true';

// Clean the path
$path = ltrim($path, '/');

// Determine API endpoint
if ($isBot && !empty($botToken)) {
    $baseUrl = TELEGRAM_API_BASE . '/bot' . $botToken;
} else {
    // For user mode, we need to use the MTProto API
    // This is a simplified version - for full user mode, you'd need MTProto
    $baseUrl = TELEGRAM_API_BASE;
}

// Build target URL
$targetUrl = $baseUrl . '/' . $path;

// ============================================
// Get request body
// ============================================
$input = file_get_contents('php://input');
$headers = getallheaders();

// Remove problematic headers
unset($headers['Host']);
unset($headers['Content-Length']);

// ============================================
// Prepare cURL request
// ============================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// SSL settings - disable for testing, enable for production
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Set request method
switch ($method) {
    case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
        break;
    case 'PUT':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
        break;
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;
    case 'GET':
    default:
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        break;
}

// Set headers
$headerArray = [];
foreach ($headers as $key => $value) {
    if (strtolower($key) !== 'host' && 
        strtolower($key) !== 'content-length' &&
        strtolower($key) !== 'accept-encoding') {
        $headerArray[] = "$key: $value";
    }
}

// Add user agent
$headerArray[] = 'User-Agent: Telegram-Proxy/1.0 (PHP)';

if (!empty($headerArray)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
}

// ============================================
// Execute request
// ============================================
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

// ============================================
// Log request (optional - for debugging)
// ============================================
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $clientIp,
    'method' => $method,
    'path' => $path,
    'url' => $targetUrl,
    'status' => $httpCode,
    'error' => $error
];

// Uncomment to enable logging
// file_put_contents('proxy.log', json_encode($logData) . "\n", FILE_APPEND);

// ============================================
// Return response
// ============================================
if ($error) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $error,
        'message' => 'Proxy error',
        'curl_info' => $curlInfo
    ]);
} else {
    http_response_code($httpCode);
    echo $response;
}

?>