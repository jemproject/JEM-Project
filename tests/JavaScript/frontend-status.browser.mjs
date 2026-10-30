// Uses real PHP layouts, Joomla core.js and Chromium. The POST receiver is synthetic.
// JEM_TEST_JOOMLA_ROOT=<read-only Joomla tree> PHP_BINARY=<php> CHROME_BINARY=<chromium> node this-file.mjs
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const joomla = process.env.JEM_TEST_JOOMLA_ROOT || 'C:/xampp/htdocs/jl602';
const core = await readFile(join(joomla, 'media/system/js/core.js'));
const script = await readFile(join(root, 'media/js/frontend-list-actions.js'));
const submissions = [];
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (request.method === 'POST' && url.pathname === '/submit') {
            let body = '';
            for await (const chunk of request) body += chunk;
            const values = new URLSearchParams(body);
            submissions.push({ ids: values.getAll('cid[]'), task: values.get('task'),
                tokenPresent: values.get('fixture_token') === '1', boxchecked: values.get('boxchecked') });
            response.writeHead(200, { 'Content-Type': 'text/html' });
            response.end('<p id="submitted">Submitted</p>');
        } else if (url.pathname === '/core.js' || url.pathname === '/actions.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(url.pathname === '/core.js' ? core : script);
        } else if (url.pathname === '/') {
            const html = execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
                join(root, 'tests/JavaScript/fixtures/frontend-status.php'),
                url.searchParams.get('view'), url.searchParams.get('layout'),
                url.searchParams.get('state'), url.searchParams.has('print') ? 'print' : '',
            ], { encoding: 'utf8' });
            response.setHeader('Content-Type', 'text/html');
            response.end('<!doctype html><html><head><script defer src="/core.js"></script>'
                + '<script defer src="/actions.js"></script></head><body><div id="jem">' + html + '</div></body></html>');
        } else {
            response.writeHead(204); response.end();
        }
    } catch (error) {
        response.writeHead(500); response.end(String(error));
    }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const profile = await mkdtemp(join(tmpdir(), 'jem-status-test-'));
const browser = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--disable-background-networking', '--disable-extensions', '--no-first-run',
    '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank',
], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
const browserClosed = new Promise(done => {
    browser.once('exit', done);
    browser.once('error', done);
});
let socket;
try {
    const endpoint = await new Promise((done, fail) => {
        const timeout = setTimeout(() => fail(new Error('Chrome startup timeout')), 15000);
        browser.stderr.on('data', chunk => {
            const match = chunk.toString().match(/DevTools listening on (ws:\/\/\S+)/);
            if (match) { clearTimeout(timeout); done(match[1]); }
        });
        browser.on('error', error => { clearTimeout(timeout); fail(error); });
    });
    socket = new WebSocket(endpoint);
    await new Promise((done, fail) => { socket.onopen = done; socket.onerror = fail; });
    const pending = new Map(), errors = [];
    let sequence = 0;
    socket.onmessage = event => {
        const data = JSON.parse(event.data);
        if (data.method === 'Runtime.exceptionThrown') {
            errors.push(data.params.exceptionDetails.exception?.description || data.params.exceptionDetails.text);
        }
        const call = pending.get(data.id);
        if (call) {
            pending.delete(data.id); clearTimeout(call.timeout);
            data.error ? call.fail(data.error) : call.done(data.result);
        }
    };
    const send = (method, params = {}, sessionId) => new Promise((done, fail) => {
        const id = ++sequence;
        const timeout = setTimeout(() => { pending.delete(id); fail(new Error('CDP timeout: ' + method)); }, 10000);
        pending.set(id, { done, fail, timeout });
        socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    await send('Runtime.enable', {}, sessionId);
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, sessionId);
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
        return result.result.value;
    };
    const waitFor = async expression => {
        for (let i = 0; i < 60; i++) {
            if (await evaluate(expression)) return;
            await new Promise(done => setTimeout(done, 50));
        }
        throw new Error('Browser condition not reached: ' + expression + '; JS errors: ' + errors.join(', '));
    };
    const navigate = async (view, layout, state, print = false) => {
        await evaluate('window.previousFixtureDocument = true');
        await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port
            + '/?' + new URLSearchParams({ view, layout, state }) + (print ? '&print=1' : '') }, sessionId);
        await waitFor('!window.previousFixtureDocument && document.readyState === "complete" && !!document.getElementById("adminForm")');
    };
    let checked = 0;
    let bulk = 0;
    for (const view of ['myevents', 'myvenues']) {
        for (const layout of ['standard', 'responsive']) {
            for (const state of [1, 0, 2]) {
                await navigate(view, layout, state);
                assert.equal(await evaluate('typeof window.listItemTask'), 'undefined');
                assert.equal(await evaluate('typeof Joomla.submitform'), 'function');
                assert.equal(await evaluate('document.querySelectorAll(".jem-status-link").length'), 2);
                assert.equal(await evaluate('!!document.getElementById("cb0")'), false, 'No checkbox without permission');
                await evaluate(`document.body.insertAdjacentHTML('beforeend',
                    '<form id="unrelated"><input type="checkbox" name="cid[]" checked></form>');
                    document.adminForm.addEventListener('submit', () => {
                        if (!document.querySelector('#unrelated input').checked) throw new Error('Other form changed');
                    });`);
                await evaluate('document.getElementById("cb3").checked = true; document.getElementById("cb4").checked = true;');
                const before = submissions.length;
                // Click the nested image, not just its enclosing anchor.
                await evaluate('document.querySelector(".jem-status-link img").click()');
                await waitFor('!!document.getElementById("submitted")');
                assert.equal(submissions.length, before + 1);
                assert.deepEqual(submissions.at(-1), { ids: ['101'], task: view + (state === 1 ? '.unpublish' : '.publish'),
                    tokenPresent: true, boxchecked: '1' });
                checked++;
            }
            await navigate(view, layout, 1, true);
            assert.equal(await evaluate('document.querySelectorAll(".jem-status-link, input[name=" + JSON.stringify("cid[]") + "]").length'), 0);
            // Existing select-all and bulk submit must still send all selected rows.
            await navigate(view, layout, 1);
            await evaluate('document.querySelector("input[name=checkall-toggle]").click()');
            const beforeBulk = submissions.length;
            await evaluate('Joomla.submitform(' + JSON.stringify(view + '.unpublish') + ', document.adminForm)');
            await waitFor('!!document.getElementById("submitted")');
            assert.equal(submissions.length, beforeBulk + 1);
            assert.deepEqual(submissions.at(-1), { ids: ['101', '103', '104'], task: view + '.unpublish',
                tokenPresent: true, boxchecked: '3' });
            bulk++;
        }
    }
    await navigate('myevents', 'standard', 1);
    // A missing or disabled target must never submit another selected row.
    const beforeInvalid = submissions.length;
    await evaluate(`window.invalidSubmitCalls = 0;
        const originalSubmit = Joomla.submitform;
        Joomla.submitform = (...args) => { window.invalidSubmitCalls++; return originalSubmit(...args); };`);
    await evaluate('document.getElementById("cb1").disabled = true; document.querySelector(".jem-status-link").click()');
    await evaluate('document.getElementById("cb1").remove(); document.querySelector(".jem-status-link").click()');
    assert.equal(await evaluate('window.invalidSubmitCalls'), 0);
    assert.equal(submissions.length, beforeInvalid);
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ layouts: 4, successfulPosts: checked, singleRowWithCheckboxGaps: true,
        bulkPosts: bulk, csrfFieldPreserved: true, readOnlyPrint: true,
        missingOrDisabledTargetBlocked: true, javascriptErrors: 0 }));
} finally {
    socket?.close();
    browser.kill();
    await browserClosed;
    server.closeAllConnections(); server.close();
    // Only the exact, newly created browser profile is removed.
    if (dirname(profile) === resolve(tmpdir()) && profile.startsWith(join(resolve(tmpdir()), 'jem-status-test-'))) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
}
