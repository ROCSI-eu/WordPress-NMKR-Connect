<?php
/**
 * NMKR Connect — Analytics Admin AJAX
 * Endpoints:
 *  - nmkr_analytics_kpis
 *  - nmkr_analytics_timeseries
 */

if (!defined('ABSPATH')) { exit; }

// Register endpoints
add_action('wp_ajax_nmkr_analytics_kpis', 'nmkr_analytics_kpis_ajax');
add_action('wp_ajax_nmkr_analytics_timeseries', 'nmkr_analytics_timeseries_ajax');

/** ---------- Helpers (file-local) ---------- */

function nmkr_analytics_bool($v) { return !empty($v) && $v !== '0' && $v !== 'false'; }

/** Sanitize shortcode type (or return '') */
function nmkr_analytics_sanitize_shortcode_type($v) {
    $v = strtolower(sanitize_text_field((string)$v));
    $allowed = array('grid','list','carousel','token','project');
    return in_array($v, $allowed, true) ? $v : '';
}

/** Sanitize UID (project/token) → keep [A-Za-z0-9-_], max 64 */
function nmkr_analytics_sanitize_uid($v) {
    $v = preg_replace('/[^0-9a-zA-Z\-_]/', '', (string)$v);
    return substr($v, 0, 64);
}

/** Parse range & bucket */
function nmkr_analytics_parse_range_and_bucket($req) {
    $range  = isset($req['range'])  ? strtolower( sanitize_text_field( wp_unslash( $req['range'] ) ) )  : '7d';
    $bucket = isset($req['bucket']) ? strtolower( sanitize_text_field( wp_unslash( $req['bucket'] ) ) ) : 'day';

    $now_ts = current_time('timestamp'); // site-local
    $from_ts = $now_ts - 7 * DAY_IN_SECONDS;
    $to_ts   = $now_ts;

    if ($range === '24h')       { $from_ts = $now_ts - DAY_IN_SECONDS; }
    elseif ($range === '7d')    { $from_ts = $now_ts - 7 * DAY_IN_SECONDS; }
    elseif ($range === '30d')   { $from_ts = $now_ts - 30 * DAY_IN_SECONDS; }
    elseif ($range === 'custom') {
        $from_raw = isset($req['from']) ? sanitize_text_field( wp_unslash( $req['from'] ) ) : '';
        $to_raw   = isset($req['to'])   ? sanitize_text_field( wp_unslash( $req['to'] ) )   : '';
        $from_ts = strtotime($from_raw) ?: $from_ts;
        $to_ts   = strtotime($to_raw)   ?: $to_ts;
        if ($from_ts > $to_ts) { $tmp = $from_ts; $from_ts = $to_ts; $to_ts = $tmp; }
    }

    // Clamp to maximum span of 365 days
    $max_span = 365 * DAY_IN_SECONDS;
    if (($to_ts - $from_ts) > $max_span) {
        $from_ts = $to_ts - $max_span;
    }

    // Auto-bucket: if span ≤ 72h and bucket not explicitly set to 'day', use 'hour'
    $span = max(0, $to_ts - $from_ts);
    if (!isset($req['bucket']) && $span <= 3 * DAY_IN_SECONDS) {
        $bucket = 'hour';
    }
    if (!in_array($bucket, array('hour','day'), true)) {
        $bucket = ($span <= 3 * DAY_IN_SECONDS) ? 'hour' : 'day';
    }

    return array(
        'from_ts' => $from_ts,
        'to_ts'   => $to_ts,
        'from'    => date_i18n('Y-m-d H:i:s', $from_ts),
        'to'      => date_i18n('Y-m-d H:i:s', $to_ts),
        'bucket'  => $bucket,
        'range'   => $range,
    );
}

/** Build WHERE + params for analytics filters */
function nmkr_analytics_build_where($filters) {
    $where  = array('event_ts BETWEEN %s AND %s');
    $params = array($filters['from'], $filters['to']);

    if (!empty($filters['shortcode_type'])) {
        $where[]  = 'shortcode_type = %s';
        $params[] = $filters['shortcode_type'];
    }
    if (!empty($filters['project_uid'])) {
        $where[]  = 'project_uid = %s';
        $params[] = $filters['project_uid'];
    }
    if (!empty($filters['token_uid'])) {
        $where[]  = 'token_uid = %s';
        $params[] = $filters['token_uid'];
    }

    return array(' WHERE ' . implode(' AND ', $where) . ' ', $params);
}

/** Transient cache helpers */
function nmkr_analytics_cache_key($endpoint, $payload) {
    $blog = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
    return 'nmkr_analytics:' . $blog . ':' . $endpoint . ':' . sha1(wp_json_encode($payload));
}
function nmkr_analytics_cache_ttl() {
    return 90; // seconds (short; analytics are near-real-time)
}
function nmkr_analytics_debug_on() {
    $opts = get_option('nmkr_connect_options', array());
    return !empty($opts['analytics_debug']);
}

/** ---------- Endpoints ---------- */

/**
 * KPIs: views, clicks, ctr for a range
 * Params: range (24h|7d|30d|custom), from, to, shortcode_type, project_uid, token_uid, _ (cache buster)
 */
function nmkr_analytics_kpis_ajax() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error(array('message' => 'Method not allowed'), 405);
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nmkr_dashboard_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Forbidden'), 403);
    }

    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    // Avoid stray output corrupting JSON
    $__prev_display_errors = ini_get('display_errors'); @ini_set('display_errors','0'); ob_start();

    global $wpdb;
    $table = $wpdb->prefix . 'nmkr_analytics';

    $range    = nmkr_analytics_parse_range_and_bucket($_REQUEST);
    $filters  = array(
        'from'           => $range['from'],
        'to'             => $range['to'],
        'shortcode_type' => nmkr_analytics_sanitize_shortcode_type( isset($_REQUEST['shortcode_type']) ? wp_unslash($_REQUEST['shortcode_type']) : '' ),
        'project_uid'    => nmkr_analytics_sanitize_uid( isset($_REQUEST['project_uid']) ? wp_unslash($_REQUEST['project_uid']) : '' ),
        'token_uid'      => nmkr_analytics_sanitize_uid( isset($_REQUEST['token_uid'])   ? wp_unslash($_REQUEST['token_uid'])   : '' ),
    );

    $cache_key = nmkr_analytics_cache_key('kpis', array('range'=>$range, 'filters'=>$filters));
    $use_cache = !nmkr_analytics_debug_on();
    if ($use_cache) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
            wp_send_json_success($cached);
        }
    }

    list($where_sql, $params) = nmkr_analytics_build_where($filters);

    // Index-friendly grouped count (uses ix_type_ts / ix_event_ts)
    $sql = "
        SELECT event_type, COUNT(*) AS cnt
        FROM $table
        $where_sql
          AND event_type IN ('view','click')
        GROUP BY event_type
    ";
    $prepared = $wpdb->prepare($sql, $params);
    $rows = $wpdb->get_results($prepared, ARRAY_A);

    $views = 0; $clicks = 0;
    foreach ($rows as $r) {
        if ($r['event_type'] === 'view')  { $views  = (int)$r['cnt']; }
        if ($r['event_type'] === 'click') { $clicks = (int)$r['cnt']; }
    }
    $ctr = ($views > 0) ? round(($clicks / max(1, $views)) * 100, 2) : 0.0;

    $resp = array(
        'views'  => $views,
        'clicks' => $clicks,
        'ctr'    => $ctr,
        'range'  => array('from'=>$range['from'], 'to'=>$range['to'], 'label'=>$range['range']),
    );

    if ($use_cache) {
        set_transient($cache_key, $resp, nmkr_analytics_cache_ttl());
    }

    ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
    wp_send_json_success($resp);
}

/**
 * Time-series (views, clicks, ctr) bucketed by hour/day
 * Params: from, to, range (optional), bucket (hour|day), shortcode_type, project_uid, token_uid, _ (cache buster)
 */
function nmkr_analytics_timeseries_ajax() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error(array('message' => 'Method not allowed'), 405);
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nmkr_dashboard_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Forbidden'), 403);
    }

    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $__prev_display_errors = ini_get('display_errors'); @ini_set('display_errors','0'); ob_start();

    global $wpdb;
    $table  = $wpdb->prefix . 'nmkr_analytics';

    $range   = nmkr_analytics_parse_range_and_bucket($_REQUEST);
    $filters = array(
        'from'           => $range['from'],
        'to'             => $range['to'],
        'shortcode_type' => nmkr_analytics_sanitize_shortcode_type( isset($_REQUEST['shortcode_type']) ? wp_unslash($_REQUEST['shortcode_type']) : '' ),
        'project_uid'    => nmkr_analytics_sanitize_uid( isset($_REQUEST['project_uid']) ? wp_unslash($_REQUEST['project_uid']) : '' ),
        'token_uid'      => nmkr_analytics_sanitize_uid( isset($_REQUEST['token_uid'])   ? wp_unslash($_REQUEST['token_uid'])   : '' ),
    );

    $cache_key = nmkr_analytics_cache_key('timeseries', array('range'=>$range, 'filters'=>$filters));
    $use_cache = !nmkr_analytics_debug_on();
    if ($use_cache) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
            wp_send_json_success($cached);
        }
    }

    list($where_sql, $params) = nmkr_analytics_build_where($filters);

    // Bucket expression (MySQL)
    $bucket_expr = ($range['bucket'] === 'hour')
        ? "DATE_FORMAT(event_ts, '%Y-%m-%d %H:00:00')"
        : "DATE(event_ts)";

    $sql = "
        SELECT
            $bucket_expr AS bucket,
            SUM(CASE WHEN event_type='view'  THEN 1 ELSE 0 END) AS views,
            SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) AS clicks
        FROM $table
        $where_sql
        GROUP BY bucket
        ORDER BY bucket ASC
    ";
    $prepared = $wpdb->prepare($sql, $params);
    $rows = $wpdb->get_results($prepared, ARRAY_A);

    foreach ($rows as &$r) {
        $v = (int) ($r['views']  ?? 0);
        $c = (int) ($r['clicks'] ?? 0);
        $r['views']  = $v;
        $r['clicks'] = $c;
        $r['ctr']    = ($v > 0) ? round(($c / $v) * 100, 2) : 0.0;
    }
    unset($r);

    $resp = array(
        'series' => $rows,
        'from'   => $range['from'],
        'to'     => $range['to'],
        'bucket' => $range['bucket'],
    );

    if ($use_cache) {
        set_transient($cache_key, $resp, nmkr_analytics_cache_ttl());
    }

    ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
    wp_send_json_success($resp);
}


