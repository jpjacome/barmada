/**
 * Livewire URL fix
 * Fixes URL issues with Livewire in shared hosting environments
 */

console.log('Livewire URL fix loaded');

// Run immediately when the script loads
(function() {
    console.log('Patching Livewire URL handling');
    
    // Fix Livewire script loading
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a small amount of time to ensure all scripts are processed
        setTimeout(function() {
            // Find the malformed Livewire script (the one with src="https://orustravel.org/barmada/public")
            const scripts = document.querySelectorAll('script');
            scripts.forEach(script => {
                const src = script.getAttribute('src');
                if (src && !src.endsWith('.js') && 
                    (src.endsWith('/public') || src === 'public' || src.includes('/barmada/public'))) {
                    console.log('Found malformed Livewire script: ' + src);
                    
                    // Save the original attributes
                    const csrf = script.getAttribute('data-csrf');
                    const updateUri = script.getAttribute('data-update-uri');
                    const navigateOnce = script.getAttribute('data-navigate-once');
                    
                    // Create a new correctly formed script
                    const newScript = document.createElement('script');
                    // Correct the script URL to point to Livewire JS file
                    newScript.src = src + '/livewire/livewire.js';
                    
                    // Copy the attributes
                    if (csrf) newScript.setAttribute('data-csrf', csrf);
                    if (updateUri) newScript.setAttribute('data-update-uri', updateUri);
                    if (navigateOnce) newScript.setAttribute('data-navigate-once', navigateOnce);
                    
                    // Replace the old script
                    if (script.parentNode) {
                        script.parentNode.insertBefore(newScript, script);
                        script.parentNode.removeChild(script);
                        console.log('Replaced malformed Livewire script with corrected version');
                    }
                }
            });
            
            console.log('Livewire script check completed');
        }, 100);
    });
    
    console.log('Livewire patched successfully');
})(); 