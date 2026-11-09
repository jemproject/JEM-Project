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
    ['/recurrence-change.js', join(root, 'media/js/recurrence-change.js')],
]) assets.set(url, await readFile(file));
const submissions = [], previews = [];
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (request.method === 'POST' && url.pathname === '/submit') {
            const chunks = [];
            for await (const chunk of request) chunks.push(chunk);
            const raw = Buffer.concat(chunks);
            const data = request.headers['content-type'].startsWith('multipart/form-data')
                ? Object.fromEntries(await new Response(raw, {headers: {'Content-Type': request.headers['content-type']}}).formData())
                : Object.fromEntries(new URLSearchParams(raw.toString()));
            if (data.task === 'event.recurrencePreview') {
                previews.push(data);
                response.setHeader('Content-Type', 'application/json');
                response.end(JSON.stringify({success: true, data: {
                    required: url.searchParams.get('decision') === '1', count: 1,
                    fingerprint: 'fixture-fingerprint', keepLabel: 'Keep independently',
                    message: 'Recurrence changed', blocked: 'Active bookings', keepAllowed: true, deleteAllowed: false
                }}));
                return;
            }
            submissions.push(data);
            response.setHeader('Content-Type', 'text/html');
            response.end('<p id="submitted">Submitted</p>');
        } else if (assets.has(url.pathname)) {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(assets.get(url.pathname));
        } else if (url.pathname === '/') {
            const html = execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
                join(root, 'tests/JavaScript/fixtures/recurrence-preview.php'),
                url.searchParams.get('side') || 'admin', url.searchParams.get('locale') || 'de-DE',
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
const profile = await mkdtemp(join(tmpdir(), 'jem-recurrence-preview-'));
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
    const pending = new Map(), errors = [], alerts = [];
    let sequence = 0;
    socket.onmessage = event => {
        const data = JSON.parse(event.data);
        if (data.method === 'Page.javascriptDialogOpening') {
            alerts.push(data.params.message);
            send('Page.handleJavaScriptDialog', {accept: true}, data.sessionId);
        }
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
    await send('Page.enable', {}, sessionId);
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
    const localDate = (iso, locale) => {
        const [year, month, day] = iso.split('-');
        return locale === 'de-DE' ? `${day}.${month}.${year}`
            : locale === 'es-ES' ? `${day}-${month}-${year}`
            : locale === 'en-US' ? `${month}/${day}/${year}` : `${day}/${month}/${year}`;
    };
    const navigate = async (side, locale = 'de-DE', decision = false) => {
        await evaluate('window.previousFixtureDocument = true');
        await send('Page.navigate', {url: 'http://127.0.0.1:' + server.address().port
            + '/?' + new URLSearchParams({side, locale})}, sessionId);
        await waitFor('!window.previousFixtureDocument && document.readyState === "complete"'
            + ' && !!document.querySelector(".field-calendar")._joomlaCalendar');
        await evaluate('document.querySelector("form").action = "/submit?decision=' + (decision ? '1' : '0') + '"');
    };
    const type = async value => {
        await evaluate('document.getElementById("jform_dates").focus(); document.getElementById("jform_dates").select()');
        await send('Input.insertText', {text: value}, sessionId);
        await evaluate('document.getElementById("jform_dates").blur()');
    };
    let unchanged = 0, changed = 0, invalid = 0, decisions = 0;
    for (const side of ['admin', 'site']) {
        for (const locale of ['de-DE', 'es-ES', 'fr-FR', 'en-US']) {
            await send('Emulation.setTimezoneOverride', {
                timezoneId: side === 'admin' ? 'Pacific/Auckland' : 'America/Los_Angeles'
            }, sessionId);
            await navigate(side, locale);
            assert.equal(await evaluate('document.getElementById("jform_dates").value'), localDate('2031-11-08', locale));
            await evaluate('document.getElementById("save").click()');
            await waitFor('!!document.getElementById("submitted")');
            const preview = previews.at(-1), saved = submissions.at(-1);
            assert.equal(preview.jem_recurrence_date_format, 'iso');
            assert.equal(preview['jform[dates]'], '2031-11-08');
            assert.equal(preview['jform[enddates]'], '2031-11-09');
            assert.equal(preview['jform[recurrence_limit_date]'], '2031-12-31');
            assert.equal(preview['jform[title]'], 'Existing event');
            assert.equal(saved['jform[dates]'], localDate('2031-11-08', locale));
            assert.equal(saved['jform[enddates]'], localDate('2031-11-09', locale));
            unchanged++;
        }

        // Invalid displayed dates never reach the preview; retry is not left pending.
        await navigate(side);
        const before = previews.length, alertCount = alerts.length;
        await type('31.11.2031');
        await evaluate('Joomla.submitform("event.save", document.querySelector("form"))');
        assert.equal(previews.length, before);
        assert.equal(alerts.length, alertCount + 1);
        assert.equal(await evaluate('document.getElementById("jform_dates").value'), '31.11.2031');
        invalid++;
        await type('09.12.2031');
        await evaluate('document.getElementById("save").click()');
        await waitFor('!!document.getElementById("submitted")');
        assert.equal(previews.at(-1)['jform[dates]'], '2031-12-09');
        assert.equal(submissions.at(-1)['jform[dates]'], '09.12.2031');
        changed++;

        // Retain the confirmation and disabled delete choice with active bookings.
        await navigate(side, 'de-DE', true);
        const savedBefore = submissions.length;
        await evaluate('document.getElementById("save").click()');
        await waitFor('document.querySelector("dialog").open');
        assert.equal(submissions.length, savedBefore);
        assert.equal(await evaluate('document.getElementById("jform_dates").value'), '08.11.2031');
        assert.equal(await evaluate('document.querySelector("input[value=delete]").disabled'), true);
        await evaluate('(() => { const keep = document.querySelector("input[value=keep]");'
            + 'keep.checked = true; keep.dispatchEvent(new Event("change", {bubbles:true}));'
            + 'document.querySelector("[data-recurrence-continue]").click(); })()');
        await waitFor('!!document.getElementById("submitted")');
        assert.equal(submissions.at(-1).jem_recurrence_choice, 'keep');
        assert.equal(submissions.at(-1).jem_recurrence_fingerprint, 'fixture-fingerprint');
        assert.equal(submissions.at(-1)['jform[dates]'], '08.11.2031');
        decisions++;

        // Native submit/Enter still runs preview once before the normal submission.
        await navigate(side);
        const nativeBefore = previews.length;
        await evaluate('document.getElementById("native-save").click()');
        await waitFor('!!document.getElementById("submitted")');
        assert.equal(previews.length, nativeBefore + 1);
        assert.equal(previews.at(-1)['jform[dates]'], '2031-11-08');
        assert.equal(submissions.at(-1)['jform[dates]'], '08.11.2031');

        // New records do not enter the recurrence preview.
        await navigate(side);
        const newBefore = previews.length;
        await evaluate('document.querySelector("[name=jem_recurrence_event_id]").value="0";'
            + 'document.getElementById("save").click()');
        await waitFor('!!document.getElementById("submitted")');
        assert.equal(previews.length, newBefore);
        assert.equal(submissions.at(-1)['jform[dates]'], '08.11.2031');

        // Missing native picker (template override) retains the legacy payload.
        await navigate(side);
        await evaluate('document.querySelector(".field-calendar")._joomlaCalendar = null;'
            + 'Joomla.submitform("event.save", document.querySelector("form"))');
        await waitFor('!!document.getElementById("submitted")');
        assert.equal(Object.hasOwn(previews.at(-1), 'jem_recurrence_date_format'), false);
        assert.equal(previews.at(-1)['jform[dates]'], '08.11.2031');
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({forms: 2, locales: 4, timezones: 2, unchanged, changed,
        invalid, decisions, previews: previews.length, normalPosts: submissions.length,
        javascriptErrors: errors.length}));
} finally {
    socket?.close();
    browser.kill();
    await browserClosed;
    server.closeAllConnections(); server.close();
    // Only the exact, newly created browser profile is removed.
    if (dirname(profile) === resolve(tmpdir()) && profile.startsWith(join(resolve(tmpdir()), 'jem-recurrence-preview-'))) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
}
