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

/**
 * Enforce POST-only, nonce, capability, and secure headers for admin analytics AJAX.
 *
 * @param array $opts {
 *   @type bool   $require_post   Default true.
 *   @type string $nonce_key      Default 'nonce'.
 *   @type string $nonce_action   Default 'nmkr_dashboard_nonce'.
 *   @type string $capability     Default 'manage_options'.
 *   @type string $content        'json' or 'none' (default 'json'). If 'json', sets Content-Type header.
 * }
 */
function nmkr_analytics_admin_ajax_guard( array $opts = array() ) {
    $opts = array_merge(array(
        'require_post' => true,
        'nonce_key'    => 'nonce',
        'nonce_action' => 'nmkr_dashboard_nonce',
        'capability'   => 'nmkr_view_analytics',
        'content'      => 'json',
    ), $opts);

    if ( $opts['require_post'] && (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ) {
        status_header(405);
        wp_send_json_error(array('message' => 'Method Not Allowed'), 405);
    }

    $nonce_key = $opts['nonce_key'];
    $nonce     = isset($_POST[$nonce_key]) ? sanitize_text_field( wp_unslash( $_POST[$nonce_key] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, $opts['nonce_action'] ) ) {
        status_header(403);
        wp_send_json_error(array('message' => 'Invalid security token'), 403);
    }

    if ( ! current_user_can( $opts['capability'] ) ) {
        status_header(403);
        wp_send_json_error(array('message' => 'Forbidden'), 403);
    }

    // Security & cache headers
    nocache_headers();
    if ( ! headers_sent() ) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        if ( $opts['content'] === 'json' ) {
            header('Content-Type: application/json; charset=UTF-8');
        }
    }
}

/** ---------- Endpoints ---------- */

/**
 * KPIs: views, clicks, ctr for a range
 * Params: range (24h|7d|30d|custom), from, to, shortcode_type, project_uid, token_uid, _ (cache buster)
 */
function nmkr_analytics_kpis_ajax() {
    nmkr_analytics_admin_ajax_guard(array('content' => 'json'));

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
    nmkr_analytics_admin_ajax_guard(array('content' => 'json'));

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

/** Whitelist sort fields per entity */
function nmkr_analytics_whitelist_sort($entity, $sort) {
    $sort = strtolower(sanitize_text_field((string)$sort));
    $maps = array(
        'projects' => array('views','clicks','ctr','project_uid'),
        'tokens'   => array('views','clicks','ctr','token_uid'),
        'breakdown'=> array('views','clicks','ctr','shortcode_type'),
    );
    $allowed = $maps[$entity] ?? array();
    return in_array($sort, $allowed, true) ? $sort : $allowed[0];
}

/** Sanitize order */
function nmkr_analytics_sanitize_order($order) {
    $order = strtolower(sanitize_text_field((string)$order));
    return ($order === 'asc') ? 'ASC' : 'DESC';
}

/** Pagination bounds */
function nmkr_analytics_parse_pagination($req) {
    $page = max(1, (int) ($req['page'] ?? 1));
    $per  = (int) ($req['per_page'] ?? 10);
    if ($per < 1) $per = 10;
    if ($per > 100) $per = 100;
    $offset = ($page - 1) * $per;
    return array($page, $per, $offset);
}

/** Optional UID prefix search (index-friendly if prefix, not wildcard) */
function nmkr_analytics_uid_prefix($raw) {
    $raw = (string) $raw;
    $raw = preg_replace('/[^0-9a-zA-Z\-_]/', '', $raw);
    $raw = substr($raw, 0, 64);
    return $raw;
}

// Top Projects
add_action('wp_ajax_nmkr_analytics_top_projects', 'nmkr_analytics_top_projects_ajax');

function nmkr_analytics_top_projects_ajax() {
    nmkr_analytics_admin_ajax_guard(array('content' => 'json'));

    $__prev_display_errors = ini_get('display_errors'); @ini_set('display_errors','0'); ob_start();
    global $wpdb;
    $table = $wpdb->prefix . 'nmkr_analytics';

    // Range + common filters from PR-2 helper
    $range   = nmkr_analytics_parse_range_and_bucket($_REQUEST);
    $filters = array(
        'from'           => $range['from'],
        'to'             => $range['to'],
        'shortcode_type' => nmkr_analytics_sanitize_shortcode_type( isset($_REQUEST['shortcode_type']) ? wp_unslash($_REQUEST['shortcode_type']) : '' ),
        // optional: allow narrowing by a single UID (rare case)
        'project_uid'    => nmkr_analytics_sanitize_uid( isset($_REQUEST['project_uid']) ? wp_unslash($_REQUEST['project_uid']) : '' ),
        'token_uid'      => nmkr_analytics_sanitize_uid( isset($_REQUEST['token_uid'])   ? wp_unslash($_REQUEST['token_uid'])   : '' ),
    );

    // Pagination & sorting
    list($page, $per_page, $offset) = nmkr_analytics_parse_pagination($_REQUEST);
    $sort  = nmkr_analytics_whitelist_sort('projects', isset($_REQUEST['sort'])  ? wp_unslash($_REQUEST['sort'])  : 'views');
    $order = nmkr_analytics_sanitize_order(               isset($_REQUEST['order']) ? wp_unslash($_REQUEST['order']) : 'desc');

    // Optional prefix search (index-friendly)
    $prefix = nmkr_analytics_uid_prefix( isset($_REQUEST['search']) ? wp_unslash($_REQUEST['search']) : '' );

    // Cache
    $cache_key = nmkr_analytics_cache_key('top_projects', array('range'=>$range,'filters'=>$filters,'page'=>$page,'per'=>$per_page,'sort'=>$sort,'order'=>$order,'prefix'=>$prefix));
    $use_cache = !nmkr_analytics_debug_on();
    if ($use_cache) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
            wp_send_json_success($cached);
        }
    }

    // WHERE
    list($where_sql, $params) = nmkr_analytics_build_where($filters);
    $where_sql .= " AND project_uid IS NOT NULL ";
    if ($prefix !== '') {
        // Only prefix LIKE to keep index use; no leading wildcard
        $where_sql .= " AND project_uid LIKE %s ";
        $params[] = $wpdb->esc_like($prefix) . '%';
    }

    // ORDER BY whitelist (cannot be prepared)
    $order_by = ($sort === 'project_uid') ? 'project_uid' : (($sort === 'clicks') ? 'clicks' : (($sort === 'ctr') ? 'ctr' : 'views'));
    $order_dir = $order;

    // Total distinct projects (for pagination)
    $count_sql = "SELECT COUNT(DISTINCT project_uid) FROM $table $where_sql";
    $total = (int) $wpdb->get_var( $wpdb->prepare($count_sql, $params) );

    // Aggregation; compute CTR in SQL for sortable ORDER BY (views>0 guard via NULLIF)
    $sql = "
        SELECT
            project_uid,
            SUM(CASE WHEN event_type='view'  THEN 1 ELSE 0 END) AS views,
            SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) AS clicks,
            (SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN event_type='view' THEN 1 ELSE 0 END),0)) AS ctr
        FROM $table
        $where_sql
        GROUP BY project_uid
        ORDER BY $order_by $order_dir
        LIMIT %d OFFSET %d
    ";
    $params_limit = array_merge($params, array($per_page, $offset));
    $rows = $wpdb->get_results( $wpdb->prepare($sql, $params_limit), ARRAY_A );

    // Normalize + round CTR (%)
    foreach ($rows as &$r) {
        $v = (int) ($r['views']  ?? 0);
        $c = (int) ($r['clicks'] ?? 0);
        $r['views']  = $v;
        $r['clicks'] = $c;
        $r['ctr']    = ($v > 0) ? round(((float)$r['ctr']) * 100, 2) : 0.0;
    } unset($r);

    $resp = array(
        'rows'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'sort'     => $order_by,
        'order'    => strtolower($order_dir),
        'range'    => array('from'=>$range['from'],'to'=>$range['to']),
    );

    if ($use_cache) {
        set_transient($cache_key, $resp, nmkr_analytics_cache_ttl());
    }

    ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
    wp_send_json_success($resp);
}

// Top Tokens
add_action('wp_ajax_nmkr_analytics_top_tokens', 'nmkr_analytics_top_tokens_ajax');

function nmkr_analytics_top_tokens_ajax() {
    nmkr_analytics_admin_ajax_guard(array('content' => 'json'));

    $__prev_display_errors = ini_get('display_errors'); @ini_set('display_errors','0'); ob_start();
    global $wpdb;
    $table = $wpdb->prefix . 'nmkr_analytics';

    $range   = nmkr_analytics_parse_range_and_bucket($_REQUEST);
    $filters = array(
        'from'           => $range['from'],
        'to'             => $range['to'],
        'shortcode_type' => nmkr_analytics_sanitize_shortcode_type( isset($_REQUEST['shortcode_type']) ? wp_unslash($_REQUEST['shortcode_type']) : '' ),
        'project_uid'    => nmkr_analytics_sanitize_uid( isset($_REQUEST['project_uid']) ? wp_unslash($_REQUEST['project_uid']) : '' ),
        'token_uid'      => nmkr_analytics_sanitize_uid( isset($_REQUEST['token_uid'])   ? wp_unslash($_REQUEST['token_uid'])   : '' ),
    );

    list($page, $per_page, $offset) = nmkr_analytics_parse_pagination($_REQUEST);
    $sort  = nmkr_analytics_whitelist_sort('tokens', isset($_REQUEST['sort'])  ? wp_unslash($_REQUEST['sort'])  : 'views');
    $order = nmkr_analytics_sanitize_order(               isset($_REQUEST['order']) ? wp_unslash($_REQUEST['order']) : 'desc');

    $prefix = nmkr_analytics_uid_prefix( isset($_REQUEST['search']) ? wp_unslash($_REQUEST['search']) : '' );

    $cache_key = nmkr_analytics_cache_key('top_tokens', array('range'=>$range,'filters'=>$filters,'page'=>$page,'per'=>$per_page,'sort'=>$sort,'order'=>$order,'prefix'=>$prefix));
    $use_cache = !nmkr_analytics_debug_on();
    if ($use_cache) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
            wp_send_json_success($cached);
        }
    }

    list($where_sql, $params) = nmkr_analytics_build_where($filters);
    $where_sql .= " AND token_uid IS NOT NULL ";
    if ($prefix !== '') {
        $where_sql .= " AND token_uid LIKE %s ";
        $params[] = $wpdb->esc_like($prefix) . '%';
    }

    $order_by = ($sort === 'token_uid') ? 'token_uid' : (($sort === 'clicks') ? 'clicks' : (($sort === 'ctr') ? 'ctr' : 'views'));
    $order_dir = $order;

    $count_sql = "SELECT COUNT(DISTINCT token_uid) FROM $table $where_sql";
    $total = (int) $wpdb->get_var( $wpdb->prepare($count_sql, $params) );

    $sql = "
        SELECT
            token_uid,
            SUM(CASE WHEN event_type='view'  THEN 1 ELSE 0 END) AS views,
            SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) AS clicks,
            (SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN event_type='view' THEN 1 ELSE 0 END),0)) AS ctr
        FROM $table
        $where_sql
        GROUP BY token_uid
        ORDER BY $order_by $order_dir
        LIMIT %d OFFSET %d
    ";
    $params_limit = array_merge($params, array($per_page, $offset));
    $rows = $wpdb->get_results( $wpdb->prepare($sql, $params_limit), ARRAY_A );

    foreach ($rows as &$r) {
        $v = (int) ($r['views']  ?? 0);
        $c = (int) ($r['clicks'] ?? 0);
        $r['views']  = $v;
        $r['clicks'] = $c;
        $r['ctr']    = ($v > 0) ? round(((float)$r['ctr']) * 100, 2) : 0.0;
    } unset($r);

    $resp = array(
        'rows'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'sort'     => $order_by,
        'order'    => strtolower($order_dir),
        'range'    => array('from'=>$range['from'],'to'=>$range['to']),
    );

    if ($use_cache) {
        set_transient($cache_key, $resp, nmkr_analytics_cache_ttl());
    }

    ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
    wp_send_json_success($resp);
}

// Shortcode Breakdown
add_action('wp_ajax_nmkr_analytics_breakdown', 'nmkr_analytics_breakdown_ajax');

function nmkr_analytics_breakdown_ajax() {
    nmkr_analytics_admin_ajax_guard(array('content' => 'json'));

    $__prev_display_errors = ini_get('display_errors'); @ini_set('display_errors','0'); ob_start();
    global $wpdb;
    $table = $wpdb->prefix . 'nmkr_analytics';

    $range   = nmkr_analytics_parse_range_and_bucket($_REQUEST);
    $filters = array(
        'from'           => $range['from'],
        'to'             => $range['to'],
        'shortcode_type' => '', // intentionally ignored for breakdown
        'project_uid'    => nmkr_analytics_sanitize_uid( isset($_REQUEST['project_uid']) ? wp_unslash($_REQUEST['project_uid']) : '' ),
        'token_uid'      => nmkr_analytics_sanitize_uid( isset($_REQUEST['token_uid'])   ? wp_unslash($_REQUEST['token_uid'])   : '' ),
    );

    $sort  = nmkr_analytics_whitelist_sort('breakdown', isset($_REQUEST['sort']) ? wp_unslash($_REQUEST['sort']) : 'views');
    $order = nmkr_analytics_sanitize_order(               isset($_REQUEST['order'])? wp_unslash($_REQUEST['order']): 'desc');

    $cache_key = nmkr_analytics_cache_key('breakdown', array('range'=>$range,'filters'=>$filters,'sort'=>$sort,'order'=>$order));
    $use_cache = !nmkr_analytics_debug_on();
    if ($use_cache) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
            wp_send_json_success($cached);
        }
    }

    // NOTE: We intentionally do NOT filter by shortcode_type here because this endpoint
    // returns a breakdown grouped BY shortcode_type. Filters for project/token + time still apply.
    list($where_sql, $params) = nmkr_analytics_build_where($filters); // includes from/to

    $order_by = ($sort === 'shortcode_type') ? 'shortcode_type' : (($sort === 'clicks') ? 'clicks' : (($sort === 'ctr') ? 'ctr' : 'views'));
    $order_dir = $order;

    $sql = "
        SELECT
            shortcode_type,
            SUM(CASE WHEN event_type='view'  THEN 1 ELSE 0 END) AS views,
            SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) AS clicks,
            (SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN event_type='view' THEN 1 ELSE 0 END),0)) AS ctr
        FROM $table
        $where_sql
        GROUP BY shortcode_type
        ORDER BY $order_by $order_dir
    ";
    $rows = $wpdb->get_results( $wpdb->prepare($sql, $params), ARRAY_A );

    foreach ($rows as &$r) {
        $v = (int) ($r['views']  ?? 0);
        $c = (int) ($r['clicks'] ?? 0);
        $r['views']  = $v;
        $r['clicks'] = $c;
        $r['ctr']    = ($v > 0) ? round(((float)$r['ctr']) * 100, 2) : 0.0;
    } unset($r);

    $resp = array(
        'rows'  => $rows,
        'range' => array('from'=>$range['from'],'to'=>$range['to']),
        'sort'  => $order_by,
        'order' => strtolower($order_dir),
    );

    if ($use_cache) {
        set_transient($cache_key, $resp, nmkr_analytics_cache_ttl());
    }

    ob_end_clean(); @ini_set('display_errors', $__prev_display_errors);
    wp_send_json_success($resp);
}

// Export endpoint
add_action('wp_ajax_nmkr_analytics_export', 'nmkr_analytics_export_ajax');

/**
 * Export analytics data as CSV or JSON.
 * Params (POST):
 *   - entity: timeseries | top_projects | top_tokens | breakdown
 *   - format: csv | json
 *   - range: 24h|7d|30d|custom
 *   - from, to (when range=custom)
 *   - shortcode_type, project_uid, token_uid
 *   - For top_*: page, per_page, sort (views|clicks|ctr|*_uid), order (asc|desc), search (UID prefix)
 * Note: Exports reflect current filters; top_* export the current page to avoid huge payloads.
 */
function nmkr_analytics_export_ajax() {
    nmkr_analytics_admin_ajax_guard(array('content' => 'none'));

    $__prev_display_errors = ini_get('display_errors'); @ini_set('display_errors','0'); ob_start();

    $entity = strtolower(sanitize_text_field($_POST['entity'] ?? 'timeseries'));
    $format = strtolower(sanitize_text_field($_POST['format'] ?? 'csv'));
    if (!in_array($entity, array('timeseries','top_projects','top_tokens','breakdown'), true)) {
        $entity = 'timeseries';
    }
    if (!in_array($format, array('csv','json'), true)) {
        $format = 'csv';
    }

    global $wpdb;
    $table  = $wpdb->prefix . 'nmkr_analytics';

    // Reuse helpers
    $range   = nmkr_analytics_parse_range_and_bucket($_REQUEST);
    $filters = array(
        'from'           => $range['from'],
        'to'             => $range['to'],
        'shortcode_type' => nmkr_analytics_sanitize_shortcode_type($_REQUEST['shortcode_type'] ?? ''),
        'project_uid'    => nmkr_analytics_sanitize_uid($_REQUEST['project_uid'] ?? ''),
        'token_uid'      => nmkr_analytics_sanitize_uid($_REQUEST['token_uid']   ?? ''),
    );
    list($where_sql, $params) = nmkr_analytics_build_where($filters);

    $stamp = gmdate('Ymd_His');
    $fname = "nmkr-analytics-{$entity}-{$stamp}";

    if ($entity === 'timeseries') {
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
        $rows = $wpdb->get_results( $wpdb->prepare($sql, $params), ARRAY_A );
        foreach ($rows as &$r) {
            $v = (int) ($r['views']  ?? 0);
            $c = (int) ($r['clicks'] ?? 0);
            $r['views']  = $v;
            $r['clicks'] = $c;
            $r['ctr']    = ($v > 0) ? round(($c / $v) * 100, 2) : 0.0;
        } unset($r);

        return nmkr_analytics_stream_export($format, $fname, array('bucket','views','clicks','ctr'), $rows);
    }

    if ($entity === 'breakdown') {
        $sql = "
            SELECT
                shortcode_type,
                SUM(CASE WHEN event_type='view'  THEN 1 ELSE 0 END) AS views,
                SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) AS clicks,
                (SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN event_type='view' THEN 1 ELSE 0 END),0)) AS ctr
            FROM $table
            $where_sql
            GROUP BY shortcode_type
            ORDER BY views DESC
        ";
        $rows = $wpdb->get_results( $wpdb->prepare($sql, $params), ARRAY_A );
        foreach ($rows as &$r) {
            $v = (int) ($r['views']  ?? 0);
            $c = (int) ($r['clicks'] ?? 0);
            $r['views']  = $v;
            $r['clicks'] = $c;
            $r['ctr']    = ($v > 0) ? round(((float)$r['ctr']) * 100, 2) : 0.0;
        } unset($r);

        return nmkr_analytics_stream_export($format, $fname, array('shortcode_type','views','clicks','ctr'), $rows);
    }

    // Common for top_projects / top_tokens
    $is_projects = ($entity === 'top_projects');
    $uid_col     = $is_projects ? 'project_uid' : 'token_uid';
    $prefix      = nmkr_analytics_uid_prefix($_REQUEST['search'] ?? '');

    $where2  = $where_sql . " AND $uid_col IS NOT NULL ";
    $params2 = $params;
    if ($prefix !== '') {
        $where2 .= " AND $uid_col LIKE %s ";
        $params2[] = $wpdb->esc_like($prefix) . '%';
    }

    list($page, $per_page, $offset) = nmkr_analytics_parse_pagination($_REQUEST);

    $entity_key = $is_projects ? 'projects' : 'tokens';
    $sort  = nmkr_analytics_whitelist_sort($entity_key, $_REQUEST['sort'] ?? 'views');
    $order = nmkr_analytics_sanitize_order($_REQUEST['order'] ?? 'desc');
    $order_by = ($sort === $uid_col) ? $uid_col : (($sort === 'clicks') ? 'clicks' : (($sort === 'ctr') ? 'ctr' : 'views'));

    $sql = "
        SELECT
            $uid_col AS {$uid_col},
            SUM(CASE WHEN event_type='view'  THEN 1 ELSE 0 END) AS views,
            SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) AS clicks,
            (SUM(CASE WHEN event_type='click' THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN event_type='view' THEN 1 ELSE 0 END),0)) AS ctr
        FROM $table
        $where2
        GROUP BY $uid_col
        ORDER BY $order_by $order
        LIMIT %d OFFSET %d
    ";
    $rows = $wpdb->get_results( $wpdb->prepare($sql, array_merge($params2, array($per_page, $offset))), ARRAY_A );
    foreach ($rows as &$r) {
        $v = (int) ($r['views']  ?? 0);
        $c = (int) ($r['clicks'] ?? 0);
        $r['views']  = $v;
        $r['clicks'] = $c;
        $r['ctr']    = ($v > 0) ? round(((float)$r['ctr']) * 100, 2) : 0.0;
    } unset($r);

    $headers = array($uid_col, 'views', 'clicks', 'ctr');
    return nmkr_analytics_stream_export($format, $fname, $headers, $rows);
}

/**
 * Stream CSV or JSON and exit.
 * @param string $format 'csv'|'json'
 * @param string $fname  filename base (no extension)
 * @param array  $headers ordered column keys
 * @param array  $rows    list of associative arrays
 */
function nmkr_analytics_stream_export($format, $fname, $headers, $rows) {
    ob_end_clean();

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'.json"');
        echo wp_json_encode($rows);
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fname.'.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $headers);

    foreach ($rows as $r) {
        $line = array();
        foreach ($headers as $h) {
            $val = isset($r[$h]) ? $r[$h] : '';
            if (is_float($val)) {
                $val = number_format($val, 2, '.', '');
            }
            $line[] = $val;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}