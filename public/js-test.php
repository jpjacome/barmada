<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>JavaScript Loading Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .result { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>JavaScript Loading Test</h1>
    
    <div id="results"></div>
    
    <script>
        // Function to log results
        function logResult(message, success) {
            const resultsDiv = document.getElementById('results');
            const resultDiv = document.createElement('div');
            resultDiv.className = 'result ' + (success ? 'success' : 'error');
            resultDiv.textContent = message;
            resultsDiv.appendChild(resultDiv);
        }
        
        // Function to test loading a script
        function testScript(url) {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.onload = () => resolve({ url, success: true });
                script.onerror = () => resolve({ url, success: false });
                script.src = url;
                document.head.appendChild(script);
            });
        }
        
        // Test different URL patterns
        async function runTests() {
            logResult('Starting tests...', true);
            
            const baseUrl = window.location.origin;
            const tests = [
                // Direct JS access with different paths
                baseUrl + '/js/app.js',
                baseUrl + '/barmada/public/js/app.js',
                baseUrl + '/barmada/js/app.js',
                
                // Through absolute URL
                '<?php echo url('/js/app.js'); ?>',
                
                // Through asset helper
                '<?php echo asset('js/app.js'); ?>'
            ];
            
            logResult('Base URL: ' + baseUrl, true);
            
            for (const testUrl of tests) {
                try {
                    const result = await testScript(testUrl);
                    if (result.success) {
                        logResult('✅ Success: ' + result.url, true);
                    } else {
                        logResult('❌ Failed: ' + result.url, false);
                    }
                } catch (error) {
                    logResult('❌ Error: ' + testUrl + ' - ' + error.message, false);
                }
            }
            
            logResult('All tests completed', true);
        }
        
        // Run the tests
        runTests();
    </script>
    
    <h2>Server Information</h2>
    <pre>
Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?>
Script Path: <?php echo __FILE__; ?>
URL: <?php echo url('/js/app.js'); ?>
Asset: <?php echo asset('js/app.js'); ?>
    </pre>
    
    <?php if (file_exists(__DIR__ . '/js/app.js')): ?>
        <p class="result success">app.js exists in the correct location.</p>
    <?php else: ?>
        <p class="result error">app.js doesn't exist at expected location: <?php echo __DIR__ . '/js/app.js'; ?></p>
    <?php endif; ?>
</body>
</html> 