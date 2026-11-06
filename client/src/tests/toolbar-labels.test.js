import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

test('a creation action immediately after a right-aligned search has a spaced separator', () => {
    const styles = readFileSync(new URL('../styles.css', import.meta.url), 'utf8');
    const dom = new JSDOM(`<style>${styles}</style>
        <div class="gridfield-pro-toolbar">
            <div class="gridfield-pro-toolbar__group--right">
                <div class="gridfield-pro-toolbar__item"><div class="gridfield-search"></div></div>
                <div id="creation" class="gridfield-pro-toolbar__item"><div class="gridfield-add-new-dropdown"></div></div>
            </div>
        </div>`);
    const computed = dom.window.getComputedStyle(dom.window.document.getElementById('creation'));
    assert.equal(computed.borderLeftWidth, '1px');
    assert.equal(computed.borderLeftStyle, 'solid');
    assert.equal(computed.marginLeft, '0.75rem');
    assert.equal(computed.paddingLeft, '0.75rem');
    dom.window.close();
});

test('toolbar dropdowns reserve space for their arrow without changing outside or multi-select fields', () => {
    const styles = readFileSync(new URL('../styles.css', import.meta.url), 'utf8');
    const dom = new JSDOM(`<style>${styles}</style>
        <div class="gridfield-pro-toolbar">
            <select id="filter" class="dropdown" style="min-width:140px">
                <option>All months</option>
            </select>
            <select id="multiple" class="dropdown" multiple><option>One</option></select>
        </div>
        <select id="outside" class="dropdown"><option>Outside</option></select>`);
    const { document, getComputedStyle } = dom.window;
    const computed = getComputedStyle(document.getElementById('filter'));
    assert.equal(computed.paddingRight, '2rem');
    assert.equal(computed.paddingLeft, '0.65rem');
    assert.equal(computed.boxSizing, 'border-box');
    assert.equal(computed.flexShrink, '0');
    assert.equal(computed.appearance, 'none');
    assert.equal(computed.backgroundPosition, 'calc(100% - 0.75rem) center');
    assert.equal(computed.backgroundRepeat, 'no-repeat');
    assert.equal(computed.backgroundSize, '0.75rem 0.5rem');
    assert.match(computed.backgroundImage, /data:image\/svg\+xml/);
    assert.notEqual(getComputedStyle(document.getElementById('multiple')).paddingRight, '2rem');
    assert.notEqual(getComputedStyle(document.getElementById('outside')).paddingRight, '2rem');
    dom.window.close();
});

test('toolbar field labels are visually hidden without removing accessible names or controls', () => {
    const styles = readFileSync(new URL('../styles.css', import.meta.url), 'utf8');
    const dom = new JSDOM(`<style>${styles}</style>
        <div class="gridfield-pro-toolbar">
            <label id="search-label" class="sr-only" for="search">Search in name</label>
            <input id="search">
            <label id="wrapped"><input type="checkbox">Enabled</label>
            <span class="gridfield-pro-toolbar__label">Explicit group title</span>
            <button>New</button>
        </div>
        <label id="outside" for="outside-input">Outside label</label><input id="outside-input">`);
    const { document, getComputedStyle } = dom.window;
    const label = document.getElementById('search-label');
    const computed = getComputedStyle(label);
    assert.equal(computed.position, 'absolute');
    assert.equal(computed.width, '1px');
    assert.equal(computed.overflow, 'hidden');
    assert.notEqual(computed.display, 'none');
    assert.notEqual(computed.visibility, 'hidden');
    assert.equal(document.getElementById('search').labels[0], label);
    assert.notEqual(getComputedStyle(document.getElementById('wrapped')).position, 'absolute');
    assert.notEqual(getComputedStyle(document.getElementById('outside')).position, 'absolute');
    assert.notEqual(getComputedStyle(document.querySelector('button')).display, 'none');
    dom.window.close();
});
