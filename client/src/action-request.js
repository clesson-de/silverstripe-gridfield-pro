const requests = new WeakMap();

/** Locks only menu POST actions, leaving core serialization and error handling intact. */
export function actionRequestLock(root, $) {
    const grid = root.closest('.grid-field');
    const error = root.querySelector('[role="alert"]');
    let pending;
    const release = () => {
        if (pending && requests.get(grid) === pending) {
            requests.delete(grid);
            grid.removeAttribute('aria-busy');
        }
        pending = undefined;
    };
    const sent = (_event, xhr, settings) => {
        if (pending && !pending.xhr && settings.url === $(grid).data('url') && typeof settings.data === 'string'
            && settings.data.includes(`${encodeURIComponent(pending.name)}=`)) {
            pending.xhr = xhr;
            const rows = pending.rows;
            xhr.fail(() => {
                if (root.isConnected) {
                    $(grid).find('tbody').empty().append(rows);
                    if (error) {
                        error.textContent = root.dataset.error;
                        error.hidden = false;
                    }
                }
            });
            xhr.always(release);
        }
    };
    $(document).on('ajaxSend', sent);
    return {
        accept(item) {
            if (!item.matches('button.gridfield-action-menu__action')) {
                return true;
            }
            if (requests.has(grid)) {
                return false;
            }
            pending = { name: item.name, rows: $(grid).find('tbody').children().clone(true) };
            if (error) {
                error.hidden = true;
            }
            requests.set(grid, pending);
            grid.setAttribute('aria-busy', 'true');
            // Core dispatch is synchronous; don't retain a lock if another handler cancels it.
            queueMicrotask(() => { if (pending && !pending.xhr) { release(); } });
            return true;
        },
        destroy() {
            $(document).off('ajaxSend', sent);
            release();
        },
    };
}
