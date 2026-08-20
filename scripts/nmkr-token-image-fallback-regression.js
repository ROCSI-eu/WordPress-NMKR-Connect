'use strict';
const assert = require('assert');
let handler;
let existingImages = [];
global.document = {
    addEventListener: (name, callback, capture) => {
        assert.strictEqual(name, 'error');
        assert.strictEqual(capture, true);
        handler = callback;
    },
    querySelectorAll: selector => {
        assert.strictEqual(selector, 'img[data-nmkr-token-image]');
        return existingImages;
    }
};

function image(src, fallback, placeholder, complete, naturalWidth) {
    const attrs = { 'data-nmkr-token-image': '1', 'data-nmkr-fallback-src': fallback, 'data-nmkr-placeholder-src': placeholder };
    let assignedSrc = src;
    const assignments = [];
    return {
        currentSrc: src,
        complete,
        naturalWidth,
        matches: selector => selector === 'img[data-nmkr-token-image]',
        getAttribute: key => attrs[key] || '',
        setAttribute: (key, value) => { attrs[key] = value; },
        removeAttribute: key => { delete attrs[key]; },
        get src() { return assignedSrc; },
        set src(value) { assignedSrc = value; assignments.push(value); },
        attrs,
        assignments
    };
}

const alreadyFailed = image('primary', 'fallback', 'placeholder', true, 0);
const pending = image('pending', 'pending-fallback', 'placeholder', false, 0);
const loaded = image('loaded', 'loaded-fallback', 'placeholder', true, 640);
existingImages = [alreadyFailed, pending, loaded];
require('../js/nmkr-token-image-fallback.js');

assert.deepStrictEqual(alreadyFailed.assignments, ['fallback']);
assert.deepStrictEqual(pending.assignments, []);
assert.deepStrictEqual(loaded.assignments, []);

let img = image('primary', 'fallback', 'placeholder');
assert.strictEqual(img.src, 'primary'); // primary success makes no transition.
img = image('primary', 'fallback', 'placeholder'); handler({ target: img }); assert.strictEqual(img.src, 'fallback');
img.currentSrc = 'fallback'; handler({ target: img }); assert.strictEqual(img.src, 'placeholder');
img.currentSrc = 'placeholder'; handler({ target: img }); assert.strictEqual(img.src, 'placeholder');
assert.deepStrictEqual(img.assignments, ['fallback', 'placeholder']);
img = image('primary', '', 'placeholder'); handler({ target: img }); assert.deepStrictEqual(img.assignments, ['placeholder']);
img.currentSrc = 'placeholder'; handler({ target: img }); assert.strictEqual(img.src, 'placeholder');
assert.deepStrictEqual(img.assignments, ['placeholder']);
img = image('primary', 'primary', 'placeholder'); handler({ target: img }); assert.deepStrictEqual(img.assignments, ['placeholder']);
img.currentSrc = 'placeholder'; handler({ target: img }); assert.deepStrictEqual(img.assignments, ['placeholder']);
const unrelated = image('unrelated', 'fallback', 'placeholder');
unrelated.matches = () => false;
handler({ target: unrelated });
assert.deepStrictEqual(unrelated.assignments, []);
console.log('Token image fallback regression: PASS');
