import { initialiseCharFilter } from './char-filter.js';

const $ = window.jQuery;
const cleanups = new WeakMap();

$.entwine('ss', function ($) {
    $('.gridfield-pro-toolbar .gridfield-charfilter').entwine({
        onmatch() {
            this._super();
            cleanups.set(this[0], initialiseCharFilter(this[0]));
        },
        onunmatch() {
            cleanups.get(this[0])?.();
            cleanups.delete(this[0]);
            this._super();
        },
    });
});
