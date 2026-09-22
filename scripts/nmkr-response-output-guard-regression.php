<?php
/** Public-safe regression for request-scoped response output hardening. */
define('ABSPATH', __DIR__ . '/synthetic/');

$utility = file_get_contents(__DIR__ . '/../includes/helpers/nmkr-utility-functions.php');
$sync = file_get_contents(__DIR__ . '/../includes/synchronization/nmkr-sync-ajax-handlers.php');
$dashboard = file_get_contents(__DIR__ . '/../includes/pages/dashboard/nmkr-dashboard-ajax.php');
$analytics = file_get_contents(__DIR__ . '/../includes/pages/analytics/nmkr-analytics-ajax.php');

function nmkr_response_guard_assert($condition, $case) {
    if (!$condition) {
        throw new Exception($case);
    }
}

function nmkr_response_guard_extract_function($source, $name) {
    $start = strpos($source, 'function ' . $name . '(');
    nmkr_response_guard_assert(false !== $start, 'missing_' . $name);
    $brace = strpos($source, '{', $start);
    $depth = 0;
    $length = strlen($source);

    for ($index = $brace; $index < $length; $index++) {
        if ('{' === $source[$index]) {
            $depth++;
        } elseif ('}' === $source[$index] && 0 === --$depth) {
            return substr($source, $start, $index - $start + 1);
        }
    }

    throw new Exception('unterminated_' . $name);
}

try {
    eval(nmkr_response_guard_extract_function($utility, 'nmkr_begin_response_output_guard'));
    eval(nmkr_response_guard_extract_function($utility, 'nmkr_end_response_output_guard'));

    $original_display_errors = ini_get('display_errors');
    @ini_set('display_errors', '1');
    $expected_restore = ini_get('display_errors');
    $before_level = ob_get_level();

    $guard = nmkr_begin_response_output_guard();
    nmkr_response_guard_assert(ob_get_level() === $before_level + 1, 'guard_buffer_started');
    echo 'synthetic-stray-output';
    nmkr_end_response_output_guard($guard);

    nmkr_response_guard_assert(ob_get_level() === $before_level, 'guard_buffer_restored');
    nmkr_response_guard_assert(ini_get('display_errors') === $expected_restore, 'display_errors_restored');

    if (false !== $original_display_errors) {
        @ini_set('display_errors', $original_display_errors);
    }

    $production = array(
        'utility' => $utility,
        'sync' => $sync,
        'dashboard' => $dashboard,
        'analytics' => $analytics,
    );
    $ini_sites = 0;
    foreach ($production as $name => $source) {
        preg_match_all('/ini_set\\s*\\(\\s*[\\'"]display_errors[\\'"]/', $source, $matches);
        $count = count($matches[0]);
        $ini_sites += $count;
        if ('utility' !== $name) {
            nmkr_response_guard_assert(0 === $count, 'direct_ini_set_' . $name);
        }
    }
    nmkr_response_guard_assert(2 === $ini_sites, 'centralized_ini_set_count');

    nmkr_response_guard_assert(1 === substr_count($sync, 'nmkr_begin_response_output_guard()'), 'sync_begin_guard');
    nmkr_response_guard_assert(4 === substr_count($sync, 'nmkr_end_response_output_guard($__nmkr_response_guard)'), 'sync_end_guards');
    nmkr_response_guard_assert(1 === substr_count($dashboard, 'nmkr_begin_response_output_guard()'), 'dashboard_begin_guard');
    nmkr_response_guard_assert(3 === substr_count($dashboard, 'nmkr_end_response_output_guard($__nmkr_response_guard)'), 'dashboard_end_guards');
    nmkr_response_guard_assert(6 === substr_count($analytics, 'nmkr_begin_response_output_guard()'), 'analytics_begin_guards');
    nmkr_response_guard_assert(11 === substr_count($analytics, 'nmkr_end_response_output_guard('), 'analytics_end_guards');
    nmkr_response_guard_assert(3 === substr_count($analytics, '$rows, $__nmkr_response_guard);'), 'export_guard_handoff');

    echo "Response output guard regression: PASS\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Response output guard regression: FAIL " . $error->getMessage() . "\n");
    exit(1);
}
