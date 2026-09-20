<?php
/** Defensive, streaming policy for NMKR's numbered token-page endpoint. */
if (!defined('ABSPATH')) { exit; }

if (!defined('NMKR_SYNC_TOKEN_PAGE_SIZE')) define('NMKR_SYNC_TOKEN_PAGE_SIZE', 50);
// Plugin safety policy, not a documented NMKR service limit.
if (!defined('NMKR_SYNC_MAX_TOKEN_PAGES_PER_PROJECT')) define('NMKR_SYNC_MAX_TOKEN_PAGES_PER_PROJECT', 2000);

/**
 * Stream numbered token pages without retaining page payloads.
 *
 * Callbacks make this policy deterministic and public-safe to regression test.
 */
function nmkr_stream_token_pages($project_uids, $fetch_page, $process_token, $checkpoint, $progress = null, $max_pages = NMKR_SYNC_MAX_TOKEN_PAGES_PER_PROJECT, $authoritative_stop = null) {
    if (!is_array($project_uids) || !is_callable($fetch_page) || !is_callable($process_token) || !is_callable($checkpoint)) {
        return new WP_Error('nmkr_token_page_invalid_configuration', __('Token pagination could not be configured.', 'connector-for-nmkr'));
    }
    $max_pages = (int) $max_pages;
    if ($max_pages < 1) return new WP_Error('nmkr_token_page_limit', __('The token pagination safety limit is invalid.', 'connector-for-nmkr'));
    $owners = array();
    $unique_count = 0;
    $project_count = count($project_uids);
    foreach (array_values($project_uids) as $project_index => $project_uid) {
        $signatures = array();
        $page_number = 1;
        while (true) {
            if ($page_number > $max_pages) return new WP_Error('nmkr_token_page_limit', __('Token pagination reached the plugin safety limit before an empty terminal page.', 'connector-for-nmkr'));
            $halt = call_user_func($checkpoint, 'before_page_request', $project_uid, $page_number); if (is_wp_error($halt)) return $halt;
            $page = call_user_func($fetch_page, $project_uid, $page_number);
            $halt = call_user_func($checkpoint, 'after_page_request', $project_uid, $page_number);
            // An exact Stop observed during dispatch, at the checkpoint, or in
            // the authoritative run owner always wins. Otherwise retain
            // attempt-evidence failures so the worker routes them to canonical
            // failed finalization instead of replacing them with a checkpoint error.
            if (is_wp_error($page) && $page->get_error_code() === 'sync_stop_requested') return $page;
            if (is_wp_error($halt) && $halt->get_error_code() === 'sync_stop_requested') return $halt;
            if (is_callable($authoritative_stop) && call_user_func($authoritative_stop)) {
                return new WP_Error('sync_stop_requested', __('Synchronization stop was requested.', 'connector-for-nmkr'));
            }
            if (is_wp_error($page) && $page->get_error_code() === 'nmkr_api_metric_evidence_persistence_failure') return $page;
            if (is_wp_error($halt)) return $halt;
            if (is_wp_error($page)) return $page;
            if (!is_array($page) || (!empty($page) && array_keys($page) !== range(0, count($page) - 1))) {
                return new WP_Error('nmkr_token_page_malformed_shape', __('A token page had an invalid top-level shape.', 'connector-for-nmkr'));
            }
            $halt = call_user_func($checkpoint, 'before_page_processing', $project_uid, $page_number); if (is_wp_error($halt)) return $halt;
            if (empty($page)) {
                $halt = call_user_func($checkpoint, 'after_page_processing', $project_uid, $page_number); if (is_wp_error($halt)) return $halt;
                if (is_callable($progress)) call_user_func($progress, $project_index, $project_count, $page_number, $unique_count, true);
                break;
            }
            $uids = array();
            foreach ($page as $token) {
                if (!is_array($token)) return new WP_Error('nmkr_token_page_malformed_record', __('A token page contained an invalid token record.', 'connector-for-nmkr'));
                $raw_uid = array_key_exists('uid', $token) ? $token['uid'] : (array_key_exists('token_uid', $token) ? $token['token_uid'] : null);
                if (!is_string($raw_uid)) return new WP_Error('nmkr_token_page_malformed_record', __('A token page contained an invalid token identifier.', 'connector-for-nmkr'));
                $uid = trim($raw_uid);
                if ($uid !== $raw_uid || $uid === '' || strlen($uid) > 200 || preg_match('/^[A-Za-z0-9_-]+$/', $uid) !== 1) return new WP_Error('nmkr_token_page_malformed_record', __('A token page contained an invalid token identifier.', 'connector-for-nmkr'));
                $uids[] = $uid;
            }
            $signature_uids = array_values(array_unique($uids)); sort($signature_uids, SORT_STRING);
            $signature = hash('sha256', implode("\n", $signature_uids));
            if (isset($signatures[$signature])) return new WP_Error('nmkr_token_page_repeated', __('Token pagination returned a repeated non-empty page.', 'connector-for-nmkr'));
            $signatures[$signature] = true;
            $new_on_page = 0;
            foreach ($page as $offset => $token) {
                $uid = $uids[$offset];
                if (isset($owners[$uid])) {
                    if (!hash_equals((string) $owners[$uid], (string) $project_uid)) return new WP_Error('nmkr_token_project_conflict', __('A token identifier was associated with multiple projects.', 'connector-for-nmkr'));
                    continue;
                }
                $owners[$uid] = (string) $project_uid;
                $new_on_page++;
                $result = call_user_func($process_token, $uid, $project_uid, $token, $page_number);
                if (is_wp_error($result)) return $result;
                $unique_count++;
            }
            if ($new_on_page === 0) return new WP_Error('nmkr_token_page_no_progress', __('A non-empty token page contained no new token identifiers.', 'connector-for-nmkr'));
            $halt = call_user_func($checkpoint, 'after_page_processing', $project_uid, $page_number); if (is_wp_error($halt)) return $halt;
            // Durable progress is intentionally reported at page boundaries,
            // not for every token persisted within the page.
            if (is_callable($progress)) call_user_func($progress, $project_index, $project_count, $page_number, $unique_count, false);
            unset($page);
            $page_number++;
        }
    }
    return array('total_projects' => $project_count, 'total_tokens' => $unique_count, 'uid_owners' => $owners);
}
