import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fetchOptions } from '../remote-options.js';

globalThis.window = { location: { href: 'https://cms.example/admin/' } };
globalThis.document = { baseURI: 'https://cms.example/' };

test('resolves CMS-relative search URLs against the document base, not the current edit URL', async () => {
    window.location.href = 'https://cms.example/admin/pages/edit/show/12';
    await fetchOptions('admin/pages/EditForm/field/Slides/existing-search', 'sl', undefined, async url => {
        assert.equal(url.pathname, '/admin/pages/EditForm/field/Slides/existing-search');
        return { ok: true, json: async () => [] };
    });
});

test('preserves application subdirectory installations', async () => {
    const baseURI = document.baseURI;
    document.baseURI = 'https://cms.example/project/';
    try {
        await fetchOptions('admin/pages/EditForm/field/Slides/existing-search', 'sl', undefined, async url => {
            assert.equal(url.pathname, '/project/admin/pages/EditForm/field/Slides/existing-search');
            return { ok: true, json: async () => [] };
        });
    } finally {
        document.baseURI = baseURI;
    }
});

test('encodes the query, retains same-origin credentials and returns text labels', async () => {
    const records = [{ id: 12, label: '<b>Title & name</b>' }];
    const result = await fetchOptions('/search', 'a & b', undefined, async (url, options) => {
        assert.equal(url.searchParams.get('gridfield_relationsearch'), 'a & b');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers.Accept, 'application/json');
        assert.equal(options.headers['X-Requested-With'], 'XMLHttpRequest');
        return { ok: true, json: async () => records };
    });
    assert.deepEqual(result, records);
});

test('fails explicitly on HTTP errors and malformed search responses', async () => {
    await assert.rejects(fetchOptions('/search', '', undefined, async () => ({ ok: false, status: 403 })), /403/);
    for (const data of [{}, [{ id: 0, label: 'Invalid' }], [{ id: 1, label: {} }]]) {
        await assert.rejects(fetchOptions('/search', '', undefined, async () => ({
            ok: true, json: async () => data,
        })), /Invalid relation search response/);
    }
});

test('forwards abort signals so obsolete searches can be cancelled', async () => {
    const controller = new AbortController();
    controller.abort();
    await assert.rejects(fetchOptions('/search', '', controller.signal, async (_, options) => {
        assert.equal(options.signal, controller.signal);
        options.signal.throwIfAborted();
    }), { name: 'AbortError' });
});
