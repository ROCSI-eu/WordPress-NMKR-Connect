<?php
/** Public-safe synthetic regression for the in-place run_id schema upgrade. */
define('ABSPATH', dirname(__DIR__) . '/');
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['nmkr_options'] = array();
$GLOBALS['nmkr_autoload'] = array();
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['nmkr_options']) ? $GLOBALS['nmkr_options'][$key] : $default; }
function add_option($key, $value, $deprecated = '', $autoload = 'yes') { if (array_key_exists($key, $GLOBALS['nmkr_options'])) return false; $GLOBALS['nmkr_options'][$key] = $value; $GLOBALS['nmkr_autoload'][$key] = $autoload; return true; }
function update_option($key, $value, $autoload = null) { $GLOBALS['nmkr_options'][$key] = $value; if ($autoload !== null) $GLOBALS['nmkr_autoload'][$key] = $autoload; return true; }
function delete_option($key) { unset($GLOBALS['nmkr_options'][$key], $GLOBALS['nmkr_autoload'][$key]); return true; }
class NMKR_Schema_WPDB {
    public $prefix = 'wp_'; public $table = true; public $column = false; public $index = false; public $rows = array(array('id' => 1), array('id' => 2)); public $fail_column = false; public $fail_index = false; public $fail_verify = false; public $destructive = 0;
    public function get_charset_collate() { return ''; }
    public function prepare($query, $value) { return str_replace('%s', "'" . $value . "'", $query); }
    public function get_var($query) { return $this->table ? 'wp_nmkr_sync_stats' : null; }
    public function get_results($query, $format) {
        if (stripos($query, 'SHOW COLUMNS') === 0) return ($this->column && !$this->fail_verify) ? array(array('Field' => 'run_id', 'Type' => 'char(36)', 'Null' => 'YES')) : array(array('Field' => 'id', 'Type' => 'mediumint(9)', 'Null' => 'NO'));
        if (stripos($query, 'SHOW INDEX') === 0) return ($this->index && !$this->fail_verify) ? array(array('Non_unique' => 0, 'Key_name' => 'run_id', 'Column_name' => 'run_id')) : array();
        return array();
    }
    public function query($query) { if (stripos($query, 'ALTER TABLE') === 0) { if ($this->fail_index) return false; $this->index = true; return 1; } return false; }
}
$GLOBALS['wpdb'] = new NMKR_Schema_WPDB();
function dbDelta($sql) { global $wpdb; if (!$wpdb->fail_column) $wpdb->column = true; if (!$wpdb->fail_index) $wpdb->index = true; return array(); }
require dirname(__DIR__) . '/includes/database/nmkr-database-structure.php';
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
function reset_schema($compliant = false) { global $wpdb; $GLOBALS['nmkr_options'] = array(); $GLOBALS['nmkr_autoload'] = array(); $wpdb = new NMKR_Schema_WPDB(); $wpdb->column = $compliant; $wpdb->index = $compliant; }
reset_schema();
check(nmkr_connect_maybe_upgrade_schema(), 'legacy table upgrades successfully');
check($wpdb->column && $wpdb->index && count($wpdb->rows) === 2, 'upgrade preserves legacy rows while adding nullable column and index');
check(get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION) === NMKR_CONNECT_SCHEMA_VERSION, 'version records only after verified success');
check($GLOBALS['nmkr_autoload'][NMKR_CONNECT_SCHEMA_VERSION_OPTION] === false, 'schema version option is non-autoloaded');
check(!get_option(NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION), 'lock is cleaned after success');
$before = $wpdb->destructive; check(nmkr_connect_maybe_upgrade_schema() && $wpdb->destructive === $before, 'second invocation is non-destructive');
reset_schema(true); check(nmkr_connect_maybe_upgrade_schema(), 'already-compliant old installation is recorded without migration');
reset_schema(); $wpdb->fail_column = true; check(!nmkr_connect_maybe_upgrade_schema() && get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION, '') === '', 'column-add failure does not advance version'); check(!get_option(NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION), 'lock is cleaned after column failure');
reset_schema(); $wpdb->fail_index = true; check(!nmkr_connect_maybe_upgrade_schema() && get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION, '') === '', 'index-add failure does not advance version'); check(!get_option(NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION), 'lock is cleaned after index failure');
reset_schema(); $wpdb->fail_verify = true; check(!nmkr_connect_maybe_upgrade_schema() && get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION, '') === '', 'verification failure does not advance version');
reset_schema(); add_option(NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION, array('token' => 'active', 'created_at' => time()), '', 'no'); check(!nmkr_connect_maybe_upgrade_schema() && !$wpdb->column, 'lock contention prevents a second migration');
$GLOBALS['nmkr_options'][NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION] = array('token' => 'stale', 'created_at' => time() - NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_TTL - 1); check(nmkr_connect_maybe_upgrade_schema(), 'stale lock is recovered'); check(!get_option(NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION), 'stale recovery lock is cleaned');
reset_schema(); nmkr_connect_create_tables(); check(nmkr_connect_maybe_upgrade_schema() && get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION) === NMKR_CONNECT_SCHEMA_VERSION, 'fresh activation records version after verification');
echo "All schema upgrade regression checks passed.\n";
