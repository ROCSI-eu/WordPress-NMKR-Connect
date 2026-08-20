'use strict';
const assert = require('assert');
let handler;
global.document = { addEventListener: (name, callback, capture) => { assert.strictEqual(name, 'error'); assert.strictEqual(capture, true); handler = callback; } };
require('../js/nmkr-token-image-fallback.js');

function image(src, fallback, placeholder) {
    const attrs = { 'data-nmkr-token-image': '1', 'data-nmkr-fallback-src': fallback, 'data-nmkr-placeholder-src': placeholder };
    return { src, currentSrc: src, matches: () => true, getAttribute: key => attrs[key] || '', setAttribute: (key, value) => { attrs[key] = value; }, removeAttribute: key => { delete attrs[key]; }, attrs };
}
let img = image('primary', 'fallback', 'placeholder');
assert.strictEqual(img.src, 'primary'); // primary success makes no transition.
img = image('primary', 'fallback', 'placeholder'); handler({ target: img }); assert.strictEqual(img.src, 'fallback');
img.currentSrc = 'fallback'; handler({ target: img }); assert.strictEqual(img.src, 'placeholder');
img.currentSrc = 'placeholder'; handler({ target: img }); assert.strictEqual(img.src, 'placeholder');
img = image('primary', '', 'placeholder'); handler({ target: img }); assert.strictEqual(img.src, 'placeholder');
img.currentSrc = 'placeholder'; handler({ target: img }); assert.strictEqual(img.src, 'placeholder');
console.log('Token image fallback regression: PASS');
