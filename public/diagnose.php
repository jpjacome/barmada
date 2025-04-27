<?php
// Diagnostic file for troubleshooting URL and path issues

// Turn on error reporting for diagnostic purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Header for HTML display
header('Content-Type: text/html; charset=utf-8');

// Determine the correct application path
$app_path = dirname($_SERVER['SCRIPT_FILENAME']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Path Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        .info { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .data { font-family: monospace; background: #eee; padding: 10px; }
        .warning { background: #fff3cd; padding: 10px; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>URL and Path Diagnostics</h1>
    
    <div class="warning">
        <strong>Important:</strong> This script shows sensitive server information. 
        Remove it after debugging or secure it properly.
    </div>
    
    <h2>Server Information</h2>
    <div class="info">
        <div><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
        <div><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
        <div><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></div>
        <div><strong>Script Filename:</strong> <?php echo $_SERVER['SCRIPT_FILENAME']; ?></div>
        <div><strong>Application Path:</strong> <?php echo $app_path; ?></div>
    </div>
    
    <h2>URL Information</h2>
    <div class="info">
        <div><strong>HTTP Host:</strong> <?php echo $_SERVER['HTTP_HOST']; ?></div>
        <div><strong>Server Name:</strong> <?php echo $_SERVER['SERVER_NAME']; ?></div>
        <div><strong>Request URI:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></div>
        <div><strong>Script Name:</strong> <?php echo $_SERVER['SCRIPT_NAME']; ?></div>
        <div><strong>PHP Self:</strong> <?php echo $_SERVER['PHP_SELF']; ?></div>
        <div><strong>Request Scheme:</strong> <?php echo $_SERVER['REQUEST_SCHEME'] ?? 'Unknown'; ?></div>
        <div><strong>Full URL:</strong> <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?></div>
    </div>
    
    <h2>Path Testing</h2>
    <div class="info">
        <?php
        // Check both document root and app-relative paths
        $test_paths = [
            'app.js (app root)' => $app_path . '/js/app.js',
            'app.js (document root)' => $_SERVER['DOCUMENT_ROOT'] . '/js/app.js',
            'app.js (barmada/public)' => $_SERVER['DOCUMENT_ROOT'] . '/barmada/public/js/app.js',
            'livewire.js (app root)' => $app_path . '/vendor/livewire/livewire.js',
            'livewire.js (document root)' => $_SERVER['DOCUMENT_ROOT'] . '/vendor/livewire/livewire.js',
            'livewire.js (barmada/public)' => $_SERVER['DOCUMENT_ROOT'] . '/barmada/public/vendor/livewire/livewire.js',
            'fix-paths.php (app root)' => $app_path . '/fix-paths.php',
            'fix-paths.php (document root)' => $_SERVER['DOCUMENT_ROOT'] . '/fix-paths.php',
            'fix-paths.php (barmada/public)' => $_SERVER['DOCUMENT_ROOT'] . '/barmada/public/fix-paths.php',
        ];
        
        echo "<div><strong>Checking file existence:</strong></div>";
        foreach ($test_paths as $name => $path) {
            $exists = file_exists($path);
            echo "<div>{$name} ({$path}): " . ($exists ? '<span class="success">✅ Found</span>' : '<span class="error">❌ Not found</span>') . "</div>";
        }
        ?>
    </div>
    
    <h2>Laravel Environment</h2>
    <div class="info">
        <?php
        // Try different locations for .env file
        $env_paths = [
            'Parent of document root' => dirname($_SERVER['DOCUMENT_ROOT']) . '/.env',
            'Document root' => $_SERVER['DOCUMENT_ROOT'] . '/.env',
            'Barmada root' => $_SERVER['DOCUMENT_ROOT'] . '/barmada/.env',
            'Application path parent' => dirname($app_path) . '/.env',
        ];
        
        $env_found = false;
        foreach ($env_paths as $location => $env_path) {
            if (file_exists($env_path)) {
                echo "<div><strong>Found .env at {$location}:</strong> {$env_path}</div>";
                $env_found = true;
                $env_content = file_get_contents($env_path);
                // Only show APP_URL and ASSET_URL for security
                preg_match('/APP_URL=(.+)/', $env_content, $app_url_matches);
                preg_match('/ASSET_URL=(.+)/', $env_content, $asset_url_matches);
                
                echo "<div><strong>APP_URL:</strong> " . ($app_url_matches[1] ?? 'Not found') . "</div>";
                echo "<div><strong>ASSET_URL:</strong> " . ($asset_url_matches[1] ?? 'Not found') . "</div>";
                break;
            }
        }
        
        if (!$env_found) {
            echo "<div class='error'>Cannot find .env file in any standard location</div>";
        }
        ?>
    </div>
    
    <h2>Asset Generation Test</h2>
    <div class="info">
        <?php
        // Test how Laravel would generate URLs for assets
        $test_assets = [
            'js/app.js',
            'css/app.css',
            'vendor/livewire/livewire.js'
        ];
        
        echo "<p>If Laravel's asset() helper function were used, paths would look like:</p>";
        
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $subdir = '/barmada/public';
        
        foreach ($test_assets as $asset) {
            echo "<div><strong>{$asset}:</strong> {$base_url}{$subdir}/{$asset}</div>";
        }
        ?>
    </div>
    
    <h2>Recommended fixes</h2>
    <div class="info">
        <p>Based on your current setup:</p>
        <ol>
            <li>Make sure all asset paths use asset() helper with correct base URL</li>
            <li>Check .htaccess configuration for proper rewriting</li>
            <li>Ensure Laravel's ASSET_URL environment variable is set correctly</li>
            <li>Consider creating symbolic links for asset folders for better path resolution</li>
            <li>Clear Laravel cache with: php artisan config:clear, cache:clear, view:clear</li>
        </ol>
    </div>
</body>
</html> 