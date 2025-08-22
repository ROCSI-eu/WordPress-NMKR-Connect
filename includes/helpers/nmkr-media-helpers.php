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
        $base = apply_filters('nmkr_ipfs_gateway_base', 'https://cloudflare-ipfs.com/ipfs/');
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
 *   2) td.metadata: image | image_url | imageUrl | media[0].src
 *   3) t.gateway_link if it looks like an image
 *
 * Filters:
 *   nmkr_token_image_url( string $url, object $token )
 *
 * @param object $token
 * @return string Normalized image URL or '' if none
 */
if (!function_exists('nmkr_get_token_image_url')) {
    function nmkr_get_token_image_url($token) {
        // 1) direct ipfs_link
        if (!empty($token->ipfs_link)) {
            $u = nmkr_resolve_ipfs_url($token->ipfs_link);
            if (!empty($u)) {
                return apply_filters('nmkr_token_image_url', $u, $token);
            }
        }

        // 2) metadata fallbacks
        if (!empty($token->metadata)) {
            $meta = json_decode($token->metadata, true);
            // If JSON failed to decode but the field is a simple string URL, try it
            if ($meta === null && json_last_error() !== JSON_ERROR_NONE) {
                $maybeUrl = trim((string) $token->metadata);
                if ($maybeUrl !== '') {
                    $u = nmkr_resolve_ipfs_url($maybeUrl);
                    if (!empty($u)) {
                        return apply_filters('nmkr_token_image_url', $u, $token);
                    }
                }
            } elseif (is_array($meta)) {
                $candidates = array();
                if (isset($meta['image']))                $candidates[] = $meta['image'];
                if (isset($meta['image_url']))            $candidates[] = $meta['image_url'];
                if (isset($meta['imageUrl']))             $candidates[] = $meta['imageUrl'];
                if (isset($meta['media'][0]['src']))      $candidates[] = $meta['media'][0]['src'];

                foreach ($candidates as $cand) {
                    if (!empty($cand)) {
                        $u = nmkr_resolve_ipfs_url($cand);
                        if (!empty($u)) {
                            return apply_filters('nmkr_token_image_url', $u, $token);
                        }
                    }
                }
            }
        }

        // 3) last resort: gateway_link that looks like an image
        if (!empty($token->gateway_link)) {
            $gl = esc_url_raw($token->gateway_link);
            if (nmkr_is_probably_image_url($gl)) {
                return apply_filters('nmkr_token_image_url', $gl, $token);
            }
        }

        return apply_filters('nmkr_token_image_url', '', $token);
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
