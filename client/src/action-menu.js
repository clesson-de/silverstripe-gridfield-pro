import { initialiseMenu } from './dropdown-menu.js';
import { actionRequestLock } from './action-request.js';

const $ = window.jQuery;
const cleanups = new WeakMap();

$.entwine('ss', function ($) {
    $('[data-gridfield-menu]').entwine({
        onmatch() {
            this._super();
            const root = this[0];
            const lock = actionRequestLock(root, $);
            const destroy = initialiseMenu(root, root.querySelector('[data-menu-toggle]'),
                root.querySelector('[role="menu"]'), item => lock.accept(item));
            cleanups.set(root, () => {
                destroy();
                lock.destroy();
            });
        },
        onunmatch() {
            cleanups.get(this[0])?.();
            cleanups.delete(this[0]);
            this._super();
        },
    });
});
