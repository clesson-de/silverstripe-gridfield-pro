import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { initialiseSearch, searchURL } from '../search.js';

function setup() {
    const dom = new JSDOM(`<div class="gridfield-search">
        <input class="gridfield-search__input" value="old">
        <span class="gridfield-search__icon"></span>
        <button type="button" class="gridfield-search__clear">X</button>
    </div>`);
    const root = dom.window.document.querySelector('div');
    const input = root.querySelector('input');
    const queries = [];
    const instance = initialiseSearch(root, query => queries.push(query));
    const enter = (isComposing = false) => {
        const event = new dom.window.KeyboardEvent('keydown', {
            key: 'Enter', bubbles: true, cancelable: true, isComposing,
        });
        input.dispatchEvent(event);
        return event;
    };
    return { dom, root, input, queries, instance, enter };
}

test('Enter submits short and empty queries; typing never submits', () => {
    const ui = setup();
    for (const value of ['a', 'many characters', '']) {
        ui.input.value = value;
        ui.input.dispatchEvent(new ui.dom.window.Event('input'));
        assert.deepEqual(ui.queries, []);
    }
    ui.input.value = ' ab ';
    assert.equal(ui.enter().defaultPrevented, true);
    ui.input.value = '';
    ui.enter();
    assert.deepEqual(ui.queries, ['ab', '']);
    ui.instance.destroy();
    ui.dom.window.close();
});

test('X clears and focuses the field and immediately submits an empty query', () => {
    const ui = setup();
    ui.root.querySelector('button').click();
    assert.equal(ui.input.value, '');
    assert.equal(ui.dom.window.document.activeElement, ui.input);
    assert.equal(ui.root.querySelector('button').hidden, true);
    assert.equal(ui.root.querySelector('span').hidden, false);
    assert.deepEqual(ui.queries, ['']);
    ui.instance.destroy();
    ui.dom.window.close();
});

test('IME composition does not submit and cleanup removes handlers', () => {
    const ui = setup();
    ui.input.dispatchEvent(new ui.dom.window.Event('compositionstart'));
    ui.enter();
    ui.input.dispatchEvent(new ui.dom.window.Event('compositionend'));
    ui.enter(true);
    assert.deepEqual(ui.queries, []);
    ui.enter();
    assert.deepEqual(ui.queries, ['old']);
    ui.instance.destroy();
    ui.enter();
    assert.deepEqual(ui.queries, ['old']);
    ui.dom.window.close();
});

test('navigation retains unrelated URL/grid state and resets only the current paginator', () => {
    const state = {
        GridFieldPaginator: { currentPage: 8, other: 'retained' },
        GridFieldSortableHeader: { SortColumn: 'Name' },
    };
    const url = new URL(searchURL(
        'https://example.test/admin/contacts?tab=main&gridState-Other-0=keep#section',
        'gridState-Contacts-0', state, ' name & email ', true
    ));
    assert.equal(url.searchParams.get('tab'), 'main');
    assert.equal(url.searchParams.get('gridState-Other-0'), 'keep');
    assert.equal(url.hash, '#section');
    const result = JSON.parse(url.searchParams.get('gridState-Contacts-0'));
    assert.equal(result.GridFieldSearch.query, 'name & email');
    assert.equal(result.GridFieldPaginator.currentPage, 1);
    assert.equal(result.GridFieldPaginator.other, 'retained');
    assert.equal(result.GridFieldSortableHeader.SortColumn, 'Name');
    assert.equal(state.GridFieldPaginator.currentPage, 8);
});

test('grids without paging do not gain paginator state', () => {
    const url = new URL(searchURL('https://example.test/admin', 'gridState-Items-1', {}, '', false));
    assert.deepEqual(JSON.parse(url.searchParams.get('gridState-Items-1')), { GridFieldSearch: { query: '' } });
});
