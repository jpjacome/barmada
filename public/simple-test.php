<?php
// Simple test to check if JS files are being served correctly
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple JS Test</title>
</head>
<body>
    <h1>Simple JavaScript Test</h1>
    <p>This page loads JavaScript files using different URL patterns and checks which ones work.</p>
    
    <div id="results"></div>
    
    <script>
        // Create results container
        const results = document.getElementById('results');
        
        // Check if a script loads successfully
        function testScript(name, url) {
            console.log('Testing:', url);
            
            const script = document.createElement('script');
            script.src = url;
            
            // Create result element
            const resultEl = document.createElement('div');
            resultEl.style.margin = '10px 0';
            resultEl.style.padding = '10px';
            resultEl.style.border = '1px solid #ccc';
            
            // Show the URL being tested
            resultEl.innerHTML = '<strong>Testing URL:</strong> ' + url;
            results.appendChild(resultEl);
            
            // Set up load and error handlers
            script.onload = function() {
                resultEl.style.backgroundColor = '#d4edda';
                resultEl.innerHTML += '<br><span style="color:green">✓ Success: Script loaded correctly</span>';
                console.log('Success:', url);
                
                // If this is the js-copy.js test, try to call the function
                if (url.indexOf('js-copy.js') !== -1) {
                    try {
                        if (typeof jsTestLoaded === 'function') {
                            jsTestLoaded();
                        }
                    } catch (e) {
                        console.error("Couldn't call jsTestLoaded function:", e);
                    }
                }
            };
            
            script.onerror = function() {
                resultEl.style.backgroundColor = '#f8d7da';
                resultEl.innerHTML += '<br><span style="color:red">✗ Error: Failed to load script</span>';
                console.log('Error:', url);
            };
            
            // Add the script to the page
            document.head.appendChild(script);
        }
        
        // Test different URL patterns
        window.onload = function() {
            // Get base location information
            const origin = window.location.origin;
            const path = window.location.pathname.replace('simple-test.php', '');
            
            // Display the base URL
            const infoEl = document.createElement('p');
            infoEl.innerHTML = '<strong>Base URL:</strong> ' + origin + '<br>' +
                              '<strong>Current Path:</strong> ' + path;
            results.appendChild(infoEl);
            
            // Add server info
            const serverInfoEl = document.createElement('div');
            serverInfoEl.style.margin = '10px 0';
            serverInfoEl.style.padding = '10px';
            serverInfoEl.style.backgroundColor = '#f8f9fa';
            serverInfoEl.innerHTML = '<strong>Server Information:</strong><br>' +
                                    'User Agent: ' + navigator.userAgent + '<br>' +
                                    'Page URL: ' + window.location.href;
            results.appendChild(serverInfoEl);
            
            // Create a testing section
            const testingSection = document.createElement('h2');
            testingSection.textContent = 'Testing app.js';
            results.appendChild(testingSection);
            
            // Test various URL formats for app.js
            testScript('Direct URL', origin + '/js/app.js');
            testScript('Barmada Public', origin + '/barmada/public/js/app.js');
            testScript('Barmada URL', origin + '/barmada/js/app.js');
            testScript('Relative URL', 'js/app.js');
            testScript('Based on current path', origin + path + 'js/app.js');
            
            // Test js-copy.js (should be more reliable)
            const copySectionTitle = document.createElement('h2');
            copySectionTitle.textContent = 'Testing js-copy.js';
            copySectionTitle.style.marginTop = '30px';
            results.appendChild(copySectionTitle);
            
            testScript('JS Copy Test - Direct URL', origin + '/js-copy.js');
            testScript('JS Copy Test - Barmada Public', origin + '/barmada/public/js-copy.js');
            testScript('JS Copy Test - Relative', 'js-copy.js');
            testScript('JS Copy Test - Current path', origin + path + 'js-copy.js');
        };
    </script>
    
    <style>
        body { 
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1, h2 {
            color: #333;
        }
        
        h2 {
            margin-top: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
    </style>
</body>
</html> 