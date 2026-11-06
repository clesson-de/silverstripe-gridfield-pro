import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { initialiseCharFilter } from '../char-filter.js';

test('mobile characters and reset dispatch existing native actions, including zero', () => {
    const dom = new JSDOM(`<div class="gridfield-charfilter">
        <select class="gridfield-charfilter__select">
            <option value="">All</option><option selected>A</option><option>B</option><option>0</option>
        </select>
        <button data-filter-char="all">All</button><button data-filter-char="A">A</button>
        <button data-filter-char="B">B</button><button data-filter-char="0">0</button>
    </div>`);
    const root = dom.window.document.querySelector('.gridfield-charfilter');
    const select = root.querySelector('select');
    const clicked = [];
    root.addEventListener('click', event => clicked.push(event.target.dataset.filterChar));
    const destroy = initialiseCharFilter(root);
    assert.ok(root.classList.contains('gridfield-charfilter--enhanced'));
    for (const char of ['B', '', '0']) {
        select.value = char;
        select.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
        assert.equal(select.value, 'A', 'Server refresh owns state; failure leaves the old selection');
    }
    assert.deepEqual(clicked, ['B', 'all', '0']);
    destroy();
    assert.equal(root.classList.contains('gridfield-charfilter--enhanced'), false);
    select.value = 'B';
    select.dispatchEvent(new dom.window.Event('change'));
    assert.deepEqual(clicked, ['B', 'all', '0'], 'Refresh cleanup removes old listeners');
    dom.window.close();
});
