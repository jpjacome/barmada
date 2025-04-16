@php
    $theme = session('theme', 'light');
    $logoPath = 'images/logo-' . $theme . '.svg';
    
    // Check if the theme-specific SVG exists
    if (!file_exists(public_path($logoPath))) {
        // Check for theme-specific PNG
        $pngPath = 'images/logo-' . $theme . '.png';
        if (file_exists(public_path($pngPath))) {
            $logoPath = $pngPath;
        } else {
            // Fall back to original file naming as last resort
            $originalPath = ($theme === 'light') ? 'images/logo.svg' : 'images/logowhite.svg';
            if (file_exists(public_path($originalPath))) {
                $logoPath = $originalPath;
            }
        }
    }
@endphp
<img src="{{ asset($logoPath) }}" {{ $attributes }} alt="Golems Bar Logo">
