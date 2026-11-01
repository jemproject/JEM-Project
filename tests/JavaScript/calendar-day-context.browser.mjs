// Production calendar.js with real jQuery and headless Chromium; synthetic markup only.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { readFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (url.pathname === '/jquery.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(await readFile(join(process.env.JEM_TEST_JOOMLA_ROOT || 'C:/xampp/htdocs/jl602',
                'media/vendor/jquery/js/jquery.min.js')));
        } else if (url.pathname === '/calendar.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(await readFile(join(root, 'media/js/calendar.js')));
        } else {
            response.setHeader('Content-Type', 'text/html');
            const href = url.searchParams.get('sef') === '1'
                ? '/calendar/day/20270115?jemcal=1&task=archive&layout=default'
                : '/index.php?option=com_jem&view=day&id=20270115&Itemid=101&task=archive&jemcal=1';
            response.end('<!doctype html><html><head><script src="/jquery.js"></script></head><body>'
                + '<main id="jem"><div id="jlcalendarlegend"><button id="cat7" class="eventCat">Category</button>'
                + '<button id="venue9" class="eventVenues">Venue</button></div>'
                + '<button id="buttonshowall">Show all</button><button id="buttonhideall">Hide all</button>'
                + '<div class="event-filter cat7 venue9" id="event1">Event</div>'
                + '<a id="overflow" href="' + href + '">And more</a>'
                + '<div id="annual" data-day-link="' + href + '">15</div>'
                + '<a id="unrelated" href="/event/1">Event link</a></main><script src="/calendar.js"></script></body></html>');
        }
    } catch (error) {
        response.writeHead(500); response.end(String(error));
    }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const profile = await mkdtemp(join(tmpdir(), 'jem-calendar-context-'));
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
    const pending = new Map();
    let sequence = 0;
    socket.onmessage = event => {
        const data = JSON.parse(event.data);
        const call = pending.get(data.id);
        if (call) {
            pending.delete(data.id); clearTimeout(call.timeout);
            data.error ? call.fail(data.error) : call.done(data.result);
        }
    };
    const send = (method, params = {}, sessionId) => new Promise((done, fail) => {
        const id = ++sequence;
        const timeout = setTimeout(() => fail(new Error('CDP timeout: ' + method)), 10000);
        pending.set(id, { done, fail, timeout });
        socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, sessionId);
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
        return result.result.value;
    };
    let scenarios = 0;
    for (const sef of [0, 1]) {
        for (const inherited of [0, 1]) {
            await evaluate('window.oldFixture = true');
            await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port
                + '/?sef=' + sef + (inherited ? '&calcats=8' : '') }, sessionId);
            for (let attempt = 0; attempt < 100; ++attempt) {
                if (await evaluate('!window.oldFixture && document.readyState === "complete" && !!window.jQuery')) break;
                await new Promise(done => setTimeout(done, 50));
            }
            // Wait for jQuery's ready callbacks, then exercise the real delegated handlers.
            await evaluate('new Promise(resolve => jQuery(() => resolve(true)))');
            const click = selector => evaluate('document.querySelector(' + JSON.stringify(selector) + ').click()');
            const state = () => evaluate('["overflow","annual"].map(id => {const e=document.getElementById(id);'
                + 'return Object.fromEntries(new URL(e.getAttribute("data-day-link")||e.href,location.href).searchParams);})');
            await click('#cat7');
            for (const query of await state()) {
                assert.equal(query.calcats, inherited ? '8,7' : '7');
                assert.equal(query.task, 'archive');
                assert.equal(query.jemcal, '1');
            }
            assert.equal(await evaluate('document.getElementById("event1").style.display'), 'none');
            await click('#venue9');
            for (const query of await state()) assert.equal(query.calvenues, '9');
            await click('#cat7');
            for (const query of await state()) assert.equal(query.calcats, inherited ? '8' : undefined);
            await click('#buttonhideall');
            for (const query of await state()) assert.equal(query.calhide, '1');
            await click('#buttonshowall');
            for (const query of await state()) {
                assert.equal(query.calcats, undefined);
                assert.equal(query.calvenues, undefined);
                assert.equal(query.calhide, undefined);
                assert.equal(query.task, 'archive');
            }
            assert.equal(await evaluate('document.getElementById("unrelated").getAttribute("href")'), '/event/1');
            scenarios++;
        }
    }
    console.log(JSON.stringify({ scenarios, realProductionJavaScript: true, queryAndSefLinks: true,
        inheritedScope: true, categoryVenueAndGlobalToggles: true, unrelatedLinksUntouched: true }));
    await send('Browser.close');
} finally {
    socket?.close();
    browser.kill();
    await closed;
    await new Promise(done => setTimeout(done, 300));
    // Only this unique mkdtemp browser profile, never a site path.
    if (resolve(profile).startsWith(resolve(tmpdir()) + '\\jem-calendar-context-')
        || resolve(profile).startsWith(resolve(tmpdir()) + '/jem-calendar-context-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
    server.close();
}



