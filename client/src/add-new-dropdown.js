import { initialiseMenu } from './dropdown-menu.js';

const $ = window.jQuery;
const cleanups = new WeakMap();

$.entwine('ss', function ($) {
    $('.gridfield-add-new-dropdown').entwine({
        onmatch() {
            this._super();
            const root = this[0];
            const toggle = root.querySelector('[data-menu-toggle]');
            if (toggle) {
                cleanups.set(root, initialiseMenu(root, toggle, root.querySelector('[role="menu"]')));
            }
        },
        onunmatch() {
            cleanups.get(this[0])?.();
            cleanups.delete(this[0]);
            this._super();
        },
    });
});
