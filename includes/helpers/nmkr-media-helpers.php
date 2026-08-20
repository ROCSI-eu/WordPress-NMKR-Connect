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
 * Validate and normalize the configured IPFS gateway base.
 *
 * @param mixed $base
 * @return string
 */
if (!function_exists('nmkr_normalize_ipfs_gateway_base')) {
    function nmkr_normalize_ipfs_gateway_base($base) {
        if (!is_string($base) || '' === $base || preg_match('/[\x00-\x1F\x7F]/', $base)) return '';
        if (trim($base) !== $base || false === filter_var($base, FILTER_VALIDATE_URL)) return '';

        $parts = parse_url($base);
        if (!is_array($parts) || 'https' !== strtolower(isset($parts['scheme']) ? $parts['scheme'] : '') || empty($parts['host'])) return '';
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return '';

        $origin = 'https://' . $parts['host'];
        if (isset($parts['port'])) $origin .= ':' . $parts['port'];
        $path = isset($parts['path']) ? $parts['path'] : '';
        $path = preg_replace('#(?:/ipfs)+/?$#i', '', rtrim($path, '/'));
        return esc_url_raw($origin . rtrim($path, '/') . '/ipfs/');
    }
}

/**
 * Convert ipfs://, /ipfs/, or bare CIDs to an HTTPS gateway URL.
 * Override base via:
 *   add_filter('nmkr_ipfs_gateway_base', function() { return 'https://gateway.example/ipfs/'; });
 *
 * @param string $url
 * @return string Normalized HTTP(S) URL or empty string
 */
if (!function_exists('nmkr_resolve_ipfs_url')) {
    function nmkr_resolve_ipfs_url($url) {
        if (empty($url)) return '';
        $url = trim((string) $url);

        // Preserve usable direct HTTPS inputs unchanged.
        if (stripos($url, 'https://') === 0) {
            return nmkr_is_valid_https_url($url) ? $url : '';
        }

        // Empty by default: production installations should configure a trusted gateway.
        $base = nmkr_normalize_ipfs_gateway_base(apply_filters('nmkr_ipfs_gateway_base', ''));
        if ('' === $base) return '';

        // Strip common prefixes
        $path = preg_replace('#^ipfs://#i', '', $url);
        $path = preg_replace('#^/ipfs/#i', '', $path);
        $path = ltrim($path, '/');

        // Avoid double slashes
        return esc_url_raw(rtrim($base, '/') . '/' . $path);
    }
}

/** Validate an absolute, credential-free HTTPS URL for public image markup. */
if (!function_exists('nmkr_is_valid_https_url')) {
    function nmkr_is_valid_https_url($url) {
        if (!is_string($url) || '' === $url || trim($url) !== $url || preg_match('/[\x00-\x1F\x7F]/', $url)) return false;
        if (false === filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);
        return is_array($parts) && 'https' === strtolower(isset($parts['scheme']) ? $parts['scheme'] : '') && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
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
 * Check whether a stored IPFS image value has a supported raw shape.
 *
 * @param string $url
 * @return bool
 */
if (!function_exists('nmkr_is_usable_ipfs_image_input')) {
    function nmkr_is_usable_ipfs_image_input( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) return false;

        if ( preg_match( '#^https?://#i', $url ) ) {
            $candidate = esc_url_raw( $url );
            if ( '' === $candidate ) return false;

            $path = (string) parse_url( $candidate, PHP_URL_PATH );
            if ( nmkr_is_probably_image_url( $candidate ) && false === stripos( $path, '/ipfs/' ) ) {
                return true;
            }

            $path = preg_replace( '#^.*?/ipfs/#i', '', $path );
        } else {
            $path = preg_replace( '#^ipfs://#i', '', $url );
            $path = preg_replace( '#^/ipfs/#i', '', $path );
        }

        $path = ltrim( (string) $path, '/' );
        if ( 0 === stripos( $path, 'ipfs/' ) ) {
            $path = substr( $path, 5 );
        }

        $cid = strtok( $path, '/' );
        return is_string( $cid ) && (
            1 === preg_match( '/^Qm[1-9A-HJ-NP-Za-km-z]{44}$/', $cid ) ||
            1 === preg_match( '/^b[a-z2-7]{45,}$/i', $cid ) ||
            1 === preg_match( '/^z[1-9A-HJ-NP-Za-km-z]{40,}$/', $cid ) ||
            1 === preg_match( '/^k[0-9a-z]{45,}$/', $cid )
        );
    }
}

/**
 * Build the finite token-image candidate set.
 * $token is t.* + td.* from JOIN of nmkr_tokens (t) and nmkr_token_details (td).
 *
 * Priority:
 *   1) t.gateway_link, preserved unchanged
 *   2) t.ipfs_link through a configured gateway
 *   3) t.metadata.image
 *
 * @param object $t
 * @return array Primary, optional fallback, and packaged placeholder URLs
 */
if (!function_exists('nmkr_get_token_image_candidates')) {
    function nmkr_get_token_image_candidates($t) {
        $remote = array();
        if (isset($t->gateway_link) && is_string($t->gateway_link)) {
            $provider = trim($t->gateway_link);
            if (nmkr_is_valid_https_url($provider)) $remote[] = $provider;
        }
        if (count($remote) < 2 && isset($t->ipfs_link) && is_string($t->ipfs_link) && nmkr_is_usable_ipfs_image_input($t->ipfs_link)) {
            $ipfs = nmkr_resolve_ipfs_url(trim($t->ipfs_link));
            if (nmkr_is_valid_https_url($ipfs) && !in_array($ipfs, $remote, true)) $remote[] = $ipfs;
        }
        if (count($remote) < 2 && isset($t->metadata->image) && is_string($t->metadata->image)) {
            $metadata = nmkr_resolve_ipfs_url(trim($t->metadata->image));
            if (nmkr_is_valid_https_url($metadata) && !in_array($metadata, $remote, true)) $remote[] = $metadata;
        }
        $placeholder = plugins_url('images/placeholder.png', NMKR_CONNECT_PLUGIN_FILE);
        return array('primary' => isset($remote[0]) ? $remote[0] : $placeholder, 'fallback' => isset($remote[1]) ? $remote[1] : '', 'placeholder' => $placeholder);
    }
}

if (!function_exists('nmkr_get_token_image_url')) {
    function nmkr_get_token_image_url($t) {
        $candidates = nmkr_get_token_image_candidates($t);
        return $candidates['primary'];
    }
}

/** Render shared token image markup and enqueue its finite fallback handler. */
if (!function_exists('nmkr_get_token_image_markup')) {
    function nmkr_get_token_image_markup($t, $alt, $class, $attributes = '') {
        $sources = nmkr_get_token_image_candidates($t);
        $script = 'js/nmkr-token-image-fallback.js';
        wp_enqueue_script('nmkr-token-image-fallback', plugins_url($script, NMKR_CONNECT_PLUGIN_FILE), array(), @filemtime(plugin_dir_path(NMKR_CONNECT_PLUGIN_FILE) . $script) ?: '1.0', true);
        return '<img src="' . esc_url($sources['primary']) . '" alt="' . esc_attr($alt) . '" class="' . esc_attr($class) . '" loading="lazy" decoding="async" onclick="openLightbox(this.src)" data-nmkr-token-image="1"'
            . ('' !== $sources['fallback'] ? ' data-nmkr-fallback-src="' . esc_url($sources['fallback']) . '"' : '')
            . ' data-nmkr-placeholder-src="' . esc_url($sources['placeholder']) . '" ' . $attributes . ' />';
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
