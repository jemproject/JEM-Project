// Read-only local Events Map browser acceptance checks. No database writes.
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname, basename } from 'node:path';
const base = process.env.JEM_MAP_TEST_URL || 'http://localhost/jl602/index.php?option=com_jem&view=eventsmap&Itemid=3517';
assert.ok(['localhost','127.0.0.1'].includes(new URL(base).hostname), 'Only local test sites');
const profile = await mkdtemp(join(tmpdir(), 'jem-eventsmap-'));
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

    await send('Runtime.enable', {}, sessionId);
    await send('Page.enable', {}, sessionId);
    const wait = async condition => {
        for (let i = 0; i < 160; i++) {
            try { if (await evaluate(condition)) return; } catch {}
            await new Promise(done => setTimeout(done, 100));
        }
        throw new Error('Page did not reach expected state: ' + condition);
    };
    const ready = '!window.previousMapDocument && !!document.querySelector(".jem-map-location-filters") && document.readyState === "complete"';
    const navigate = async filters => {
        const url = new URL(base);
        for (const [key,value] of Object.entries(filters)) url.searchParams.set('jem_map_filter_' + key,value);
        await evaluate('window.previousMapDocument = true');
        await send('Page.navigate', {url: url.href}, sessionId);
        await wait(ready);
    };
    const action = async expression => {
        await evaluate('window.previousMapDocument = true; ' + expression);
        await wait(ready);
    };
    const snapshot = () => evaluate(`({
        date: document.querySelector('[name="jem_map_filter_date"]').value,
        mode: document.querySelector('[name="jem_map_filter_mode"]:checked').value,
        rows: [...document.querySelectorAll('.jem-eventsmap-table tbody tr')].filter(r=>!r.querySelector('[colspan]')).map(r=>r.innerText),
        markers: document.querySelectorAll('.jem-eventsmap-canvas .leaflet-marker-icon').length,
        country: document.querySelector('[name="jem_map_filter_country"]')?.value,
        county: document.querySelector('[name="jem_map_filter_county"]')?.value,
        city: document.querySelector('[name="jem_map_filter_city"]')?.value
    })`);
    await send('Emulation.setDeviceMetricsOverride', {width:1280,height:1000,deviceScaleFactor:1,mobile:false}, sessionId);
    await navigate({mode:'month',date:'2026-11-25'});
    let current = await snapshot();
    assert.equal(current.date,'2026-11-01');
    const november = current.rows;
    await action('document.querySelector(\'[name="jem_map_filter_step"][value="1"]\').click()');
    current = await snapshot();
    assert.equal(current.date,'2026-12-01');
    assert.equal(current.mode,'month');
    assert.notDeepEqual(current.rows,november,'Table must update for next month');
    assert.ok(current.markers>0,'Map must render filtered markers');
    await action('document.querySelector(\'[name="jem_map_filter_step"][value="-1"]\').click()');
    assert.deepEqual((await snapshot()).rows,november);
    await navigate({mode:'date',date:'2028-02-28'});
    await action('document.querySelector(\'[name="jem_map_filter_step"][value="1"]\').click()');
    assert.equal((await snapshot()).date,'2028-02-29');
    await navigate({mode:'week',date:'2026-11-13'});
    const weekStart=(await snapshot()).date;
    await action('document.querySelector(\'[name="jem_map_filter_step"][value="1"]\').click()');
    const nextWeek=(await snapshot()).date;
    assert.equal((Date.parse(nextWeek)-Date.parse(weekStart))/86400000,7);
    await action('document.querySelector(\'[name="jem_map_filter_mode"][value="today"]\').click()');
    const today=await snapshot();
    assert.equal(today.mode,'today');
    await action('document.querySelector(\'[name="jem_map_filter_mode"][value="tomorrow"]\').click()');
    const tomorrow=await snapshot();
    assert.equal((Date.parse(tomorrow.date)-Date.parse(today.date))/86400000,1);
    await navigate({mode:'all',country:'ES',county:'Community of Madrid',city:'Madrid'});
    current=await snapshot();
    assert.equal(current.city,'Madrid');
    assert.ok(current.rows.length>0);
    assert.ok(current.rows.every(row=>row.includes('Madrid')));
    assert.equal(await evaluate('document.querySelector(\'[name="jem_map_filter_step"]\').disabled'),true);
    await action('const country=document.querySelector(\'[name="jem_map_filter_country"]\');country.value="DE";country.dispatchEvent(new Event("change",{bubbles:true}))');
    current=await snapshot();
    assert.equal(current.country,'DE'); assert.equal(current.county,''); assert.equal(current.city,'');
    assert.ok(current.rows.every(row=>row.includes('DE')));
    assert.equal(await evaluate('document.querySelector(".jem-map-location-filters").getBoundingClientRect().top > document.querySelector(".jem-map-period-filters").getBoundingClientRect().top'),true);
    const shot=await send('Page.captureScreenshot',{format:'png'},sessionId);
    if(process.env.JEM_MAP_SCREENSHOT) await writeFile(process.env.JEM_MAP_SCREENSHOT,Buffer.from(shot.data,'base64'));
    console.log(JSON.stringify({result:'PASS',checks:20,novemberEvents:november.length,germanyEvents:current.rows.length,weekStart,nextWeek}));
    await send('Browser.close');
} finally {
    socket?.close(); browser.kill(); await closed;
    assert.equal(dirname(resolve(profile)),resolve(tmpdir()));
    assert.ok(basename(profile).startsWith('jem-eventsmap-'));
    await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:200});
}
