<?php
/**
 * Read-only WP-CLI gate for the exact synthetic background worker event.
 */

if (!defined('WP_CLI') || WP_CLI !== true || !function_exists('_get_cron_array')) {
    exit(1);
}

$expected_run_id = getenv('NMKR_SYNTHETIC_EXPECTED_RUN_ID');
$valid_run_id = function_exists('nmkr_is_valid_sync_run_id')
    ? nmkr_is_valid_sync_run_id($expected_run_id)
    : is_string($expected_run_id) && (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $expected_run_id);

if (!$valid_run_id) {
    exit(1);
}

$matches = array();
$cron = _get_cron_array();
if (!is_array($cron)) {
    exit(1);
}

foreach ($cron as $timestamp => $events) {
    if ($timestamp === 'version' || !is_array($events) || !isset($events['nmkr_execute_sync_background'])) {
        continue;
    }
    if (!is_array($events['nmkr_execute_sync_background'])) {
        exit(1);
    }
    foreach ($events['nmkr_execute_sync_background'] as $event) {
        if (!is_array($event) || !array_key_exists('args', $event)) {
            exit(1);
        }
        $matches[] = array(
            'due' => ctype_digit((string) $timestamp) && (int) $timestamp <= time(),
            'args' => $event['args'],
        );
    }
}

exit(
    count($matches) === 1
    && $matches[0]['due']
    && $matches[0]['args'] === array($expected_run_id)
        ? 0
        : 1
);
