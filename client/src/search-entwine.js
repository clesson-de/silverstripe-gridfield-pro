import { initialiseSearch, searchURL } from './search.js';

const $ = window.jQuery;
const instances = new WeakMap();

$.entwine('ss', function ($) {
    $('.grid-field .gridfield-search').entwine({
        onmatch() {
            this._super();
            const root = this[0];
            const grid = root.closest('.grid-field');
            instances.set(root, initialiseSearch(root, query => {
                window.location.assign(searchURL(
                    window.location.href, root.dataset.stateKey,
                    JSON.parse(grid.querySelector('input[name$="[GridState]"]').value),
                    query, root.dataset.paginator === 'true'
                ));
            }));
        },
        onunmatch() {
            instances.get(this[0])?.destroy();
            instances.delete(this[0]);
            this._super();
        },
    });
});
