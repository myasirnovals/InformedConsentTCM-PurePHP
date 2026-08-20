<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static assets from public/
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// Route API requests to api/
if (preg_match('#^/api/(.*)#', $uri, $matches)) {
    $apiFile = __DIR__ . '/api/' . $matches[1];
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
}

// Main entry point
require __DIR__ . '/public/index.php';
