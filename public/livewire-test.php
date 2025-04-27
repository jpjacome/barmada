<?php
// Livewire diagnostic test page
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Livewire Test</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .card { border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 15px; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .success { color: green; background-color: #d4edda; padding: 10px; border-radius: 4px; }
        .error { color: red; background-color: #f8d7da; padding: 10px; border-radius: 4px; }
    </style>
    
    <!-- Load scripts synchronously to troubleshoot -->
    <script>
        console.log('Livewire test page loaded');
        
        // Error handling 
        window.addEventListener('error', function(e) {
            console.error('Script error:', e.filename, e.message);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error';
            errorDiv.innerHTML = `<strong>Error:</strong> ${e.message} (in ${e.filename})`;
            document.getElementById('results').appendChild(errorDiv);
        }, true);
    </script>
</head>
<body>
    <h1>Livewire Diagnostic Test</h1>
    <p>This page helps troubleshoot Livewire-related issues.</p>
    
    <div id="results"></div>
    
    <h2>Manual Script Loading Test</h2>
    <div class="card">
        <p>Click each button to test loading a script:</p>
        <button onclick="testScript('js/app.js', 'App.js')">Load app.js</button>
        <button onclick="testScript('livewire-url-fix.js', 'URL Fix')">Load Livewire URL Fix</button>
        <button onclick="testScript('/livewire/livewire.js', 'Livewire.js')">Load Livewire.js (absolute)</button>
        <button onclick="testScript('livewire/livewire.js', 'Livewire.js')">Load Livewire.js (relative)</button>
    </div>
    
    <h2>Browser Information</h2>
    <div class="card">
        <p><strong>URL:</strong> <span id="current-url"></span></p>
        <p><strong>User Agent:</strong> <span id="user-agent"></span></p>
    </div>
    
    <script>
        // Fill browser info
        document.getElementById('current-url').textContent = window.location.href;
        document.getElementById('user-agent').textContent = navigator.userAgent;
        
        // Function to test loading a script
        function testScript(url, name) {
            console.log('Testing script:', url);
            
            const resultDiv = document.createElement('div');
            resultDiv.className = 'card';
            resultDiv.innerHTML = `<strong>Testing:</strong> ${url} (${name})`;
            document.getElementById('results').appendChild(resultDiv);
            
            const script = document.createElement('script');
            script.onload = function() {
                resultDiv.innerHTML += `<p class="success">✅ Successfully loaded ${name}</p>`;
            };
            script.onerror = function() {
                resultDiv.innerHTML += `<p class="error">❌ Failed to load ${name}</p>`;
            };
            script.src = url;
            document.head.appendChild(script);
        }
    </script>
</body>
</html> 