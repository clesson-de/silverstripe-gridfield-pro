const $ = window.jQuery;
const cleanups = new WeakMap();

$.entwine('ss', function ($) {
    $('.gridfield-add-new-dropdown').entwine({
        onmatch() {
            this._super();
            const root = this[0];
            const toggle = root.querySelector('.gridfield-add-new-dropdown__toggle');
            if (!toggle) {
                return;
            }
            const menu = root.querySelector('[role="menu"]');
            const items = [...menu.querySelectorAll('[role="menuitem"]')];
            const events = new AbortController();
            const listen = (element, type, listener, options = {}) =>
                element.addEventListener(type, listener, { ...options, signal: events.signal });
            const close = (restoreFocus = false) => {
                menu.hidden = true;
                menu.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
                if (restoreFocus) {
                    toggle.focus();
                }
            };
            const position = () => {
                const anchor = toggle.getBoundingClientRect();
                const spaceBelow = window.innerHeight - anchor.bottom - 8;
                const spaceAbove = anchor.top - 8;
                const above = spaceBelow < menu.scrollHeight && spaceAbove > spaceBelow;
                menu.style.maxHeight = `${Math.max(0, above ? spaceAbove : spaceBelow)}px`;
                const height = menu.getBoundingClientRect().height;
                const width = menu.getBoundingClientRect().width;
                menu.style.top = `${above ? anchor.top - height - 4 : anchor.bottom + 4}px`;
                menu.style.left = `${Math.max(8, Math.min(anchor.left, window.innerWidth - width - 8))}px`;
            };
            const open = (index = 0) => {
                menu.hidden = false;
                menu.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
                position();
                items[index].focus();
            };
            listen(toggle, 'click', () => menu.hidden ? open() : close());
            listen(toggle, 'keydown', event => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    open(event.key === 'ArrowUp' ? items.length - 1 : 0);
                }
            });
            listen(menu, 'keydown', event => {
                const index = items.indexOf(document.activeElement);
                const destinations = { ArrowDown: (index + 1) % items.length, ArrowUp: (index - 1 + items.length) % items.length,
                    Home: 0, End: items.length - 1 };
                if (Object.hasOwn(destinations, event.key)) {
                    event.preventDefault();
                    items[destinations[event.key]].focus();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    close(true);
                } else if (event.key === ' ') {
                    event.preventDefault();
                    document.activeElement.click();
                }
            });
            listen(menu, 'click', () => close());
            listen(root, 'focusout', event => {
                if (!root.contains(event.relatedTarget)) {
                    close();
                }
            });
            listen(document, 'click', event => {
                if (!root.contains(event.target)) {
                    close();
                }
            });
            listen(window, 'resize', () => close());
            listen(document, 'scroll', event => {
                if (!menu.contains(event.target)) {
                    close();
                }
            }, { capture: true });
            cleanups.set(root, () => {
                close();
                events.abort();
            });
        },
        onunmatch() {
            cleanups.get(this[0])?.();
            cleanups.delete(this[0]);
            this._super();
        },
    });
});
