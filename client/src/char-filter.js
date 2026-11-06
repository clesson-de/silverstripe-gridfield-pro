/** Reuses native GridField actions rather than introducing a second write endpoint. */
export function initialiseCharFilter(root) {
    const select = root.querySelector('.gridfield-charfilter__select');
    if (!select) {
        throw new Error('A character filter requires its character selector.');
    }
    const original = select.value;
    const change = () => {
        const action = [...root.querySelectorAll('button[data-filter-char]')]
            .find(button => button.dataset.filterChar === (select.value || 'all'));
        if (!action) {
            throw new Error('The selected character has no native GridField action.');
        }
        action.click();
        // The core refresh owns the selected state; on failure retain the previous value.
        select.value = original;
    };
    select.addEventListener('change', change);
    root.classList.add('gridfield-charfilter--enhanced');
    return () => {
        select.removeEventListener('change', change);
        root.classList.remove('gridfield-charfilter--enhanced');
    };
}
