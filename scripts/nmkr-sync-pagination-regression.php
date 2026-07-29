<?php
/* Public-safe behavioral regression for production pagination policy. */
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    private $code; private $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($value) { return $value; }
require dirname(__DIR__) . '/includes/synchronization/nmkr-sync-pagination.php';
function assert_true($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
function load_core_helper($source, $name) {
    $start = strpos($source, 'function ' . $name . '(');
    $next = strpos($source, "\nfunction ", $start + 1);
    $comment = strpos($source, "\n/**", $start + 1);
    $ends = array_filter(array($next, $comment), function ($value) { return $value !== false; });
    if ($start === false) { assert_true(false, 'production helper ' . $name . ' is available'); }
    eval(substr($source, $start, min($ends) - $start));
}
$core_source = file_get_contents(dirname(__DIR__) . '/includes/synchronization/nmkr-sync-core.php');
load_core_helper($core_source, 'nmkr_is_fatal_token_persistence_error');
load_core_helper($core_source, 'nmkr_project_phase_progress');
function token($uid) { return array('uid' => $uid); }
function run_pages($projects, $pages, $max = 2000, $stop = null) {
    $calls = array(); $details = array(); $progress = array(); $checkpoints = array();
    $fetch = function ($project, $page) use (&$calls, $pages) { $calls[] = "$project:$page"; return $pages[$project][$page] ?? array(); };
    $process = function ($uid, $project) use (&$details, $stop) { $details[] = $uid; return $stop === "detail:$uid" ? new WP_Error('sync_stop_requested', 'stop') : true; };
    $checkpoint = function ($phase, $project, $page) use (&$checkpoints, $stop) { $key = "$phase:$project:$page"; $checkpoints[] = $key; return $stop === $key ? new WP_Error('sync_stop_requested', 'stop') : true; };
    $report = function ($pi, $pc, $page, $unique, $terminal) use (&$progress) { $progress[] = array($pi, $page, $unique, $terminal); };
    return array(nmkr_stream_token_pages($projects, $fetch, $process, $checkpoint, $report, $max), $calls, $details, $progress, $checkpoints);
}
list($result, $calls, $details, $progress) = run_pages(array('p1'), array('p1' => array(1 => array(token('a'), token('b')), 2 => array(token('b'), token('c')), 3 => array())));
assert_true(!is_wp_error($result) && $result['total_tokens'] === 3, 'multi-page unique total');
assert_true($calls === array('p1:1','p1:2','p1:3'), 'each numbered page and terminal empty page dispatched once');
assert_true($details === array('a','b','c'), 'one detail dispatch per unique UID');
assert_true(end($progress)[3] === true, 'project slice completes only on empty page');
list($partial, $partial_calls) = run_pages(array('p'), array('p' => array(1 => array(token('a')), 2 => array(token('b')), 3 => array())));
assert_true(!is_wp_error($partial) && $partial_calls === array('p:1','p:2','p:3'), 'partial pages are not terminal');
list($empty, $empty_calls, $empty_details) = run_pages(array('p'), array('p' => array(1 => array())));
assert_true($empty['total_tokens'] === 0 && $empty_calls === array('p:1') && $empty_details === array(), 'empty first page is terminal');
list($conflict) = run_pages(array('p1','p2'), array('p1'=>array(1=>array(token('x')),2=>array()), 'p2'=>array(1=>array(token('x')))));
assert_true(is_wp_error($conflict) && $conflict->get_error_code() === 'nmkr_token_project_conflict', 'conflicting ownership fails');
list($repeat) = run_pages(array('p'), array('p'=>array(1=>array(token('a')),2=>array(token('a')))));
assert_true(is_wp_error($repeat) && $repeat->get_error_code() === 'nmkr_token_page_repeated', 'duplicate complete page fails');
list($no_progress) = run_pages(array('p'), array('p'=>array(1=>array(token('a'),token('b')),2=>array(token('a')))));
assert_true(is_wp_error($no_progress) && in_array($no_progress->get_error_code(), array('nmkr_token_page_repeated','nmkr_token_page_no_progress'), true), 'non-progress page fails');
foreach (array(array('bad'), array(array()), array(array('uid'=>'bad uid'))) as $bad) {
    list($malformed) = run_pages(array('p'), array('p'=>array(1=>$bad)));
    assert_true(is_wp_error($malformed) && strpos($malformed->get_error_code(), 'malformed') !== false, 'malformed page or record fails');
}
list($limit) = run_pages(array('p'), array('p'=>array(1=>array(token('a')),2=>array(token('b')))), 1);
assert_true(is_wp_error($limit) && $limit->get_error_code() === 'nmkr_token_page_limit', 'page limit exhaustion fails');
list($stopped, $stopped_calls) = run_pages(array('p'), array('p'=>array(1=>array(token('a')))), 2000, 'before_page_request:p:1');
assert_true(is_wp_error($stopped) && $stopped_calls === array(), 'stop before page prevents dispatch');
list($stopped_after, $after_calls, $after_details) = run_pages(array('p'), array('p'=>array(1=>array(token('a')))), 2000, 'after_page_request:p:1');
assert_true(is_wp_error($stopped_after) && count($after_calls) === 1 && $after_details === array(), 'stop after response prevents processing');
list($detail_stop, $detail_calls, $detail_details) = run_pages(array('p'), array('p'=>array(1=>array(token('a'),token('b')))), 2000, 'detail:a');
assert_true(is_wp_error($detail_stop) && $detail_calls === array('p:1') && $detail_details === array('a'), 'stop during detail prevents later work');
$fatal_calls = array();
$fatal_pages = array();
$fatal_result = nmkr_stream_token_pages(
    array('p1', 'p2'),
    function ($project, $page) use (&$fatal_pages) { $fatal_pages[] = "$project:$page"; return array(token('fatal'), token('later')); },
    function ($uid) use (&$fatal_calls) {
        $fatal_calls[] = $uid;
        $result = new WP_Error('nmkr_token_details_write_failed', 'Synthetic database persistence failure.');
        return nmkr_is_fatal_token_persistence_error($result) ? $result : true;
    },
    function () { return true; }
);
assert_true(is_wp_error($fatal_result) && $fatal_result->get_error_code() === 'nmkr_token_details_write_failed'
    && $fatal_calls === array('fatal') && $fatal_pages === array('p1:1'),
    'fatal token persistence failure stops later token, page, and project work');
$large_project_progress = array();
for ($processed = 0; $processed <= 500; $processed++) {
    $large_project_progress[] = nmkr_project_phase_progress($processed, 500, 5, 15);
}
assert_true(min($large_project_progress) >= 5 && max($large_project_progress) === 15.0
    && max(array_slice($large_project_progress, 0, -1)) < 15 && max($large_project_progress) < 100,
    '500 project writes remain within 5-15 and cannot reach terminal progress');
echo "Pagination regression passed.\n";
