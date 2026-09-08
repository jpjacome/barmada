<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves a locale (es/en — the only two Barmada ships) when there is no
 * venue in scope to ask.
 *
 * Guest routes normally get their locale from the table/session's editor
 * (see the various `guestLocale()` calls in the guest controllers). A
 * handful of routes have no such context at all — most notably the
 * "waiting approval" page reached directly, without a table token, and
 * any operator whose venue has never had a `locale` configured. For those
 * we read the browser's Accept-Language header instead of silently
 * falling back to the framework's English default, since this is an
 * Ecuadorian product: Spanish is the sane default when we genuinely don't
 * know better.
 */
class GuestLocale
{
    /** @var string[] */
    private const SUPPORTED = ['es', 'en'];

    private const DEFAULT = 'es';

    public static function negotiate(Request $request): string
    {
        foreach (self::parseAcceptLanguage((string) $request->header('Accept-Language', '')) as $lang) {
            $primary = strtolower(substr($lang, 0, 2));
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return self::DEFAULT;
    }

    /**
     * Parse an Accept-Language header into language tags ordered by
     * quality (most preferred first). Deliberately hand-rolled rather
     * than relying on Symfony's Request::getLanguages() so behaviour is
     * explicit and easy to unit test.
     *
     * @return string[]
     */
    private static function parseAcceptLanguage(string $header): array
    {
        $header = trim($header);
        if ($header === '') {
            return [];
        }

        $entries = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $pieces = explode(';q=', $part);
            $lang = trim($pieces[0]);
            $quality = isset($pieces[1]) ? (float) $pieces[1] : 1.0;

            if ($lang !== '') {
                $entries[] = ['lang' => $lang, 'q' => $quality];
            }
        }

        usort($entries, fn (array $a, array $b) => $b['q'] <=> $a['q']);

        return array_column($entries, 'lang');
    }
}
