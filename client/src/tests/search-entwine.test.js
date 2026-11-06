import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

test('Enter uses native hidden GridState for full navigation, without submitting the form or AJAX', async () => {
    const dom = new JSDOM(`<form><div class="grid-field">
        <input type="hidden" name="Records[GridState]"
            value='{"GridFieldPaginator":{"currentPage":4},"Filter":{"value":"active"}}'>
        <div class="gridfield-search" data-state-key="gridState-Records-0" data-paginator="true">
            <input class="gridfield-search__input" value="needle">
            <span class="gridfield-search__icon"></span>
            <button type="button" class="gridfield-search__clear">X</button>
        </div></div></form>`);
    let methods;
    const navigations = [];
    const $ = () => ({ entwine: value => { methods = value; } });
    $.entwine = (namespace, register) => register($);
    globalThis.window = {
        jQuery: $,
        location: { href: 'https://example.test/admin/contacts', assign: url => navigations.push(url) },
    };
    try {
        await import('../search-entwine.js');
        const root = dom.window.document.querySelector('.gridfield-search');
        const instance = { 0: root, _super() {} };
        methods.onmatch.call(instance);
        const input = root.querySelector('input');
        input.dispatchEvent(new dom.window.KeyboardEvent('keydown', {
            key: 'Enter', bubbles: true, cancelable: true,
        }));
        assert.equal(navigations.length, 1);
        const state = JSON.parse(new URL(navigations[0]).searchParams.get('gridState-Records-0'));
        assert.equal(state.GridFieldSearch.query, 'needle');
        assert.equal(state.GridFieldPaginator.currentPage, 1);
        assert.equal(state.Filter.value, 'active');
        methods.onunmatch.call(instance);
        input.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'Enter' }));
        assert.equal(navigations.length, 1);
    } finally {
        delete globalThis.window;
        dom.window.close();
    }
});
