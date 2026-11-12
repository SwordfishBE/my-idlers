const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM, VirtualConsole } = require('jsdom');

test('delete confirmation and table search work with the production bundle', async () => {
    const errors = [];
    const console = new VirtualConsole();
    console.on('jsdomError', error => errors.push(error.message));
    const dom = new JSDOM(`<!doctype html><div id="app">
        <button id="42" data-title="Server One" v-on:click="confirmDeleteModal">Delete</button>
        <span id="name" v-text="modal_hostname"></span><span id="untrusted">{{ 7 * 7 }}</span><form id="form" v-bind:action="delete_form_action"></form>
        <div id="confirmDeleteModal" class="d-none"></div></div>
        <table id="table"><thead><tr><th>Name</th></tr></thead>
        <tbody><tr><td>Beta</td></tr><tr><td>Alpha</td></tr></tbody></table>`, {
        runScripts: 'dangerously', url: 'https://example.test', virtualConsole: console, pretendToBeVisual: true,
    });
    const { window } = dom;
    try {
        window.eval(fs.readFileSync('public/js/app.js', 'utf8'));
        const component = fs.readFileSync('resources/views/components/modal-delete-script.blade.php', 'utf8')
            .replace(/<\/?script>/g, '').replace('{{$uri}}', 'servers');
        window.eval(component);
        await new Promise(resolve => window.addEventListener('load', resolve, { once: true }));
        window.document.querySelector('#app button').click();
        await window.Vue.nextTick();
        assert.equal(window.document.querySelector('#name').textContent, 'Server One');
        assert.equal(window.document.querySelector('#form').getAttribute('action'), '/servers/42');
        assert.equal(window.document.querySelector('#untrusted').textContent, '{{ 7 * 7 }}');
        const malicious = '<img src=x onerror=alert(1)>{{ 7 * 7 }}';
        window.document.querySelector('#app button').dataset.title = malicious;
        window.document.querySelector('#app button').click();
        await window.Vue.nextTick();
        assert.equal(window.document.querySelector('#name').textContent, malicious);
        assert.equal(window.document.querySelector('#name img'), null);
        const table = new window.DataTable('#table', { pageLength: 1 });
        assert.equal(table.rows().count(), 2);
        table.search('Alpha').draw();
        assert.equal(window.document.querySelector('#table tbody').textContent, 'Alpha');
        assert.deepEqual(errors, []);
    } finally { window.close(); }
});

test('server comparison updates its link when a selection changes', async () => {
    const errors = [];
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', error => errors.push(error.message));
    const dom = new JSDOM(`<!doctype html><div id="app">
        <select id="first" @change="changeServer1($event)"><option value="a">A</option><option value="c">C</option></select>
        <select @change="changeServer2($event)"><option value="b">B</option></select>
        <a id="compare" v-bind:href="full_url">Compare</a></div>`, {
        runScripts: 'dangerously', url: 'https://example.test', virtualConsole, pretendToBeVisual: true,
    });
    const { window } = dom;
    try {
        window.eval(fs.readFileSync('public/js/app.js', 'utf8'));
        const view = fs.readFileSync('resources/views/servers/choose-compare.blade.php', 'utf8');
        const script = view.match(/<script type="application\/javascript">([\s\S]*?)<\/script>/)[1]
            .replace("{{ url('servers-compare') }}", 'https://example.test/servers-compare')
            .replace(/{{ route\('servers\.compare',[\s\S]*?\) }}/, 'https://example.test/servers-compare/a/b')
            .replace("{{ $all_servers[0]->id ?? '' }}", 'a')
            .replace("{{ $all_servers[1]->id ?? '' }}", 'b');
        window.eval(script);
        await new Promise(resolve => window.addEventListener('load', resolve, { once: true }));
        const select = window.document.querySelector('#first');
        select.value = 'c'; select.dispatchEvent(new window.Event('change'));
        await window.Vue.nextTick();
        assert.equal(window.document.querySelector('#compare').getAttribute('href'), 'https://example.test/servers-compare/c/b');
        assert.deepEqual(errors, []);
    } finally { window.close(); }
});

test('delete confirmation renders untrusted names as text and cancel never submits', async () => {
    const errors = [];
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', error => errors.push(error.message));
    const modal = fs.readFileSync('resources/views/components/delete-confirm-modal.blade.php', 'utf8')
        .replace('@csrf', '').replace("@method('DELETE')", '');
    const dom = new JSDOM(`<!doctype html><div id="app"><button id="42" data-title="Example" @click="confirmDeleteModal">Delete</button>${modal}</div>`, {
        runScripts: 'dangerously', url: 'https://example.test', virtualConsole, pretendToBeVisual: true,
    });
    const { window } = dom;
    try {
        window.eval(fs.readFileSync('public/js/app.js', 'utf8'));
        const script = fs.readFileSync('resources/views/components/modal-delete-script.blade.php', 'utf8')
            .replace(/<\/?script>/g, '').replace('{{$uri}}', 'servers');
        window.eval(script);
        await new Promise(resolve => window.addEventListener('load', resolve, { once: true }));
        const trigger = window.document.querySelector('#app > button');
        const dangerous = '<img src=x onerror=alert(1)> {{ 7 * 7 }}';
        trigger.dataset.title = dangerous; trigger.click(); await window.Vue.nextTick();
        const title = window.document.querySelector('#modal-title');
        assert.equal(title.textContent, dangerous);
        assert.equal(title.querySelector('img'), null);
        let submitted = false;
        window.document.querySelector('#confirmDeleteModal form').addEventListener('submit', e => { submitted = true; e.preventDefault(); });
        window.document.querySelector('#confirmDeleteModal button[title="cancel"]').click();
        await window.Vue.nextTick();
        assert.equal(submitted, false);
        assert.equal(window.document.querySelector('#confirmDeleteModal'), null);
        assert.deepEqual(errors, []);
    } finally { window.close(); }
});
