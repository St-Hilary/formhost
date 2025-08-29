<?php
// CORS Proxy for mpi.ministryplatform.com
// This script forwards requests to mpi.ministryplatform.com and returns the response

// Allow requests from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get the target URL from the query parameter
$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';

// Validate the URL to ensure it's pointing to mpi.ministryplatform.com
if (empty($targetUrl) || !preg_match('/^https?:\/\/mp\.sthilary\.org/', $targetUrl)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid or missing target URL']);
    exit;
}

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Forward the request method
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

// Forward request headers
$requestHeaders = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
        if (strtolower($name) !== 'host' && strtolower($name) !== 'origin' && strtolower($name) !== 'referer') {
            $requestHeaders[] = "$name: $value";
        }
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

// Forward request body for POST, PUT, etc.
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    $requestBody = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
}

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Check for cURL errors
if (curl_errno($ch)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Proxy error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

// Close cURL session
curl_close($ch);

// Set response headers
http_response_code($httpCode);
if ($contentType) {
    header("Content-Type: $contentType");
}

// Output the response
echo $response;