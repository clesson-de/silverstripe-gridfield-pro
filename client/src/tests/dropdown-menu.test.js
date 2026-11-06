import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { initialiseMenu, menuPosition, viewportBounds } from '../dropdown-menu.js';
import { actionRequestLock } from '../action-request.js';

function fixture() {
    const dom = new JSDOM(`<div class="grid-field" data-url="/grid"><div id="root" data-error="Action failed">
        <button data-menu-toggle hidden aria-expanded="false">Actions</button>
        <div role="menu"><a href="/first" data-menu-item>First</a>
        <button disabled data-menu-item>Disabled</button>
        <button data-menu-item data-confirm="Continue?" class="gridfield-action-menu__action"
            name="action_gridFieldAlterAction?StateID=gf_test" value="1">Execute</button>
        <a href="/last" data-menu-item>Last</a></div><span role="alert" hidden></span></div>
        <button id="outside">Outside</button></div>`);
    globalThis.window = dom.window;
    globalThis.document = dom.window.document;
    globalThis.AbortController = dom.window.AbortController;
    const root = document.querySelector('#root');
    const toggle = root.querySelector('[data-menu-toggle]');
    const menu = root.querySelector('[role="menu"]');
    toggle.getBoundingClientRect = () => ({ top: 100, bottom: 144, left: 10, right: 100 });
    menu.getBoundingClientRect = () => ({ top: 0, left: 0, width: 200, height: 150 });
    return { dom, root, toggle, menu, entries: [...menu.querySelectorAll('[data-menu-item]')] };
}

function key(element, value) {
    element.dispatchEvent(new window.KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true }));
}

test('menu fits narrow containers and visual viewports with a keyboard', () => {
    const viewport = { left: 15, top: 100, width: 320, height: 300 };
    const position = menuPosition({ left: 300, top: 300, bottom: 344 }, { width: 400, height: 500 }, viewport);
    assert.equal(position.left, 23);
    assert.equal(position.maxWidth, 304);
    assert.equal(position.top, 108);
    assert.equal(position.maxHeight, 188);
    assert.deepEqual(viewportBounds({ visualViewport: { offsetLeft: 15, offsetTop: 100, width: 320, height: 300 } }), viewport);
    assert.deepEqual(viewportBounds({ innerWidth: 768, innerHeight: 1024 }), { left: 0, top: 0, width: 768, height: 1024 });
});

test('enhancement, keyboard navigation, disabled skipping, Escape and cleanup', () => {
    const { dom, root, toggle, menu, entries } = fixture();
    const cleanup = initialiseMenu(root, toggle, menu);
    assert.equal(toggle.hidden, false);
    assert.equal(menu.hidden, true);
    toggle.click();
    assert.equal(document.activeElement, entries[0]);
    assert.equal(toggle.getAttribute('aria-expanded'), 'true');
    key(menu, 'ArrowDown');
    assert.equal(document.activeElement, entries[2]);
    key(menu, 'End');
    assert.equal(document.activeElement, entries[3]);
    key(menu, 'Home');
    assert.equal(document.activeElement, entries[0]);
    key(menu, 'ArrowUp');
    assert.equal(document.activeElement, entries[3]);
    key(menu, 'Escape');
    assert.equal(menu.hidden, true);
    assert.equal(document.activeElement, toggle);
    cleanup();
    assert.equal(menu.hidden, false);
    assert.equal(toggle.hidden, true);
    assert.equal(entries[0].hasAttribute('tabindex'), false);
    assert.equal(root.classList.contains('gridfield-pro-menu-enhanced'), false);
    toggle.click();
    assert.equal(menu.hidden, false);
    dom.window.close();
});

test('only one menu opens; focus exit, outer scroll and outside clicks dismiss it', () => {
    const { dom, root, toggle, menu } = fixture();
    const second = root.cloneNode(true);
    root.after(second);
    const secondToggle = second.querySelector('[data-menu-toggle]');
    secondToggle.getBoundingClientRect = toggle.getBoundingClientRect;
    const secondMenu = second.querySelector('[role="menu"]');
    secondMenu.getBoundingClientRect = menu.getBoundingClientRect;
    const clean1 = initialiseMenu(root, toggle, menu);
    const clean2 = initialiseMenu(second, secondToggle, secondMenu);
    toggle.click();
    secondToggle.click();
    assert.equal(menu.hidden, true);
    assert.equal(secondMenu.hidden, false);
    document.querySelector('#outside').focus();
    assert.equal(secondMenu.hidden, true);
    toggle.click();
    menu.dispatchEvent(new window.Event('scroll'));
    assert.equal(menu.hidden, false);
    document.dispatchEvent(new window.Event('scroll'));
    assert.equal(menu.hidden, true);
    toggle.click();
    document.querySelector('#outside').click();
    assert.equal(menu.hidden, true);
    clean1();
    clean2();
    dom.window.close();
});

test('confirmation cancellation stops native dispatch and disabled entries cannot execute', () => {
    const { dom, root, toggle, menu, entries } = fixture();
    window.confirm = () => false;
    let dispatched = 0;
    document.addEventListener('click', event => { if (event.target === entries[2]) { dispatched++; } });
    const cleanup = initialiseMenu(root, toggle, menu);
    toggle.click();
    entries[2].click();
    assert.equal(dispatched, 0);
    assert.equal(menu.hidden, false);
    window.confirm = () => true;
    entries[2].click();
    assert.equal(dispatched, 1);
    assert.equal(menu.hidden, true);
    cleanup();
    dom.window.close();
});

test('visual viewport resize repositions or closes an offscreen anchor; listeners are removed', () => {
    const { dom, root, toggle, menu } = fixture();
    const viewport = new window.EventTarget();
    Object.assign(viewport, { offsetLeft: 0, offsetTop: 0, width: 390, height: 500 });
    window.visualViewport = viewport;
    const cleanup = initialiseMenu(root, toggle, menu);
    toggle.click();
    viewport.dispatchEvent(new window.Event('resize'));
    assert.equal(menu.hidden, false);
    assert.equal(menu.style.maxWidth, '374px');
    viewport.offsetTop = 300;
    viewport.dispatchEvent(new window.Event('scroll'));
    assert.equal(menu.hidden, true);
    cleanup();
    viewport.dispatchEvent(new window.Event('resize'));
    assert.equal(menu.hidden, false);
    dom.window.close();
});

test('request lock blocks repeat POSTs and releases after failure without disabling the action', async () => {
    const { dom, root, entries } = fixture();
    let ajaxSend;
    let restored = false;
    const $ = () => ({
        on(_name, listener) { ajaxSend = listener; },
        off(_name, listener) { assert.equal(listener, ajaxSend); ajaxSend = undefined; },
        data() { return '/grid'; },
        find() {
            return {
                children: () => ({ clone: () => ['saved row'] }),
                empty: () => ({ append(rows) { assert.deepEqual(rows, ['saved row']); restored = true; } }),
            };
        },
    });
    const lock = actionRequestLock(root, $);
    const button = entries[2];
    assert.equal(lock.accept(button), true);
    assert.equal(lock.accept(button), false);
    assert.equal(button.disabled, false);
    let complete;
    let failure;
    ajaxSend(null, { always(callback) { complete = callback; }, fail(callback) { failure = callback; } },
        { url: '/grid', data: `${encodeURIComponent(button.name)}=1` });
    await Promise.resolve();
    assert.equal(root.closest('.grid-field').getAttribute('aria-busy'), 'true');
    failure();
    assert.equal(restored, true);
    assert.equal(root.querySelector('[role="alert"]').textContent, 'Action failed');
    assert.equal(root.querySelector('[role="alert"]').hidden, false);
    complete(); // Both success and error use the jqXHR completion contract.
    assert.equal(root.closest('.grid-field').hasAttribute('aria-busy'), false);
    assert.equal(lock.accept(button), true);
    await Promise.resolve(); // A cancelled core handler must not leave the menu locked.
    assert.equal(root.closest('.grid-field').hasAttribute('aria-busy'), false);
    lock.destroy();
    assert.equal(ajaxSend, undefined);
    dom.window.close();
});
