// Production markup, JS and CSS in headless Chrome; fixtures contain no personal data.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const joomla = process.env.JEM_TEST_JOOMLA_ROOT || 'C:/xampp/htdocs/jl602';
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        const assets = {
            '/bootstrap.css': join(joomla, 'media/vendor/bootstrap/css/bootstrap.min.css'),
            '/registration-status.css': join(root, 'media/css/registration-status.css'),
            '/registration-status.js': join(root, 'media/js/registration-status.js'),
        };
        if (assets[url.pathname]) {
            response.setHeader('Content-Type', url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript');
            response.end(await readFile(assets[url.pathname]));
        } else {
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.end(execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
                join(root, 'tests/Support/registration_status_fixture.php'), url.searchParams.get('client') || 'site', url.searchParams.get('hidden') || '',
            ]));
        }
    } catch (error) { response.writeHead(500); response.end(String(error)); }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const profile = await mkdtemp(join(tmpdir(), 'jem-registration-status-'));
const browser = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--disable-background-networking', '--disable-extensions', '--no-first-run',
    '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank',
], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
const closed = new Promise(done => { browser.once('exit', done); browser.once('error', done); });
let socket;
let scenarios = 0;
try {
    const endpoint = await new Promise((done, fail) => {
        const timer = setTimeout(() => fail(new Error('Browser startup timeout')), 15000);
        browser.stderr.on('data', chunk => {
            const match = chunk.toString().match(/DevTools listening on (ws:\/\/\S+)/);
            if (match) { clearTimeout(timer); done(match[1]); }
        });
        browser.once('error', fail);
    });
    socket = new WebSocket(endpoint);
    await new Promise(done => socket.addEventListener('open', done, { once: true }));
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = event => {
        const message = JSON.parse(event.data);
        const request = pending.get(message.id);
        if (request) { clearTimeout(request.timer); pending.delete(message.id); message.error ? request.fail(message.error) : request.done(message.result); }
    };
    const send = (method, params = {}, sessionId) => new Promise((done, fail) => {
        const id = ++sequence;
        const timer = setTimeout(() => fail(new Error(method + ' timeout')), 15000);
        pending.set(id, { done, fail, timer });
        socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, sessionId);
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    for (const client of ['site', 'admin']) {
        for (const width of [375, 768, 1280]) {
            for (const hidden of ['', 'hidden']) {
                await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false }, sessionId);
                await send('Page.navigate', { url: `http://127.0.0.1:${server.address().port}/?client=${client}&hidden=${hidden}` }, sessionId);
                for (let n = 0; n < 100; n++) {
                    if (await evaluate('document.readyState === "complete" && !!document.querySelector(".jem-registration-status")')) break;
                    await new Promise(done => setTimeout(done, 30));
                }
                assert.equal(await evaluate('document.querySelectorAll("tbody script").length'), 0);
                assert.equal(await evaluate('new URL(location.href).searchParams.get("rs_date")'), '2026-11-09');
                assert.equal(await evaluate('new URL(location.href).searchParams.has("rs_step")'), false);
                assert.equal(await evaluate('document.querySelector("#event-2 .rs-entry").textContent.includes("<script>alert(1)</script>")'), true);
                const state = await evaluate(`(() => {
                    const form = document.querySelector('.rs-filters');
                    form.requestSubmit = () => { window.sent = Object.fromEntries(new FormData(form)); };
                    const clear = [...form.querySelectorAll('a')][0];
                    return {width: document.documentElement.scrollWidth, viewport: innerWidth,
                        clear: clear.href, country: !!form.querySelector('[name="rs_country"]'),
                        selected: form.querySelectorAll('[name="rs_publication"] option[selected]').length,
                        pagination: [...document.querySelectorAll('.rs-pagination a')].map(a => a.href)};
                })()`);
                assert.ok(state.width <= state.viewport + 1, `${client}/${width}: page overflows`);
                assert.equal(state.country, !hidden);
                assert.equal(state.selected, 1);
                assert.ok(!state.clear.includes('rs_country'));
                for (const link of state.pagination) {
                    const query = new URL(link).searchParams;
                    assert.equal(query.get('rs_country'), 'ES');
                    assert.equal(query.get('rs_mode'), 'week');
                    assert.equal(query.get('rs_date'), '2026-11-09');
                }
                assert.deepEqual(await evaluate(`(() => {
                    document.querySelector('[data-rs-expand="0"]').click();
                    const collapsed = [...document.querySelectorAll('[data-rs-row]')].filter(r => !r.hidden).length;
                    document.querySelector('[data-rs-expand="1"]').click();
                    return [collapsed, [...document.querySelectorAll('[data-rs-row]')].filter(r => !r.hidden).length];
                })()`), [1, 3]);
                if (!hidden) {
                    const sent = await evaluate(`(() => {
                        const country = document.querySelector('[name="rs_country"]'); country.value = 'DE'; country.dispatchEvent(new Event('change'));
                        return window.sent;
                    })()`);
                    assert.equal(sent.rs_region, ''); assert.equal(sent.rs_county, ''); assert.equal(sent.rs_city, '');
                }
                const sent = await evaluate(`(() => {
                    document.querySelector('[name="rs_mode"][value="today"]').checked = true;
                    const date = document.querySelector('[name="rs_date"]'); date.value = '2026-11-17'; date.dispatchEvent(new Event('change'));
                    return window.sent;
                })()`);
                assert.equal(sent.rs_mode, 'date'); assert.equal(sent.rs_date, '2026-11-17');
                ++scenarios;
            }
        }
    }
    console.log(JSON.stringify({ scenarios, clients: ['FE', 'BE'], widths: [375,768,1280], result: 'PASS' }));
    await send('Browser.close');
} finally {
    socket?.close(); browser.kill(); await closed;
    await new Promise(done => setTimeout(done, 300));
    const expected = resolve(tmpdir()) + (process.platform === 'win32' ? '\\' : '/') + 'jem-registration-status-';
    if (!resolve(profile).startsWith(expected)) throw new Error('Unexpected browser profile path');
    await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    server.close();
}
