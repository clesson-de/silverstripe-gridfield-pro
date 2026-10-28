export async function fetchOptions(url, query, signal, request = fetch) {
    const target = new URL(url, document.baseURI);
    target.searchParams.set('gridfield_relationsearch', query);
    const response = await request(target, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal,
    });
    if (!response.ok) {
        throw new Error(`Relation search failed: ${response.status}`);
    }
    const options = await response.json();
    if (!Array.isArray(options) || !options.every(option =>
        Number.isInteger(Number(option.id)) && Number(option.id) > 0 && typeof option.label === 'string'
    )) {
        throw new Error('Invalid relation search response.');
    }
    return options;
}
