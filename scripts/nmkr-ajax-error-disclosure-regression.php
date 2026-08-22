<?php
/** Public-safe source-contract regression for privileged AJAX serialization. */
$sync = file_get_contents(__DIR__ . '/../includes/synchronization/nmkr-sync-ajax-handlers.php');
$logs = file_get_contents(__DIR__ . '/../includes/pages/dashboard/nmkr-dashboard-ajax.php');
$ui = file_get_contents(__DIR__ . '/../includes/pages/dashboard/nmkr-dashboard-ui.php');

function nmkr_contract_assert($condition, $case) {
    if (!$condition) {
        throw new Exception($case);
    }
}

function nmkr_extract_function($source, $name) {
    $start = strpos($source, 'function ' . $name . '(');
    nmkr_contract_assert($start !== false, 'missing_' . $name);
    $brace = strpos($source, '{', $start);
    $depth = 0;
    $length = strlen($source);
    for ($index = $brace; $index < $length; $index++) {
        if ($source[$index] === '{') $depth++;
        if ($source[$index] === '}' && --$depth === 0) return substr($source, $start, $index - $start + 1);
    }
    throw new Exception('unterminated_' . $name);
}

try {
    // Execute the production serializer boundary in isolation with a non-empty
    // private diagnostic. Test output deliberately never includes the marker.
    if (!function_exists('__')) {
        function __($message, $domain = null) { return $message; }
    }
    eval(nmkr_extract_function($sync, 'nmkr_public_failed_terminal_progress_response'));
    $marker = implode('_', array('NMKR', 'INTERNAL', 'MARKER', 'DO', 'NOT', 'DISCLOSE'));
    $terminal = nmkr_public_failed_terminal_progress_response(array(
        'terminal_outcome' => 'failed',
        'terminalRunId' => 'abababab-1111-4111-8111-111111111111',
        'progress' => 73,
        'live_metrics' => array('api_requests' => 4),
        'error' => $marker,
        'technical_details' => $marker,
    ));
    $encoded = json_encode(array('success' => true, 'data' => $terminal));
    nmkr_contract_assert($terminal['terminal_outcome'] === 'failed', 'terminal_outcome');
    nmkr_contract_assert($terminal['error_code'] === 'sync_terminal_failed', 'terminal_error_code');
    nmkr_contract_assert($terminal['error'] === 'Synchronization failed. Review the private server diagnostics for details.', 'terminal_public_message');
    nmkr_contract_assert(strpos($encoded, $marker) === false, 'terminal_marker_disclosed');
    nmkr_contract_assert(!array_key_exists('technical_details', $terminal), 'terminal_technical_details');
    nmkr_contract_assert(strpos($encoded, '"success":true') !== false, 'terminal_success_contract');
    nmkr_contract_assert($terminal['progress'] === 73 && $terminal['live_metrics']['api_requests'] === 4, 'terminal_progress_metrics');

    $forbidden = array(
        "'message' => \$owner_error->get_error_message()",
        "'message' => \$result->get_error_message()",
        "'message' => 'Synchronization encountered a critical error: ' . \$error",
        "'message' => 'Failed to force stop synchronization: ' . \$error_message",
    );
    foreach ($forbidden as $index => $needle) {
        nmkr_contract_assert(strpos($sync, $needle) === false, 'sync_boundary_' . $index);
    }
    nmkr_contract_assert(strpos($sync, "'error_code' => 'option_retrieval_failed'") !== false, 'progress_option_code');
    nmkr_contract_assert(strpos($sync, "'error_code' => 'sync_critical_error'") !== false, 'critical_code');
    nmkr_contract_assert(strpos($sync, "'error_code'] = 'sync_terminal_failed'") !== false, 'terminal_code');
    nmkr_contract_assert(strpos($sync, "'terminal_outcome'") !== false && strpos($sync, "wp_send_json_success(\$response_data)") !== false, 'terminal_success_envelope');
    nmkr_contract_assert(strpos($sync, "? 409 : 503") !== false && strpos($sync, "? 409 : 500") !== false, 'status_contracts');
    nmkr_contract_assert(strpos($logs, "'error_code' => 'log_clear_failed'") !== false, 'clear_all_code');
    nmkr_contract_assert(strpos($logs, "'error_code' => 'section_log_clear_failed'") !== false, 'clear_section_code');
    nmkr_contract_assert(strpos($logs, "'message' => 'Failed to clear logs: ' . \$e->getMessage()") === false, 'clear_all_boundary');
    nmkr_contract_assert(strpos($logs, "logs: ' . \$e->getMessage()") === false, 'clear_section_boundary');
    nmkr_contract_assert(strpos($ui, 'xhr.responseText') === false, 'dashboard_transport_boundary');
    echo "AJAX error disclosure regression: PASS\n";
} catch (Throwable $error) {
    fwrite(STDERR, "AJAX error disclosure regression: FAIL " . $error->getMessage() . "\n");
    exit(1);
}
