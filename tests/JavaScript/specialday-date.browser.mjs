// Isolated real Joomla calendar/validator and JEM fields; no installed-site or DB changes.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const joomla = process.env.JEM_TEST_JOOMLA_ROOT;
if (!joomla) throw new Error('Set JEM_TEST_JOOMLA_ROOT to a read-only Joomla 5/6 tree.');
const assets = new Map();
for (const [url, file] of [
    ['/core.js', join(joomla, 'media/system/js/core.js')],
    ['/validate.js', join(joomla, 'media/system/js/fields/validate.js')],
    ['/date-helper.js', join(joomla, 'media/system/js/fields/calendar-locales/date/gregorian/date-helper.js')],
    ['/calendar.js', join(joomla, 'media/system/js/fields/calendar.js')],
    ['/date-validation.js', join(root, 'media/js/date-validation.js')],
]) assets.set(url, await readFile(file));
const submissions = [];
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (request.method === 'POST' && url.pathname === '/submit') {
            let body = '';
            for await (const chunk of request) body += chunk;
            submissions.push(Object.fromEntries(new URLSearchParams(body)));
            response.setHeader('Content-Type', 'text/html');
            response.end('<p id="submitted">Submitted</p>');
        } else if (assets.has(url.pathname)) {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(assets.get(url.pathname));
        } else if (url.pathname === '/') {
            const html = execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
                join(root, 'tests/JavaScript/fixtures/specialday-date.php'),
                url.searchParams.get('side') || 'admin', url.searchParams.get('value') || '',
            ], { encoding: 'utf8', windowsHide: true });
            response.setHeader('Content-Type', 'text/html');
            response.end(html);
        } else {
            response.writeHead(204); response.end();
        }
    } catch (error) {
        response.writeHead(500); response.end(String(error));
    }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const profile = await mkdtemp(join(tmpdir(), 'jem-specialday-date-'));
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
    const navigate = async (side, value = '') => {
        await evaluate('window.previousFixtureDocument = true');
        await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port
            + '/?' + new URLSearchParams({ side, value }) }, sessionId);
        await waitFor('!window.previousFixtureDocument && document.readyState === "complete"'
            + ' && !!document.querySelector(".field-calendar")._joomlaCalendar'
            + ' && !document.querySelector("[data-jem-invalid-date]")');
    };
    const type = async (name, value) => {
        await evaluate('(() => { const field = document.getElementById(' + JSON.stringify(name) + ');'
            + 'field.focus(); field.value = ' + JSON.stringify(value)
            + '; field.dispatchEvent(new Event("input", {bubbles: true})); field.blur(); })()');
    };
    let rejected = 0;
    for (const side of ['admin', 'site']) {
        await navigate(side);
        for (const name of ['start_date', 'end_date']) {
            for (const invalid of ['2027-02-30', '2027-02-29', '2027-04-31', '2027-13-10', 'nonsense']) {
                await type('start_date', '2028-02-29');
                await type('end_date', '2028-02-29');
                await type(name, invalid);
                await evaluate('document.getElementById("save").click()');
                assert.equal(submissions.length, side === 'admin' ? 0 : 1);
                assert.equal(await evaluate('document.getElementById(' + JSON.stringify(name) + ').value'), invalid);
                assert.equal(await evaluate('document.getElementById(' + JSON.stringify(name)
                    + ').getAttribute("aria-invalid")'), 'true');
                rejected++;
            }
        }
        await type('start_date', '2028-02-29');
        await type('end_date', '2028-02-29');
        await evaluate('document.getElementById("save").click()');
        await waitFor('!!document.getElementById("submitted")');
        assert.equal(submissions.at(-1).start_date, '2028-02-29');
        assert.equal(submissions.at(-1).end_date, '2028-02-29');
        for (const value of ['2027-02-30', '"><script>alert(1)</script>']) {
            await navigate(side, value);
            assert.equal(await evaluate('document.getElementById("start_date").value'), value);
            assert.equal(await evaluate('document.getElementById("end_date").value'), value);
            await evaluate('document.getElementById("save").click()');
            assert.equal(submissions.length, side === 'admin' ? 1 : 2);
        }
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ forms: 2, rejectedSubmissions: rejected,
        validLeapDatePosts: submissions.length, rejectedTextRetained: true,
        escapedRetryText: true, javascriptErrors: errors.length }));
} finally {
    socket?.close();
    browser.kill();
    await browserClosed;
    server.closeAllConnections(); server.close();
    // Only the exact, newly created browser profile is removed.
    if (dirname(profile) === resolve(tmpdir()) && profile.startsWith(join(resolve(tmpdir()), 'jem-specialday-date-'))) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
}
