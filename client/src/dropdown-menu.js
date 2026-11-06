let activeMenu;

/** Returns the visible viewport in layout-viewport coordinates. */
export function viewportBounds(view = window) {
    const viewport = view.visualViewport;
    return {
        left: viewport?.offsetLeft ?? 0,
        top: viewport?.offsetTop ?? 0,
        width: viewport?.width ?? view.innerWidth,
        height: viewport?.height ?? view.innerHeight,
    };
}

/** Computes bounds without requiring a DOM, including the on-screen keyboard case. */
export function menuPosition(anchor, size, viewport) {
    const gap = 4;
    const inset = 8;
    const left = viewport.left + inset;
    const top = viewport.top + inset;
    const right = viewport.left + viewport.width - inset;
    const bottom = viewport.top + viewport.height - inset;
    const below = Math.max(0, bottom - anchor.bottom - gap);
    const above = Math.max(0, anchor.top - top - gap);
    const upwards = below < size.height && above > below;
    const maxHeight = upwards ? above : below;
    const width = Math.min(size.width, Math.max(0, right - left));
    const height = Math.min(size.height, maxHeight);
    return {
        left: Math.max(left, Math.min(anchor.left, right - width)),
        top: upwards ? anchor.top - height - gap : Math.max(top, anchor.bottom + gap),
        maxHeight,
        maxWidth: Math.max(0, right - left),
    };
}

/** Enhances an accessible inline list without moving it out of its CMS form. */
export function initialiseMenu(root, toggle, menu, onActivate = () => true) {
    const events = new AbortController();
    const listen = (element, type, listener, options = {}) =>
        element.addEventListener(type, listener, { ...options, signal: events.signal });
    const allItems = () => [...menu.querySelectorAll('[data-menu-item]')];
    const items = () => allItems().filter(item => !item.disabled && item.getAttribute('aria-disabled') !== 'true');
    const originalTabs = new Map(allItems().map(item => [item, item.getAttribute('tabindex')]));
    const close = (restoreFocus = false) => {
        menu.hidden = true;
        menu.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
        if (activeMenu === close) {
            activeMenu = undefined;
        }
        if (restoreFocus) {
            toggle.focus();
        }
    };
    const position = () => {
        const anchor = toggle.getBoundingClientRect();
        const viewport = viewportBounds();
        if (anchor.bottom <= viewport.top || anchor.top >= viewport.top + viewport.height
            || anchor.right <= viewport.left || anchor.left >= viewport.left + viewport.width) {
            close();
            return;
        }
        const bounds = menuPosition(anchor, {
            width: menu.getBoundingClientRect().width, height: menu.scrollHeight,
        }, viewport);
        menu.style.maxWidth = `${bounds.maxWidth}px`;
        menu.style.maxHeight = `${bounds.maxHeight}px`;
        // Fixed-position descendants can have transformed containing blocks.
        // Correct against the measured result rather than assuming viewport ancestry.
        menu.style.left = `${bounds.left}px`;
        menu.style.top = `${bounds.top}px`;
        const actual = menu.getBoundingClientRect();
        menu.style.left = `${bounds.left + bounds.left - actual.left}px`;
        menu.style.top = `${bounds.top + bounds.top - actual.top}px`;
    };
    const open = (last = false) => {
        activeMenu?.();
        activeMenu = close;
        menu.hidden = false;
        menu.classList.add('show');
        toggle.setAttribute('aria-expanded', 'true');
        position();
        if (!menu.hidden) {
            const available = items();
            (last ? available.at(-1) : available[0])?.focus();
        }
    };
    const navigate = event => {
        const available = items();
        const index = available.indexOf(document.activeElement);
        const destinations = {
            ArrowDown: (index + 1) % available.length,
            ArrowUp: (index - 1 + available.length) % available.length,
            Home: 0, End: available.length - 1,
        };
        if (Object.hasOwn(destinations, event.key)) {
            event.preventDefault();
            available[destinations[event.key]]?.focus();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
        } else if (event.key === ' ' && document.activeElement?.matches('a[data-menu-item]')) {
            event.preventDefault();
            document.activeElement.click();
        }
    };
    allItems().forEach(item => item.setAttribute('tabindex', '-1'));
    root.classList.add('gridfield-pro-menu-enhanced');
    menu.classList.add('dropdown-menu');
    toggle.hidden = false;
    close();
    listen(toggle, 'click', () => menu.hidden ? open() : close());
    listen(toggle, 'keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            open(event.key === 'ArrowUp');
        }
    });
    listen(menu, 'keydown', navigate);
    listen(menu, 'click', event => {
        const item = event.target.closest('[data-menu-item]');
        if (!item) {
            return;
        }
        if (item.disabled || item.getAttribute('aria-disabled') === 'true'
            || (item.dataset.confirm && !window.confirm(item.dataset.confirm)) || !onActivate(item)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        close(true);
    }, { capture: true });
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
    const reposition = () => { if (!menu.hidden) { position(); } };
    listen(window, 'resize', reposition);
    if (window.visualViewport) {
        listen(window.visualViewport, 'resize', reposition);
        listen(window.visualViewport, 'scroll', reposition);
    }
    listen(document, 'scroll', event => {
        if (!menu.contains(event.target)) {
            close();
        }
    }, { capture: true });
    return () => {
        close();
        events.abort();
        root.classList.remove('gridfield-pro-menu-enhanced');
        menu.classList.remove('dropdown-menu');
        menu.removeAttribute('style');
        menu.hidden = false;
        toggle.hidden = true;
        originalTabs.forEach((value, item) => {
            if (value === null) {
                item.removeAttribute('tabindex');
            } else {
                item.setAttribute('tabindex', value);
            }
        });
    };
}
