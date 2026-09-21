<?php
/** Public-safe regression for project UID compatibility resolution. */
define('ABSPATH', dirname(__DIR__) . '/');

class WP_Error {
    private $code;
    public function __construct($code, $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($value) { return $value; }
function plugin_dir_path($path) { return rtrim(dirname($path), '/\\') . '/'; }
function nmkr_sync_worker_checkpoint() { return true; }
function nmkr_project_phase_progress($processed, $total, $start, $end) {
    return $total > 0 ? $start + (($processed / $total) * ($end - $start)) : $start;
}
function update_option() {}
function nmkr_update_sync_progress() {}
function nmkr_log_data_sync() {}

$GLOBALS['stored_projects'] = array();
function nmkr_store_project_exact($project) {
    $GLOBALS['stored_projects'][] = $project;
    return array('action' => 'inserted');
}
function check($ok, $message) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

require dirname(__DIR__) . '/includes/helpers/nmkr-project-normalization.php';

check(
    nmkr_resolve_project_uid(array('uid' => 'canonical-1', 'project_uid' => 'compat-1')) === 'canonical-1',
    'canonical uid keeps precedence when both fields are present'
);
check(
    nmkr_resolve_project_uid(array('project_uid' => 'compat_2')) === 'compat_2',
    'project_uid compatibility fallback resolves'
);

$invalid_cases = array(
    array(),
    array('uid' => ''),
    array('uid' => ' canonical'),
    array('uid' => 'canonical '),
    array('uid' => 'bad.uid'),
    array('uid' => array('bad')),
    array('uid' => str_repeat('a', 201)),
    array('project_uid' => 'bad uid'),
    array('project_uid' => array('bad')),
    array('uid' => '', 'project_uid' => 'fallback-must-not-override-empty-canonical'),
);
foreach ($invalid_cases as $invalid) {
    check(
        nmkr_resolve_project_uid($invalid) === '',
        'missing or invalid canonical project identifier is rejected'
    );
}
check(
    nmkr_resolve_project_uid(array('uid' => null, 'project_uid' => 'compat-null-fallback')) === 'compat-null-fallback',
    'null canonical field permits compatibility fallback'
);

$core = file_get_contents(dirname(__DIR__) . '/includes/synchronization/nmkr-sync-core.php');
$start = strpos($core, 'function nmkr_sync_projects(');
$end = strpos($core, "\n\n/**", $start + 1);
check($start !== false && $end !== false, 'production project synchronization function is discoverable');
eval(substr($core, $start, $end - $start));

$run = function ($project) {
    $GLOBALS['stored_projects'] = array();
    $log = array();
    $steps = 0;
    $result = nmkr_sync_projects($log, $steps, 10, '', 0, array($project), 0, 10);
    return array($result, $GLOBALS['stored_projects'], $steps, $log);
};

list($uids, $stored) = $run(array(
    'uid' => 'canonical-3',
    'project_uid' => 'different-compat',
    'projectname' => 'Canonical',
));
check(
    $uids === array('canonical-3') && count($stored) === 1 && $stored[0]['uid'] === 'canonical-3',
    'canonical uid is preserved through persistence handoff'
);

list($uids, $stored) = $run(array(
    'project_uid' => 'compat-4',
    'projectname' => 'Compatibility',
));
check(
    $uids === array('compat-4') && count($stored) === 1 && $stored[0]['uid'] === 'compat-4',
    'project_uid fallback is canonicalized before persistence'
);

list($uids, $stored, $steps) = $run(array('projectname' => 'Missing'));
check(
    $uids === array() && $stored === array() && $steps === 1,
    'missing UID is rejected without persistence and preserves failed-project progress'
);

list($uids, $stored, $steps) = $run(array(
    'uid' => 'bad uid',
    'project_uid' => 'compat-ignored',
    'projectname' => 'Invalid',
));
check(
    $uids === array() && $stored === array() && $steps === 1,
    'invalid canonical UID is rejected rather than silently falling back'
);

echo "Project UID regression: PASS\n";
