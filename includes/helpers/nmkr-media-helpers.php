<?php
/**
 * NMKR Connect — Media helpers
 *
 * Normalizes IPFS-style URLs to HTTP(S) and picks the best token image URL
 * from stored DB fields (no remote calls).
 */
if (!defined('ABSPATH')) { exit; }

/** Internal: polyfill-like suffix check for PHP 7.4 */
if (!function_exists('nmkr_str_ends_with')) {
    function nmkr_str_ends_with($haystack, $needle) {
        $haystack = (string) $haystack;
        $needle   = (string) $needle;
        if ($needle === '') return true;
        $hlen = strlen($haystack);
        $nlen = strlen($needle);
        if ($nlen > $hlen) return false;
        return substr($haystack, -$nlen) === $needle;
    }
}

/**
 * Convert ipfs://, /ipfs/, or bare CIDs to an HTTP(S) gateway URL.
 * Override base via:
 *   add_filter('nmkr_ipfs_gateway_base', function() { return 'https://ipfs.io/ipfs/'; });
 *
 * @param string $url
 * @return string Normalized HTTP(S) URL or empty string
 */
if (!function_exists('nmkr_resolve_ipfs_url')) {
    function nmkr_resolve_ipfs_url($url) {
        if (empty($url)) return '';
        $url = trim((string) $url);

        // Already http(s)
        if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
            return esc_url_raw($url);
        }

        // Configurable gateway base; ensure it ends with /ipfs/
        $base = apply_filters('nmkr_ipfs_gateway_base', 'https://ipfs.io/ipfs/');
        if (!nmkr_str_ends_with($base, '/ipfs/')) {
            $base = rtrim($base, '/') . '/ipfs/';
        }

        // Strip common prefixes
        $path = preg_replace('#^ipfs://#i', '', $url);
        $path = preg_replace('#^/ipfs/#i', '', $path);
        $path = ltrim($path, '/');

        // Avoid double slashes
        return esc_url_raw(rtrim($base, '/') . '/' . $path);
    }
}

/**
 * Heuristic: does URL look like an image (by path only)?
 * No network calls; conservative.
 *
 * @param string $url
 * @return bool
 */
if (!function_exists('nmkr_is_probably_image_url')) {
    function nmkr_is_probably_image_url($url) {
        if (empty($url)) return false;
        $path = parse_url($url, PHP_URL_PATH);
        $path = strtolower((string) $path);

        if ($path === '') return false;

        $exts = array('.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg');
        foreach ($exts as $ext) {
            if (nmkr_str_ends_with($path, $ext)) {
                return true;
            }
        }
        // Gateways often omit extensions; accept obvious IPFS paths.
        return (strpos($path, '/ipfs/') !== false);
    }
}

/**
 * Pick the best token image URL from stored fields, normalized to HTTP(S).
 * $token is t.* + td.* from JOIN of nmkr_tokens (t) and nmkr_token_details (td).
 *
 * Priority:
 *   1) t.ipfs_link
 *   2) t.gateway_link (with IPFS path extraction)
 *   3) t.metadata.image
 *
 * @param object $t
 * @return string Normalized image URL or '' if none
 */
if (!function_exists('nmkr_get_token_image_url')) {
    function nmkr_get_token_image_url( $t ) {
        $url = '';

        // 1) Prefer the canonical IPFS source so it uses the configured gateway.
        if ( isset( $t->ipfs_link ) && is_string( $t->ipfs_link ) ) {
            $ipfs_link = trim( $t->ipfs_link );
            $candidate = '' !== $ipfs_link ? nmkr_resolve_ipfs_url( $ipfs_link ) : '';

            if ( nmkr_is_probably_image_url( $candidate ) ) {
                $url = $candidate;
            }
        }

        // 2) Fall back to gateway_link if no usable ipfs_link is present.
        if ( empty( $url ) && ! empty( $t->gateway_link ) && is_string( $t->gateway_link ) ) {
            $gw   = trim( $t->gateway_link );
            $path = parse_url( $gw, PHP_URL_PATH );

            // Strict match: /ipfs|ipns/<cid>[/rest]
            if ( is_string( $path ) && preg_match('~/(ipfs|ipns)/([A-Za-z0-9]+)(/.*)?$~', $path, $m) ) {
                $ns   = $m[1];                // ipfs|ipns
                $cid  = $m[2];
                $rest = isset($m[3]) ? $m[3] : '';
                $url  = nmkr_resolve_ipfs_url( $ns . '://' . $cid . $rest );
            } else {
                // Fallback: hunt for a CID anywhere in the URL and re-base to the configured gateway
                if ( preg_match('~(Qm[1-9A-HJ-NP-Za-km-z]{44,})~', $gw, $m) ) {
                    // CIDv0 (base58btc)
                    $url = nmkr_resolve_ipfs_url( 'ipfs://' . $m[1] );
                } elseif ( preg_match('~([a-z0-9]{46,})~', $gw, $m) ) {
                    // very loose CIDv1 (base32) heuristic
                    $url = nmkr_resolve_ipfs_url( 'ipfs://' . $m[1] );
                } else {
                    // last resort: use gateway_link as-is (non-standard provider path)
                    $url = $gw;
                }
            }
        }

        // 3) Then metadata.image (ipfs://, CID, or https).
        if ( empty( $url ) && ! empty( $t->metadata->image ) ) {
            $url = nmkr_resolve_ipfs_url( $t->metadata->image );
        }

        // 4) Return trimmed; caller will esc_url() on output.
        return is_string( $url ) ? trim( $url ) : '';
    }
}

/**
 * Optional: normalize a project logo if it ever becomes an IPFS URL.
 *
 * @param object $project
 * @return string
 */
if (!function_exists('nmkr_get_project_logo_url')) {
    function nmkr_get_project_logo_url($project) {
        if (empty($project->project_logo)) return '';
        return nmkr_resolve_ipfs_url($project->project_logo);
    }
}
