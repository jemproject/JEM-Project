// Native Atum CSS + exact production field CSS; synthetic DOM, no Joomla or mailer actions.
// Usage: JEM_TEST_JOOMLA_ROOT=/path/to/joomla node tests/JavaScript/mailer-matrix-theme.browser.mjs
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { readFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
assert.ok(process.env.JEM_TEST_JOOMLA_ROOT, 'Set JEM_TEST_JOOMLA_ROOT (libraries only; site not modified)');
const atum = await readFile(join(process.env.JEM_TEST_JOOMLA_ROOT, 'media/templates/administrator/atum/css/template.min.css'), 'utf8');
const source = await readFile(join(root, 'plugins/plg_jem_mailer/fields/mailerprofiles.php'), 'utf8');
const css = source.match(/return '<style>([\s\S]+?)<\/style>/)?.[1];
assert.ok(css, 'Actual field stylesheet must be available');
const profile = await mkdtemp(join(tmpdir(), 'jem-mailer-theme-'));
const browser = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--disable-background-networking', '--disable-extensions',
    '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank',
], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
const closed = new Promise(done => { browser.once('exit', done); browser.once('error', done); });
let socket;
try {
    const endpoint = await new Promise((done, fail) => {
        const timer = setTimeout(() => fail(new Error('Browser startup timeout')), 15000);
        browser.stderr.on('data', chunk => {
            const match = chunk.toString().match(/DevTools listening on (ws:\/\/\S+)/);
            if (match) { clearTimeout(timer); done(match[1]); }
        });
        browser.once('error', error => { clearTimeout(timer); fail(error); });
    });
    socket = new WebSocket(endpoint);
    await new Promise((done, fail) => { socket.onopen = done; socket.onerror = fail; });
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = event => {
        const data = JSON.parse(event.data), call = pending.get(data.id);
        if (call) {
            pending.delete(data.id); clearTimeout(call.timer);
            data.error ? call.fail(data.error) : call.done(data.result);
        }
    };
    const send = (method, params = {}, sessionId) => new Promise((done, fail) => {
        const id = ++sequence;
        const timer = setTimeout(() => fail(new Error('CDP timeout: ' + method)), 15000);
        pending.set(id, { done, fail, timer });
        socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true }, sessionId);
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    /** Relative luminance of an opaque RGB color, used for measured header contrast. */
    const luminance = rgb => rgb.map(c => c / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4)
        .reduce((sum, c, i) => sum + c * [.2126, .7152, .0722][i], 0);
    /** Compose alpha text over the opaque header surface before comparing luminance. */
    const contrast = (foreground, background) => {
        const fg = foreground.match(/[\d.]+/g).map(Number), bg = background.match(/[\d.]+/g).map(Number);
        const alpha = fg[3] ?? 1;
        const a = luminance(bg.slice(0, 3).map((c, i) => fg[i] * alpha + c * (1 - alpha)));
        const b = luminance(bg.slice(0, 3));
        return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
    };
    let scenarios = 0, assertions = 0;
    const results = [];
    for (const width of [1280, 640]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
        const html = '<!doctype html><html data-bs-theme="light" data-color-scheme="light"><head><meta charset="utf-8"><style id="atum">' + atum + '</style><style id="jem">' + css + '</style><style id="override"></style></head><body><table class="table control"><thead><tr><th>Unrelated heading</th></tr></thead></table><div class="jem-mailer-matrix"><fieldset class="jem-mailer-section"><legend>Registration</legend><div class="table-responsive"><table class="table table-striped table-sm jem-mailer-matrix-table"><thead><tr><th>Action</th><th>Participant</th><th>Administrator</th></tr></thead><tbody><tr><th scope="row">Registration</th><td><input name="recipient" type="checkbox" checked></td><td><input name="admin" type="checkbox"></td></tr></tbody></table></div></fieldset></div><span id="palette" style="color:var(--body-color,#17324f);background:var(--tertiary-bg,#e7edf5)"></span></body></html>';
        await evaluate('document.open();document.write(' + JSON.stringify(html) + ');document.close()');
        for (const mode of ['light', 'dark', 'dark-background', 'custom', 'light-again', 'fallback']) {
            const dark = mode.startsWith('dark');
            const override = mode === 'custom'
                ? ':root {--body-color:#fff4d4;--tertiary-bg:#193520}'
                : mode === 'dark-background'
                    ? '[data-bs-theme="dark"] .jem-mailer-matrix-table thead th {background:#1f2937}' : '';
            await evaluate(`(() => {
                document.documentElement.dataset.bsTheme = ${JSON.stringify(dark ? 'dark' : 'light')};
                document.documentElement.dataset.colorScheme = ${JSON.stringify(dark ? 'dark' : 'light')};
                document.querySelector('#override').textContent = ${JSON.stringify(override)};
                document.querySelector('#atum').sheet.disabled = ${mode === 'fallback'};
            })()`);
            const result = await evaluate(`(() => {
                const palette = getComputedStyle(document.querySelector('#palette'));
                const control = document.querySelector('.control th');
                const snapshot = element => {const s=getComputedStyle(element);return {color:s.color,background:s.backgroundColor};};
                const before = snapshot(control);
                document.querySelector('#jem').sheet.disabled = true;
                const withoutJem = snapshot(control);
                document.querySelector('#jem').sheet.disabled = false;
                return {headers:[...document.querySelectorAll('.jem-mailer-matrix-table thead th')].map(snapshot),
                    expected:{color:palette.color,background:palette.backgroundColor}, before, withoutJem,
                    inputs:[...document.querySelectorAll('input')].map(input=>[input.name,input.checked])};
            })()`);
            assert.equal(result.headers.length, 3); assertions++;
            for (const header of result.headers) {
                assert.equal(header.color, result.expected.color, mode + ': header must use configured text color'); assertions++;
                assert.equal(header.background, mode === 'dark-background' ? 'rgb(31, 41, 55)' : result.expected.background, mode); assertions++;
                assert.ok(contrast(header.color, header.background) >= 4.5, mode + ': insufficient contrast'); assertions++;
            }
            assert.deepEqual(result.inputs, [['recipient', true], ['admin', false]]); assertions++;
            assert.deepEqual(result.before, result.withoutJem, 'Unrelated table must not be restyled'); assertions++;
            results.push({ width, mode, contrast: contrast(result.headers[0].color, result.headers[0].background) });
            scenarios++;
        }
    }
    console.log(JSON.stringify({ scenarios, assertions, results }));
    await send('Browser.close');
} finally {
    socket?.close(); browser.kill(); await closed;
    // Delete only this exact mkdtemp profile after validating its parent and generated prefix.
    assert.equal(dirname(resolve(profile)), resolve(tmpdir()));
    assert.ok(basename(profile).startsWith('jem-mailer-theme-'));
    await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
}
