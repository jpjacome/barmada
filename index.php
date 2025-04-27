<?php
// Create file: /home/orustrav/public_html/barmada/index.php

// Redirect all requests to the public directory
// This file is a fallback in case .htaccess isn't working

// Get the current path info
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// If we're already in the public directory, no need to redirect
if (strpos($uri, '/public/') !== false) {
    return false;
}

// Remove /barmada prefix if it exists
$uri = str_replace('/barmada', '', $uri);

// Redirect to the public folder - add the barmada prefix to match APP_URL
header('Location: /barmada/public' . $uri);
exit;
?>