<?php
// Router script for PHP built-in development server
// This mimics the .htaccess rewrite rules

// Get the request URI
$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// If it's a real file (like CSS, JS, images), serve it directly
if ($request_uri !== '/' && file_exists(__DIR__ . $request_uri)) {
    return false; // Serve the file as-is
}

// Set the _route parameter for index.php
$_GET['_route'] = ltrim($request_uri, '/');

// Load index.php
require __DIR__ . '/index.php';
