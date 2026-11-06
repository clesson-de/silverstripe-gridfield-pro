/** Builds a full-page navigation using Silverstripe's native URL GridState. */
export function searchURL(url, key, state, query, hasPaginator) {
    const target = new URL(url);
    const next = { ...state, GridFieldSearch: { query: query.trim() } };
    if (hasPaginator) {
        next.GridFieldPaginator = { ...state.GridFieldPaginator, currentPage: 1 };
    }
    target.searchParams.set(key, JSON.stringify(next));
    return target.href;
}

/** Enter applies the draft; the clear control immediately removes the search. */
export function initialiseSearch(root, submit) {
    const input = root.querySelector('.gridfield-search__input');
    const clear = root.querySelector('.gridfield-search__clear');
    const icon = root.querySelector('.gridfield-search__icon');
    let composing = false;
    const updateIcon = () => {
        clear.hidden = input.value.length === 0;
        icon.hidden = !clear.hidden;
    };
    const keydown = event => {
        if (event.key === 'Enter' && !event.isComposing && !composing) {
            event.preventDefault();
            event.stopImmediatePropagation();
            submit(input.value.trim());
        }
    };
    const reset = () => {
        input.value = '';
        updateIcon();
        input.focus();
        submit('');
    };
    const start = () => { composing = true; };
    const end = () => { composing = false; };
    input.addEventListener('input', updateIcon);
    input.addEventListener('keydown', keydown);
    input.addEventListener('compositionstart', start);
    input.addEventListener('compositionend', end);
    clear.addEventListener('click', reset);
    updateIcon();
    return {
        destroy() {
            input.removeEventListener('input', updateIcon);
            input.removeEventListener('keydown', keydown);
            input.removeEventListener('compositionstart', start);
            input.removeEventListener('compositionend', end);
            clear.removeEventListener('click', reset);
        },
    };
}
