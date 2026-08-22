'use strict';

// Prevent the browser initializer from running while loading its pure formatter.
global.jQuery = function () { return { ready: function () {} }; };
global.document = {};

const formatters = require('../js/nmkr-sync-progress.js');
const formatter = formatters.nmkrFormatTransportError;
const applicationFormatter = formatters.nmkrFormatApplicationError;
const secret = ['NMKR', 'INTERNAL', 'MARKER', 'DO', 'NOT', 'DISCLOSE'].join('_');

function assertSafe(name, value) {
    if (String(value).indexOf(secret) !== -1) throw new Error(name + '_disclosed');
}

try {
    const allowed = formatter({ status: 409, responseJSON: { data: { error_code: 'sync_already_owned', message: 'A synchronization is already in progress.' } } });
    const startFailureCodes = ['sync_start_rollback_completed', 'sync_start_rollback_lock_unavailable', 'sync_start_rollback_retained', 'sync_start_rollback_owner_changed', 'ajax_handler_failure'];
    const startFailures = startFailureCodes.map(function (code) {
        return formatter({ status: 500, responseJSON: { data: { error_code: code, message: 'Synchronization could not be initialized safely.' } } });
    });
    const application = applicationFormatter({ success: false, data: { error_code: 'option_retrieval_failed', message: 'Synchronization options could not be loaded.' } });
    const unknown = formatter({ status: 502, statusText: secret, responseText: secret, responseJSON: { data: { error_code: secret, message: secret } } });
    const unknownApplication = applicationFormatter({ success: false, data: { error_code: secret, message: secret } });
    const warning = 'Temporary sync hiccup: ' + unknown + '. Retrying soon.';
    const consolePayload = ['Progress poll transient failure:', unknown];
    [allowed, application, unknown, unknownApplication, warning].forEach(function (value, index) { assertSafe('formatted_' + index, value); });
    consolePayload.forEach(function (value, index) { assertSafe('console_' + index, value); });
    if (allowed !== 'HTTP 409: A synchronization is already in progress. [sync_already_owned]') throw new Error('allowlisted_message_missing');
    startFailures.forEach(function (value, index) {
        const expected = 'HTTP 500: Synchronization could not be initialized safely. [' + startFailureCodes[index] + ']';
        if (value !== expected) throw new Error('start_failure_message_missing_' + index);
    });
    if (application !== 'Synchronization options could not be loaded. [option_retrieval_failed]') throw new Error('application_message_missing');
    if (application.indexOf('HTTP') !== -1) throw new Error('application_http_fabricated');
    if (unknown !== 'HTTP 502 [request_failed]') throw new Error('status_classification_missing');
    if (unknownApplication !== '[request_failed]') throw new Error('application_classification_missing');
    console.log('Transport error disclosure regression: PASS');
} catch (error) {
    console.error('Transport error disclosure regression: FAIL ' + error.message);
    process.exit(1);
}
