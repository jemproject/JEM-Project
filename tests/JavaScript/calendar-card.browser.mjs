// Actual production card assembly + tooltip + CSS, synthetic data, headless Chromium.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (url.pathname === '/calendar.css') {
            const referer = new URL(request.headers.referer);
            response.setHeader('Content-Type', 'text/css');
            response.end(await readFile(join(root, 'media/css/calendar'
                + (referer.searchParams.get('layout') === 'responsive' ? '-responsive' : '') + '.css')));
        } else {
            response.setHeader('Content-Type', 'text/html');
            response.end(execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
                join(root, 'tests/Support/calendar_card_fixture.php'),
                url.searchParams.get('view') || 'calendar', url.searchParams.get('layout') || 'standard',
            ]));
        }
    } catch (error) {
        response.writeHead(500); response.end(String(error));
    }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const profile = await mkdtemp(join(tmpdir(), 'jem-calendar-card-'));
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
    let scenarios = 0, cards = 0;
    for (const view of ['calendar', 'weekcal']) {
        for (const layout of ['standard', 'responsive']) {
            for (const width of [375, 1280]) {
                await send('Emulation.setDeviceMetricsOverride',
                    { width, height: 1000, deviceScaleFactor: 1, mobile: false }, sessionId);
                await evaluate('window.oldFixture = true');
                await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port
                    + '/?' + new URLSearchParams({ view, layout }) }, sessionId);
                for (let attempt = 0; attempt < 100; ++attempt) {
                    if (await evaluate('!window.oldFixture && document.readyState === "complete" && !!document.querySelector(".fixture-case")')) break;
                    await new Promise(done => setTimeout(done, 50));
                }
                for (const tall of [false, true]) {
                    await evaluate('document.body.classList.toggle("tall", ' + tall + ')');
                    const metrics = await evaluate('Array.from(document.querySelectorAll(".fixture-case")).map(item => {'
                        + 'const card=item.querySelector(".eventcontentinner"),body=item.querySelector(".eventcontenttextbody"),link=item.querySelector(".editlinktip > a");'
                        + 'if(!body||!link)return {missingBody:true};'
                        + 'const c=card.getBoundingClientRect(),b=body.getBoundingClientRect(),l=link.getBoundingClientRect();'
                        + 'const top=l.top-b.top,bottom=b.bottom-l.bottom;'
                        + 'return {top,bottom,delta:Math.abs(top-bottom),href:link.getAttribute("href"),'
                        + 'clipped:l.top<c.top||l.bottom>c.bottom||l.right>c.right+1,'
                        + 'center:Math.abs((l.top+l.bottom)/2-(c.top+c.bottom)/2),'
                        + 'bar:item.dataset.bar==="1",visible:c.width>0&&c.height>0,innerOverflow:card.scrollWidth>card.clientWidth+1};})');
                    assert.equal(metrics.length, 16);
                    for (const metric of metrics) {
                        assert.equal(metric.missingBody, undefined, 'Common text container: ' + view + '/' + layout);
                        assert.ok(metric.top >= 4.9 && metric.bottom >= 4.9 && metric.delta <= .2, JSON.stringify(metric));
                        assert.equal(metric.clipped, false, JSON.stringify(metric));
                        assert.equal(metric.innerOverflow, false, JSON.stringify(metric));
                        assert.equal(metric.visible, true);
                        assert.match(metric.href, /^#event-\d+$/);
                        if (!metric.bar) assert.ok(metric.center <= .6, 'Vertical center: ' + JSON.stringify(metric));
                        cards++;
                    }
                    scenarios++;
                }
            }
        }
    }
    console.log(JSON.stringify({ scenarios, cards, balancedPadding: true, multiline: true, minimumHeight: true }));
    await send('Browser.close');
} finally {
    socket?.close();
    browser.kill();
    await closed;
    await new Promise(done => setTimeout(done, 300));
    // Only this unique mkdtemp browser profile, never a site path.
    if (resolve(profile).startsWith(resolve(tmpdir()) + '\\jem-calendar-card-')
        || resolve(profile).startsWith(resolve(tmpdir()) + '/jem-calendar-card-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
    server.close();
}

