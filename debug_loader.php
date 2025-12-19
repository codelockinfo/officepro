<?php
/**
 * Debug Loader Issue
 * This page helps identify what's causing continuous loading
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Loader - OfficePro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .debug-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #667eea; }
        h2 { color: #333; margin-top: 0; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #5568d3; }
        #console-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <h1>🔍 Loader Debug Tool</h1>
    
    <div class="debug-section">
        <h2>1. Check JavaScript Console</h2>
        <p>Open browser console (F12) and check for errors.</p>
        <div id="console-output">Waiting for console messages...</div>
    </div>
    
    <div class="debug-section">
        <h2>2. Test AJAX Request</h2>
        <button onclick="testAjax()">Test AJAX Request</button>
        <button onclick="testLoader()">Test Loader</button>
        <button onclick="clearLoader()">Force Hide Loader</button>
        <div id="ajax-result"></div>
    </div>
    
    <div class="debug-section">
        <h2>3. Check Page Resources</h2>
        <div id="resources-status"></div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Capture console messages
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;
        const output = document.getElementById('console-output');
        
        function addToOutput(type, ...args) {
            const timestamp = new Date().toLocaleTimeString();
            const message = args.map(arg => 
                typeof arg === 'object' ? JSON.stringify(arg, null, 2) : String(arg)
            ).join(' ');
            output.textContent += `[${timestamp}] [${type}] ${message}\n`;
            output.scrollTop = output.scrollHeight;
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToOutput('LOG', ...args);
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addToOutput('ERROR', ...args);
        };
        
        console.warn = function(...args) {
            originalWarn.apply(console, args);
            addToOutput('WARN', ...args);
        };
        
        // Test AJAX
        function testAjax() {
            const resultDiv = document.getElementById('ajax-result');
            resultDiv.innerHTML = '<div class="status info">Testing AJAX...</div>';
            
            ajaxRequest('/public_html/app/api/attendance/status.php', 'GET', null, 
                (response) => {
                    resultDiv.innerHTML = '<div class="status success">AJAX Success: ' + JSON.stringify(response) + '</div>';
                },
                (error) => {
                    resultDiv.innerHTML = '<div class="status error">AJAX Error: ' + error.message + '</div>';
                }
            );
        }
        
        // Test Loader
        function testLoader() {
            showLoader();
            setTimeout(() => {
                hideLoader();
                document.getElementById('ajax-result').innerHTML = '<div class="status success">Loader test completed</div>';
            }, 2000);
        }
        
        // Force hide loader
        function clearLoader() {
            hideLoader();
            const loader = document.getElementById('global-loader');
            if (loader) {
                loader.remove();
            }
            document.getElementById('ajax-result').innerHTML = '<div class="status success">Loader forcefully hidden</div>';
        }
        
        // Check resources
        window.addEventListener('load', function() {
            const resourcesDiv = document.getElementById('resources-status');
            const scripts = Array.from(document.querySelectorAll('script[src]')).map(s => s.src);
            const stylesheets = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href);
            
            let html = '<h3>Loaded Scripts:</h3><ul>';
            scripts.forEach(src => {
                html += '<li>' + src + '</li>';
            });
            html += '</ul><h3>Loaded Stylesheets:</h3><ul>';
            stylesheets.forEach(href => {
                html += '<li>' + href + '</li>';
            });
            html += '</ul>';
            
            resourcesDiv.innerHTML = html;
        });
        
        // Check for loader on page load
        window.addEventListener('load', function() {
            setTimeout(() => {
                const loader = document.getElementById('global-loader');
                if (loader && loader.style.display !== 'none') {
                    console.warn('Loader is still visible after page load!');
                    addToOutput('WARN', 'Loader detected - attempting to hide...');
                    hideLoader();
                }
            }, 1000);
        });
        
        // Monitor for stuck loader
        setInterval(() => {
            const loader = document.getElementById('global-loader');
            if (loader && loader.style.display !== 'none') {
                const loaderAge = Date.now() - (window.loaderStartTime || Date.now());
                if (loaderAge > 10000) { // 10 seconds
                    console.warn('Loader has been visible for more than 10 seconds!');
                    addToOutput('WARN', 'Stuck loader detected - auto-hiding...');
                    hideLoader();
                }
            }
        }, 5000);
    </script>
</body>
</html>

