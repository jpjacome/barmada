<?php

namespace App\Support;

/**
 * Lightweight active-content gate for uploaded SVG icons (not a full
 * sanitizer): blocks scripts, event handlers, external entities and
 * embeddings that could execute if the file were opened directly in the
 * browser. Shared by the web product form and the API.
 */
class SafeSvg
{
    public static function check(string $svg): bool
    {
        $haystack = strtolower($svg);
        if (! str_contains($haystack, '<svg')) {
            return false;
        }

        $blocked = [
            '<script', 'javascript:', '<foreignobject', '<iframe',
            '<embed', '<object', '<!entity',
        ];
        foreach ($blocked as $needle) {
            if (str_contains($haystack, $needle)) {
                return false;
            }
        }

        // Any inline event handler such as onload= / onclick=.
        if (preg_match('/\son[a-z]+\s*=/i', $svg)) {
            return false;
        }

        return true;
    }
}
