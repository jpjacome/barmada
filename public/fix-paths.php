<?php
// This file helps fix issues with incorrect asset paths in production
// Place this file in your public directory

// Don't show errors if accessing the file directly
if (basename($_SERVER['SCRIPT_NAME']) === 'fix-paths.php') {
    echo "<h1>Path Fixer Utility</h1>";
    echo "<p>This file is working correctly. It's a utility to fix asset paths and shouldn't be accessed directly.</p>";
    echo "<p>Current server path: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
    echo "<p>Current request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
    exit;
}

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = pathinfo($request_uri);
$base_path = '/barmada/public';

// Check if accessing a public asset file directly
if (strpos($request_uri, '/public/') !== false && !file_exists($_SERVER['DOCUMENT_ROOT'] . $request_uri)) {
    // Try to fix the path
    $fixed_path = str_replace('/public/', $base_path . '/', $request_uri);
    
    // If this is a JavaScript or CSS file, redirect to the correct location
    if (isset($path_parts['extension']) && in_array($path_parts['extension'], ['js', 'css', 'png', 'jpg', 'gif', 'svg'])) {
        header("Location: $fixed_path");
        exit;
    }
}

// If we got here, the file doesn't exist or isn't a redirectable type
header("HTTP/1.0 404 Not Found");
echo "File not found. Please contact the site administrator.";
exit; 