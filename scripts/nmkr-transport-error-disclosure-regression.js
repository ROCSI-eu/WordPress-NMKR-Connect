'use strict';

// Prevent the browser initializer from running while loading its pure formatter.
global.jQuery = function () { return { ready: function () {} }; };
global.document = {};

const formatter = require('../js/nmkr-sync-progress.js').nmkrFormatTransportError;
const secret = ['NMKR', 'INTERNAL', 'MARKER', 'DO', 'NOT', 'DISCLOSE'].join('_');

function assertSafe(name, value) {
    if (String(value).indexOf(secret) !== -1) throw new Error(name + '_disclosed');
}

try {
    const allowed = formatter({ status: 503, statusText: secret, responseText: secret, responseJSON: { data: { error_code: 'option_retrieval_failed', message: secret } } });
    const unknown = formatter({ status: 502, statusText: secret, responseText: secret, responseJSON: { data: { error_code: secret, message: secret } } });
    const warning = 'Temporary sync hiccup: ' + allowed + '. Retrying soon.';
    const consolePayload = ['Progress poll transient failure:', unknown];
    [allowed, unknown, warning].forEach(function (value, index) { assertSafe('formatted_' + index, value); });
    consolePayload.forEach(function (value, index) { assertSafe('console_' + index, value); });
    if (allowed !== 'HTTP 503 [option_retrieval_failed]') throw new Error('allowlisted_code_missing');
    if (unknown !== 'HTTP 502 [request_failed]') throw new Error('status_classification_missing');
    console.log('Transport error disclosure regression: PASS');
} catch (error) {
    console.error('Transport error disclosure regression: FAIL ' + error.message);
    process.exit(1);
}
