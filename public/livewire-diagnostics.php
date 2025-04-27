<?php
/**
 * Livewire Diagnostics Script
 * 
 * This standalone script runs outside the Laravel framework to help diagnose
 * issues with Livewire and URL generation in shared hosting environments.
 */

// Configuration - fill in with your actual values
$config = [
    'app_url' => isset($_SERVER['HTTP_HOST']) ? 
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'laravel_base' => dirname(__DIR__),
    'public_path' => __DIR__,
];

// Enable error reporting for diagnostics
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to plain text unless HTML is requested
if (!isset($_GET['html'])) {
    header('Content-Type: text/plain');
}

echo "===== LIVEWIRE DIAGNOSTICS =====\n\n";

// Basic server information
echo "SERVER INFORMATION:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n";
echo "Host: " . ($config['app_url']) . "\n";
echo "Document Root: " . $config['document_root'] . "\n";
echo "Script Filename: " . $config['script_filename'] . "\n";
echo "Request URI: " . $config['request_uri'] . "\n";
echo "Laravel Base Path: " . $config['laravel_base'] . "\n";
echo "Public Path: " . $config['public_path'] . "\n\n";

// Check if we're in a subdirectory
$in_subdir = strpos($config['request_uri'], '/public/') !== false;
echo "In subdirectory: " . ($in_subdir ? 'Yes' : 'No') . "\n\n";

// Parse the URL to identify components
$url_parts = parse_url($config['app_url'] . $config['request_uri']);
echo "URL ANALYSIS:\n";
echo "Scheme: " . ($url_parts['scheme'] ?? 'unknown') . "\n";
echo "Host: " . ($url_parts['host'] ?? 'unknown') . "\n";
echo "Path: " . ($url_parts['path'] ?? 'unknown') . "\n";
echo "Query: " . ($url_parts['query'] ?? 'none') . "\n\n";

// Try to determine the base URL
$base_url_candidates = [];
$path_segments = explode('/', trim($url_parts['path'] ?? '', '/'));

// Build candidate base URLs
$current_path = '';
for ($i = 0; $i < count($path_segments); $i++) {
    if (!empty($path_segments[$i])) {
        $current_path .= '/' . $path_segments[$i];
        // Skip 'public' in URL path
        if ($path_segments[$i] !== 'public') {
            $base_url_candidates[] = ($url_parts['scheme'] ?? 'http') . '://' . ($url_parts['host'] ?? 'localhost') . $current_path;
        }
    }
}

echo "POSSIBLE BASE URLS:\n";
foreach ($base_url_candidates as $index => $url) {
    echo ($index + 1) . ". " . $url . "\n";
}
echo "\n";

// File existence checks
echo "FILE EXISTENCE CHECKS:\n";
$files_to_check = [
    'livewire.js' => '/vendor/livewire/livewire.js',
    'script_interceptor.js' => '/script-interceptor.js',
    'livewire_url_fix.js' => '/livewire-url-fix.js',
    'app_blade_php' => '/../resources/views/layouts/app.blade.php',
    'livewire_config' => '/../config/livewire.php',
    'app_service_provider' => '/../app/Providers/AppServiceProvider.php',
];

foreach ($files_to_check as $name => $relative_path) {
    $path = __DIR__ . $relative_path;
    $exists = file_exists($path);
    $size = $exists ? filesize($path) : 0;
    $modified = $exists ? date("Y-m-d H:i:s", filemtime($path)) : 'N/A';
    
    echo "{$name}:\n";
    echo "  Path: {$path}\n";
    echo "  Exists: " . ($exists ? 'Yes' : 'No') . "\n";
    echo "  Size: {$size} bytes\n";
    echo "  Last Modified: {$modified}\n";
    
    // Check if the file is readable
    if ($exists) {
        $is_readable = is_readable($path);
        echo "  Readable: " . ($is_readable ? 'Yes' : 'No') . "\n";
        
        // For script files, check if URL is accessible
        if (in_array($name, ['livewire.js', 'script_interceptor.js', 'livewire_url_fix.js'])) {
            // Create URL to test
            foreach ($base_url_candidates as $base_url) {
                $test_url = $base_url . ($name === 'livewire.js' ? '/vendor/livewire/livewire.js' : '/' . basename($relative_path));
                echo "  URL to test: {$test_url}\n";
                
                // Try to fetch the URL (if curl is available)
                if (function_exists('curl_init')) {
                    $ch = curl_init($test_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_exec($ch);
                    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    echo "  HTTP Response: {$response_code}\n";
                } else {
                    echo "  HTTP Response: Unable to check (curl not available)\n";
                }
            }
        }
    }
    echo "\n";
}

// Check Blade template for @livewireScripts directive
if (file_exists(__DIR__ . '/../resources/views/layouts/app.blade.php')) {
    $app_blade_content = file_get_contents(__DIR__ . '/../resources/views/layouts/app.blade.php');
    
    echo "BLADE TEMPLATE ANALYSIS:\n";
    echo "Contains @livewireStyles: " . (strpos($app_blade_content, '@livewireStyles') !== false ? 'Yes' : 'No') . "\n";
    echo "Contains @livewireScripts: " . (strpos($app_blade_content, '@livewireScripts') !== false ? 'Yes' : 'No') . "\n";
    echo "Contains @fixedLivewireScripts: " . (strpos($app_blade_content, '@fixedLivewireScripts') !== false ? 'Yes' : 'No') . "\n";
    echo "Contains script-interceptor.js: " . (strpos($app_blade_content, 'script-interceptor.js') !== false ? 'Yes' : 'No') . "\n";
    echo "Contains livewire-url-fix.js: " . (strpos($app_blade_content, 'livewire-url-fix.js') !== false ? 'Yes' : 'No') . "\n\n";
}

// Check for problematic URLs in the HTML
echo "HTML ANALYSIS:\n";
echo "To continue with HTML analysis, open this script with ?html=1 parameter and check the browser console.\n\n";

// URL Generator Test function
function testUrlGenerator() {
    $error = null;
    
    try {
        // Try to construct a basic URL generator
        $routes = new \Illuminate\Routing\RouteCollection();
        $request = \Illuminate\Http\Request::createFromGlobals();
        
        $url = new \Illuminate\Routing\UrlGenerator(
            $routes,
            $request
        );
        
        return [
            'success' => true,
            'url' => $url->to('/test'),
            'message' => 'URL generated successfully'
        ];
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// Include suggestions
echo "RECOMMENDATIONS:\n";
echo "1. The URL generator error in CLI suggests issues with how Laravel is bootstrapped in command-line environment.\n";
echo "2. Check your .env file for APP_URL setting - should match one of the base URLs listed above.\n";
echo "3. If using shared hosting, your environment might require additional setup for CLI commands.\n";
echo "4. Livewire script URL issue might be fixed by adding this to your .htaccess file:\n\n";
echo "   # Redirect malformed Livewire script URLs\n";
echo "   RewriteEngine On\n";
echo "   RewriteRule ^public$ /barmada/public/livewire/livewire.js [L,R=301]\n\n";

// HTML output for testing script loading in the browser
if (isset($_GET['html'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Livewire Diagnostics</title>
        <style>
            body { font-family: monospace; padding: 20px; }
            pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
            .test-area { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
            .success { color: green; }
            .error { color: red; }
            button { margin: 5px; padding: 8px 12px; }
            .tag { background: #eef; padding: 3px 5px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <pre><?php echo htmlspecialchars(ob_get_clean()); ?></pre>

        <div class="test-area">
            <h2>Interactive Tests</h2>
            
            <h3>1. Script Loading Test</h3>
            <p>Click buttons to test loading various scripts:</p>
            <button onclick="testScript('/vendor/livewire/livewire.js')">Load /vendor/livewire/livewire.js</button>
            <button onclick="testScript('/barmada/public/vendor/livewire/livewire.js')">Load /barmada/public/vendor/livewire/livewire.js</button>
            <button onclick="testScript('/livewire/livewire.js')">Load /livewire/livewire.js</button>
            <button onclick="testScript('/script-interceptor.js')">Load /script-interceptor.js</button>
            
            <div id="script-test-results"></div>
            
            <h3>2. HTML Head Analysis</h3>
            <p>Scripts in &lt;head&gt; of current page:</p>
            <div id="head-scripts"></div>
            
            <h3>3. URL Structure</h3>
            <p>Current URL components:</p>
            <div id="url-info"></div>
        </div>

        <script>
            // Script loading test
            function testScript(url) {
                console.log('Testing script load:', url);
                const resultDiv = document.getElementById('script-test-results');
                
                const script = document.createElement('script');
                script.onload = function() {
                    resultDiv.innerHTML += `<p class="success">✅ Successfully loaded: ${url}</p>`;
                };
                script.onerror = function() {
                    resultDiv.innerHTML += `<p class="error">❌ Failed to load: ${url}</p>`;
                    console.error('Failed to load script:', url);
                };
                script.src = url;
                document.head.appendChild(script);
            }
            
            // Analyze scripts in head
            function analyzeHead() {
                const scripts = document.head.querySelectorAll('script');
                const headScriptsDiv = document.getElementById('head-scripts');
                
                scripts.forEach((script, index) => {
                    const src = script.getAttribute('src') || '(inline script)';
                    const entry = document.createElement('p');
                    entry.innerHTML = `<span class="tag">${index + 1}</span> ${src}`;
                    headScriptsDiv.appendChild(entry);
                });
            }
            
            // Display URL info
            function displayUrlInfo() {
                const urlDiv = document.getElementById('url-info');
                const url = new URL(window.location.href);
                
                urlDiv.innerHTML = `
                    <p><strong>href:</strong> ${url.href}</p>
                    <p><strong>origin:</strong> ${url.origin}</p>
                    <p><strong>protocol:</strong> ${url.protocol}</p>
                    <p><strong>host:</strong> ${url.host}</p>
                    <p><strong>hostname:</strong> ${url.hostname}</p>
                    <p><strong>port:</strong> ${url.port || '(default)'}</p>
                    <p><strong>pathname:</strong> ${url.pathname}</p>
                    <p><strong>search:</strong> ${url.search}</p>
                    <p><strong>hash:</strong> ${url.hash}</p>
                `;
            }
            
            // Run all tests when page loads
            window.onload = function() {
                analyzeHead();
                displayUrlInfo();
            };
        </script>
    </body>
    </html>
    <?php
}
?> 