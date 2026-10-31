import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

// Isolated about:blank DOM only. All submit events are cancelled before networking.
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const fixtures = JSON.parse(execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
    join(root, 'tests/Support/main_quick_action_fixture.php'),
], { encoding: 'utf8' }));
const profile = await mkdtemp(join(tmpdir(), 'jem-2364-browser-'));
const browser = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--disable-background-networking', '--disable-extensions',
    '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank',
], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
const closed = new Promise(done => { browser.once('exit', done); browser.once('error', done); });
let socket;
try {
    const endpoint = await new Promise((done, fail) => {
        const timeout = setTimeout(() => fail(new Error('Browser startup timeout')), 15000);
        browser.stderr.on('data', chunk => {
            const match = chunk.toString().match(/DevTools listening on (ws:\/\/\S+)/);
            if (match) { clearTimeout(timeout); done(match[1]); }
        });
        browser.once('error', error => { clearTimeout(timeout); fail(error); });
    });
    socket = new WebSocket(endpoint);
    await new Promise((done, fail) => { socket.onopen = done; socket.onerror = fail; });
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = event => {
        const data = JSON.parse(event.data);
        const call = pending.get(data.id);
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
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, sessionId);
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    await send('Page.enable', {}, sessionId);
    await send('Emulation.setFocusEmulationEnabled', { enabled: true }, sessionId);
    await send('Page.bringToFront', {}, sessionId);
    const results = [];
    let assertions = 0;
    for (const fixture of fixtures) {
        for (const task of ['sampledata.load', 'frontendmenu.create']) {
            for (const acceptance of [false, true]) {
                for (const interaction of ['click', 'Enter', 'Space']) {
                    await evaluate('document.open(); document.write(' + JSON.stringify(fixture.html) + '); document.close()');
                    const state = await evaluate(`(() => {
                        window.probe = { confirmations: 0, submits: 0, task: null, tokenPresent: false };
                        window.confirm = text => { window.probe.confirmations++; window.probe.message = text; return ${acceptance}; };
                        document.addEventListener('submit', event => {
                            event.preventDefault();
                            window.probe.submits++;
                            const data = new FormData(event.target, event.submitter);
                            window.probe.task = data.get('task');
                            window.probe.tokenPresent = data.has('fixture_token');
                        }, { capture: true });
                        window.targetButton = document.querySelector('button[value="${task}"]');
                        window.targetButton.focus();
                        return { forms: document.forms.length, owner: window.targetButton.form?.id ?? '',
                            outerTokenPresent: !!document.forms['application-form'].querySelector('input[name="fixture_token"]') };
                    })()`);
                    if (interaction === 'click') {
                        await evaluate('window.targetButton.click()');
                    } else {
                        const key = interaction === 'Space' ? ' ' : 'Enter';
                        const code = interaction === 'Space' ? 'Space' : 'Enter';
                        const nativeVirtualKeyCode = interaction === 'Space' ? 32 : 13;
                        await send('Input.dispatchKeyEvent', { type: 'keyDown', key, code, text: interaction === 'Space' ? ' ' : '\r', windowsVirtualKeyCode: nativeVirtualKeyCode, nativeVirtualKeyCode }, sessionId);
                        await send('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: nativeVirtualKeyCode, nativeVirtualKeyCode }, sessionId);
                    }
                    const observed = await evaluate('window.probe');
                    const expectsConfirmation = task === 'sampledata.load';
                    const context = JSON.stringify({ fixture: fixture.name, task, acceptance, interaction, state, observed });
                    assert.equal(observed.confirmations, expectsConfirmation ? 1 : 0, context); assertions++;
                    assert.equal(observed.submits, expectsConfirmation && !acceptance ? 0 : 1, context); assertions++;
                    if (observed.submits) {
                        assert.equal(observed.task, task); assertions++;
                        assert.equal(observed.tokenPresent, true); assertions++;
                    }
                    assert.equal(state.forms, 1); assertions++;
                    assert.equal(state.owner, 'application-form'); assertions++;
                    assert.equal(state.outerTokenPresent, true); assertions++;
                    if (expectsConfirmation) {
                        assert.equal(observed.message, fixture.confirmation); assertions++;
                    }
                    results.push({ fixture: fixture.name, task, acceptance, interaction, ...state, ...observed });
                }
            }
        }
    }
    console.log(JSON.stringify({ cases: results.length, assertions, status: 'passed', scope: 'actual PHP quick-action renderer; no network submission' }));
} finally {
    socket?.close();
    browser.kill();
    await closed;
    // Remove only this test's unique browser profile after validating the exact parent and name.
    if (resolve(dirname(profile)) === resolve(tmpdir()) && profile.startsWith(join(tmpdir(), 'jem-2364-browser-'))) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
}
