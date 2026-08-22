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

try {
    $forbidden = array(
        "'technical_details'",
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
