import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../public/themes/admin/js/talk.js', import.meta.url), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));

test('Talk sync patches the inbox without reload, pauses when hidden and reconnects', async () => {
    let revision = 'one';
    let offline = false;
    let hidden = false;
    let requests = 0;
    let patches = 0;
    let nextTimer;
    let visibilityChanged;
    const status = { dataset: {}, textContent: '' };
    const element = () => ({ replaceWith: () => { patches++; }, querySelector: () => null });
    const shell = { querySelector: (selector) => ['main', '.talk-home-context'].includes(selector) ? element() : null };
    const page = {
        dataset: { talkView: 'inbox' },
        querySelector: (selector) => {
            if (selector === '[data-talk-sync-status]') return status;
            if (selector === '.talk-inbox-list-body') return element();
            if (selector === '.talk-home-shell') return shell;
            return null;
        },
        querySelectorAll: () => [],
    };
    const nextPage = {
        querySelector: (selector) => {
            if (selector === '.talk-inbox-list-body') return element();
            if (selector === '.talk-home-shell') return shell;
            return null;
        },
    };
    const document = {
        activeElement: null,
        querySelector: (selector) => selector === '.talk-page' ? page : null,
        get hidden() { return hidden; },
        addEventListener: (name, callback) => { if (name === 'visibilitychange') visibilityChanged = callback; },
    };
    const location = { href: 'http://localhost/talk/view/inbox', reload: () => assert.fail('full reload') };
    const window = {
        setTimeout: (callback) => { nextTimer = callback; return 1; },
        clearTimeout: () => { nextTimer = undefined; },
        setInterval: () => assert.fail('overlapping interval'),
    };
    const fetch = async (url) => {
        requests++;
        if (offline) throw new Error('network offline');
        return url === '/talk/sync'
            ? { ok: true, json: async () => ({ revision, notifications: 0 }) }
            : { ok: true, text: async () => '<section class="talk-page"></section>' };
    };
    const DOMParser = class { parseFromString() { return { querySelector: () => nextPage }; } };
    vm.runInNewContext(script, { document, location, window, fetch, DOMParser });
    await flush();
    assert.equal(patches, 3);
    assert.equal(status.dataset.state, 'online');
    assert.equal(requests, 2);

    nextTimer();
    await flush();
    assert.equal(requests, 3, 'unchanged revision does not refetch the workspace');
    revision = 'two';
    nextTimer();
    await flush();
    assert.equal(patches, 6);
    assert.equal(requests, 5);

    hidden = true;
    visibilityChanged();
    nextTimer();
    await flush();
    assert.equal(requests, 5, 'hidden tab does not poll');
    hidden = false;
    offline = true;
    visibilityChanged();
    nextTimer();
    await flush();
    assert.equal(status.dataset.state, 'offline');
    offline = false;
    nextTimer();
    await flush();
    assert.equal(status.dataset.state, 'online');
});
