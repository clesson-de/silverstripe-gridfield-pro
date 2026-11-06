import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

test('opt-in Chosen always enables substring search and cleans up on GridField replacement', async () => {
    let methods;
    let selector;
    const $ = value => {
        selector = value;
        return { entwine: value => { methods = value; } };
    };
    $.entwine = (namespace, register) => register($);
    globalThis.window = { jQuery: $ };
    const dom = new JSDOM('<form><input></form>');
    try {
        await import('../searchable-dropdown.js');
        assert.equal(selector, '.grid-field select.gridfield-searchable-dropdown');
        let chosen;
        let options;
        let initializations = 0;
        let cleanup = false;
        const search = dom.window.document.querySelector('input');
        const field = {
            addClass(value) { search.classList.add(value); return this; },
            attr(name, value) { search.setAttribute(name, value); return this; },
            on(name, handler) { search.addEventListener('keydown', handler); this.handler = handler; return this; },
            off(name) { cleanup = name === '.gridfieldSearchable'; search.removeEventListener('keydown', this.handler); },
        };
        const instance = {
            _super() {},
            attr() { return 'Project'; },
            data() { return chosen; },
            chosen(value) {
                if (value === 'destroy') {
                    chosen = undefined;
                } else {
                    options = value;
                    initializations++;
                    chosen = { search_field: field };
                }
            },
        };
        methods.onmatch.call(instance);
        methods.onmatch.call(instance);
        assert.equal(initializations, 1);
        assert.deepEqual(options, { disable_search_threshold: 0, search_contains: true, width: '100%' });
        assert.ok(search.classList.contains('no-change-track'));
        assert.equal(search.getAttribute('aria-label'), 'Project');
        let bubbled = 0;
        dom.window.document.querySelector('form').addEventListener('keydown', () => bubbled++);
        const event = new dom.window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
        search.dispatchEvent(event);
        assert.equal(event.defaultPrevented, true);
        assert.equal(bubbled, 0);
        methods.onunmatch.call(instance);
        assert.equal(cleanup, true);
        assert.equal(chosen, undefined);
        methods.onmatch.call(instance);
        assert.equal(initializations, 2);
        methods.onunmatch.call(instance);
    } finally {
        delete globalThis.window;
        dom.window.close();
    }
});
