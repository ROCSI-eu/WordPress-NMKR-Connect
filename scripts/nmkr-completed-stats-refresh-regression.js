#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const {
    nmkrHasCanonicalLatestRun,
    nmkrCompletedStatsRetryDelay
} = require('../js/admin/nmkr-dashboard.js');

const canonicalRun = {
    terminal_result: 'success',
    status: 'completed',
    items_processed: 100,
    items_successful: 100,
    items_failed: 0,
    items_skipped: 0,
    token_details_synced: 100
};

assert.strictEqual(nmkrHasCanonicalLatestRun(null), false);
assert.strictEqual(nmkrHasCanonicalLatestRun({}), false);
assert.strictEqual(nmkrHasCanonicalLatestRun(canonicalRun), true);
assert.strictEqual(
    nmkrHasCanonicalLatestRun({ ...canonicalRun, items_failed: null }),
    false
);
assert.strictEqual(
    nmkrHasCanonicalLatestRun({ ...canonicalRun, items_failed: 0 }),
    true
);

assert.strictEqual(nmkrCompletedStatsRetryDelay(0), 750);
assert.strictEqual(nmkrCompletedStatsRetryDelay(1), 1500);
assert.strictEqual(nmkrCompletedStatsRetryDelay(2), 2500);
assert.strictEqual(nmkrCompletedStatsRetryDelay(3), null);
assert.strictEqual(nmkrCompletedStatsRetryDelay(-1), null);
assert.strictEqual(nmkrCompletedStatsRetryDelay(1.5), null);

const progressSource = fs.readFileSync(
    path.join(__dirname, '..', 'js', 'nmkr-sync-progress.js'),
    'utf8'
);
const legacyEvent = "$(document).trigger('nmkr_sync_completed');";
const dashboardEvent = "$(document).trigger('nmkr:sync:completed');";
const legacyIndex = progressSource.indexOf(legacyEvent);
const dashboardIndex = progressSource.indexOf(dashboardEvent);

assert.ok(legacyIndex !== -1, 'legacy completion event must remain available');
assert.ok(
    dashboardIndex > legacyIndex,
    'dashboard completion event must be emitted after the legacy event'
);

console.log('PASS: completed statistics refresh retry contract');
