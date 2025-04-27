/**
 * Script request interceptor
 * This script intercepts attempts to load problematic scripts like 'public'
 */

(function() {
    console.log("Script interceptor loaded");
    
    // Log environment information to help diagnose URL issues
    console.log("Page URL:", window.location.href);
    console.log("Page origin:", window.location.origin);
    console.log("Page pathname:", window.location.pathname);
    
    // Try to determine the correct base URL for the application
    const pathSegments = window.location.pathname.split('/');
    const possibleBasePaths = [];
    let currentPath = '';
    
    for (let i = 1; i < pathSegments.length; i++) {
        if (pathSegments[i]) {
            currentPath += '/' + pathSegments[i];
            possibleBasePaths.push(currentPath);
        }
    }
    
    console.log("Possible application base paths:", possibleBasePaths);
    
    // Store original methods
    const originalCreateElement = document.createElement;
    const originalSetAttribute = Element.prototype.setAttribute;
    const originalAppendChild = Node.prototype.appendChild;
    
    // List of problematic script sources
    const problematicSources = [
        'public',
        '/public',
        './public',
        '../public'
    ];
    
    // Debug helper to get stack trace
    function getStackTrace() {
        try {
            throw new Error();
        } catch (e) {
            return e.stack;
        }
    }
    
    // Log script loading details
    function logScriptDetails(scriptElement, action, extraInfo = '') {
        if (!scriptElement || scriptElement.tagName !== 'SCRIPT') return;
        
        const src = scriptElement.src || '(inline script)';
        console.warn(`Script ${action}: ${src} ${extraInfo}`);
        console.log('Stack trace:', getStackTrace());
        
        // Log all attributes
        if (scriptElement.attributes && scriptElement.attributes.length > 0) {
            console.log('Script attributes:', Array.from(scriptElement.attributes).map(a => `${a.name}="${a.value}"`).join(', '));
        }
    }
    
    // Override createElement to monitor script creation
    document.createElement = function(tagName, options) {
        const element = originalCreateElement.call(document, tagName, options);
        if (tagName.toLowerCase() === 'script') {
            logScriptDetails(element, 'created');
            
            // Monkey patch this specific script element's setAttribute method
            const originalElementSetAttribute = element.setAttribute;
            element.setAttribute = function(name, value) {
                if (name === 'src') {
                    logScriptDetails(this, 'setAttribute', `${name}="${value}"`);
                    
                    // Check if this is a problematic source
                    if (problematicSources.some(badSrc => value === badSrc || value.endsWith('/' + badSrc))) {
                        console.error(`Intercepted attempt to load problematic script: ${value}`);
                        // Return without setting the attribute
                        return;
                    }
                }
                return originalElementSetAttribute.call(this, name, value);
            };
        }
        return element;
    };
    
    // Override setAttribute globally
    Element.prototype.setAttribute = function(name, value) {
        if (this.tagName === 'SCRIPT' && name === 'src') {
            logScriptDetails(this, 'setAttribute global', `${name}="${value}"`);
            
            // Check if this is a problematic source
            if (problematicSources.some(badSrc => value === badSrc || value.endsWith('/' + badSrc))) {
                console.error(`Intercepted attempt to load problematic script: ${value}`);
                // Return without setting the attribute
                return;
            }
        }
        return originalSetAttribute.call(this, name, value);
    };
    
    // Override appendChild to check for problematic script sources
    Node.prototype.appendChild = function(child) {
        if (child.tagName === 'SCRIPT') {
            logScriptDetails(child, 'appending to DOM');
            
            // Check for problematic sources
            const src = child.src || '';
            if (problematicSources.some(badSrc => src === badSrc || src.endsWith('/' + badSrc) || src.includes(badSrc + '?'))) {
                console.error(`Blocked problematic script from being appended to DOM: ${src}`);
                
                // Create a fake script element that does nothing
                const fakeScript = originalCreateElement.call(document, 'script');
                fakeScript.type = 'text/javascript';
                fakeScript.textContent = '/* Intercepted problematic script */';
                
                // We still need to trigger any load events that might be expected
                setTimeout(() => {
                    if (typeof child.onload === 'function') {
                        child.onload();
                    }
                    // Dispatch a load event
                    const event = new Event('load');
                    child.dispatchEvent(event);
                }, 10);
                
                return fakeScript; // Return fake script to prevent errors
            }
        }
        return originalAppendChild.call(this, child);
    };
    
    // Fix for broken script loading
    const originalLoadScript = window.loadScript;
    if (typeof originalLoadScript === 'function') {
        window.loadScript = function(src, callback) {
            if (problematicSources.some(badSrc => src === badSrc || src.endsWith('/' + badSrc))) {
                console.error(`Intercepted loadScript attempt for problematic script: ${src}`);
                if (typeof callback === 'function') {
                    setTimeout(callback, 10);
                }
                return;
            }
            return originalLoadScript.call(window, src, callback);
        };
    }
    
    // Also patch fetch for dynamic script loading
    const originalFetch = window.fetch;
    window.fetch = function() {
        const url = arguments[0]?.url || arguments[0];
        if (typeof url === 'string') {
            if (problematicSources.some(badSrc => url === badSrc || url.endsWith('/' + badSrc))) {
                console.error(`Intercepted fetch attempt for problematic URL: ${url}`);
                return Promise.resolve(new Response('/* Intercepted problematic URL */', {
                    status: 200,
                    headers: {'Content-Type': 'application/javascript'}
                }));
            }
        }
        return originalFetch.apply(this, arguments);
    };
    
    // Add a global error handler to provide more details
    window.addEventListener('error', function(event) {
        if (event.filename && (
            event.filename.endsWith('/public') || 
            event.filename === 'public' || 
            event.filename.includes('/livewire/livewire.js')
        )) {
            console.group('Detailed Script Error Information');
            console.error('Error loading script:', event.filename);
            console.error('Error message:', event.message);
            console.error('Error line:', event.lineno);
            console.error('Error column:', event.colno);
            console.error('Meta public URL:', document.querySelector('meta[name="public-url-check"]')?.content);
            console.error('Meta livewire asset path:', document.querySelector('meta[name="livewire-asset-path"]')?.content);
            console.groupEnd();
        }
    }, true);
    
    console.log("Script interceptor initialized and ready");
})(); 