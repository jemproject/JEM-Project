// Render the three production Search filter templates with PHP 8.4 and test their real JS/CSS in Chrome.
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
        const referer = new URL(request.headers.referer || 'http://localhost');
        const assets = {
            '/bootstrap.css': join(joomla, 'media/vendor/bootstrap/css/bootstrap.min.css'),
            '/jquery.js': join(joomla, 'media/vendor/jquery/js/jquery.min.js'),
            '/search.js': join(root, 'media/js/search.js'),
            '/search.css': join(root, 'media/css/jem' + (referer.searchParams.get('layout') === '0' ? '' : '-responsive') + '.css'),
        };
        if (assets[url.pathname]) {
            response.setHeader('Content-Type', url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript');
            response.end(await readFile(assets[url.pathname]));
        } else {
            response.setHeader('Content-Type', 'text/html');
            response.end(execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
                join(root, 'tests/Support/search_filter_fixture.php'),
                url.searchParams.get('layout') || '0', url.searchParams.get('hidden') || '',
            ]));
        }
    } catch (error) {
        response.writeHead(500); response.end(String(error));
    }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const profile = await mkdtemp(join(tmpdir(), 'jem-search-filter-'));
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
    for (const layout of [0, 1, 2]) {
        for (const width of [375, 768, 1024, 1440]) {
            for (const hidden of ['', 'date_from', 'date_to', 'date_from,date_to', 'continent,state', 'eventtype,category,venue,continent,country,state,city,date_from,date_to,limit']) {
                await send('Emulation.setDeviceMetricsOverride',
                    { width, height: 1000, deviceScaleFactor: 1, mobile: false }, sessionId);
                await evaluate('window.oldFixture = true');
                await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port
                    + '/?' + new URLSearchParams({ layout, hidden }) }, sessionId);
                let ready = false;
                for (let attempt = 0; attempt < 100; attempt++) {
                    ready = await evaluate('!window.oldFixture && document.readyState === "complete" && !!document.querySelector("#jem_filter")');
                    if (ready) break;
                    await new Promise(done => setTimeout(done, 50));
                }
                assert.ok(ready, 'Fixture loaded');
                const metrics = await evaluate(`(() => {
                    const panel = document.querySelector('#jem_filter');
                    const controls = [...panel.querySelectorAll('input, select, button')];
                    const box = panel.getBoundingClientRect();
                    return {
                        footer: (() => {
                            const limit = panel.querySelector('select[name="limit"]');
                            const label = panel.querySelector('.jem-search-filter-limit .jem-filter-label');
                            const actions = panel.querySelector('.jem-search-filter-actions').getBoundingClientRect();
                            const locations = [...panel.querySelectorAll('.jem-search-bottom select')];
                            const perRow = {};
                            locations.forEach(el => {
                                const top = Math.round(el.getBoundingClientRect().top);
                                perRow[top] = (perRow[top] || 0) + 1;
                            });
                            return {
                                labelAbove: !limit || label.getBoundingClientRect().bottom <= limit.getBoundingClientRect().top,
                                actionsLast: locations.every(el => el.getBoundingClientRect().bottom <= actions.bottom + 1),
                                maxFields: Math.max(0, ...Object.values(perRow))
                            };
                        })(),
                        limitOverlap: (() => {
                            const limit = panel.querySelector('select[name="limit"]');
                            const clear = panel.querySelector('button[type="button"]');
                            if (!limit) return false;
                            const a=limit.getBoundingClientRect(), b=clear.getBoundingClientRect();
                            return a.right > b.left - 4 && Math.abs(a.top-b.top) < 2;
                        })(),
                        overflow: document.documentElement.scrollWidth > innerWidth + 1,
                        clipped: controls.some(el => {
                            const r = el.getBoundingClientRect();
                            return r.width < 25 || r.left < box.left - 1 || r.right > box.right + 1;
                        }),
                        rows: [...panel.querySelectorAll('select, input[type=date]')].map(el => ({
                            name: el.name, top: el.getBoundingClientRect().top, width: el.getBoundingClientRect().width
                        }))
                    };
                })()`);
                assert.equal(metrics.overflow, false, JSON.stringify({ layout, width, hidden, metrics }));
                assert.equal(metrics.limitOverlap, false, JSON.stringify({ layout, width, hidden, metrics }));
                assert.equal(metrics.clipped, false, JSON.stringify({ layout, width, hidden, metrics }));
                assert.equal(metrics.footer.labelAbove, true, JSON.stringify(metrics.footer));
                assert.equal(metrics.footer.actionsLast, true, JSON.stringify(metrics.footer));
                assert.ok(metrics.footer.maxFields <= 4, JSON.stringify(metrics.footer));
                if (!hidden && width >= 768) {
                    const aligned = await evaluate(`['filter_search','filter_date_from','filter_date_to']
                        .map(name=>{const el=document.getElementById(name),b=el.getBoundingClientRect(),p=el.parentElement.parentElement,s=getComputedStyle(p);return {top:b.top,height:b.height,parentHeight:p.getBoundingClientRect().height,parentBorder:s.border,parentPadding:s.padding,parentMargin:s.margin,parentBottom:p.getBoundingClientRect().bottom,box:s.boxSizing};})`);
                    assert.ok(Math.max(...aligned.map(b=>b.top))-Math.min(...aligned.map(b=>b.top))<1, JSON.stringify(aligned));
                    assert.ok(Math.max(...aligned.map(b=>b.height))-Math.min(...aligned.map(b=>b.height))<1, JSON.stringify(aligned));
                }
                if (!hidden && width >= 1024) {
                    const fit = await evaluate(`(() => {
                        ['filter_continent','filter_country','filter_state','filter_city'].forEach((name,i)=>{
                            const el=document.getElementById(name);
                            el.options[el.selectedIndex].text=['Europe','Spain','Madrid region','Madrid'][i];
                        });
                        jem_search_fit_location(document.getElementById('jem_filter'));
                        return ['filter_continent','filter_country','filter_state','filter_city'].map(name=>{
                            const el=document.getElementById(name);return {top:el.getBoundingClientRect().top,width:el.getBoundingClientRect().width};
                        });
                    })()`);
                    assert.ok(Math.max(...fit.map(b=>b.top))-Math.min(...fit.map(b=>b.top))<2,
                        'Four short selections fit the same row: '+JSON.stringify(fit));
                    const reserved = await evaluate(`(() => {
                        const panel = document.getElementById('jem_filter');
                        const before = panel.getBoundingClientRect().height;
                        const country = document.getElementById('filter_country');
                        const width = country.getBoundingClientRect().width;
                        const style = getComputedStyle(country);
                        const context = document.createElement('canvas').getContext('2d');
                        context.font = style.fontWeight + ' ' + style.fontSize + ' ' + style.fontFamily;
                        let text = 'Country';
                        while (context.measureText(text).width + parseFloat(style.paddingLeft)
                            + parseFloat(style.paddingRight) + 4 <= width) text += 'W';
                        country.options[country.selectedIndex].text = text;
                        jem_search_fit_location(panel);
                        const city = document.getElementById('filter_city').getBoundingClientRect();
                        const limit = panel.querySelector('select[name="limit"]').getBoundingClientRect();
                        const actions = panel.querySelector('.jem-search-filter-actions').getBoundingClientRect();
                        const grid = panel.querySelector('.jem-search-bottom').getBoundingClientRect();
                        return {before, after: panel.getBoundingClientRect().height,
                            cityTop: city.top, countryTop: country.getBoundingClientRect().top,
                            limitLeft: limit.left, actionsRight: actions.right, gridLeft:grid.left,gridRight:grid.right};
                    })()`);
                    assert.ok(reserved.cityTop > reserved.countryTop, JSON.stringify(reserved));
                    assert.ok(Math.abs(reserved.after-reserved.before) < 1, 'Reserved row keeps panel height: '+JSON.stringify(reserved));
                    assert.ok(Math.abs(reserved.limitLeft-reserved.gridLeft) < 1, JSON.stringify(reserved));
                    assert.ok(Math.abs(reserved.actionsRight-reserved.gridRight) < 1, JSON.stringify(reserved));
                    const wrap = await evaluate(`(() => {
                        const el=document.getElementById('filter_country');
                        el.options[el.selectedIndex].text='A very long country name which needs several grid quarters';
                        jem_search_fit_location(document.getElementById('jem_filter'));
                        return {country:el.getBoundingClientRect().width,continent:document.getElementById('filter_continent').getBoundingClientRect().width};
                    })()`);
                    assert.ok(wrap.country > wrap.continent, JSON.stringify(wrap));
                }
                if (hidden === 'continent,state' && width >= 1024) {
                    const compact = await evaluate(`(() => {
                        const panel = document.getElementById('jem_filter');
                        document.getElementById('filter_country').selectedOptions[0].text = 'Spain';
                        document.getElementById('filter_city').selectedOptions[0].text = 'Madrid';
                        jem_search_fit_location(panel);
                        return {country:document.getElementById('filter_country').getBoundingClientRect().top,
                            limit:panel.querySelector('select[name="limit"]').getBoundingClientRect().top,
                            buttons:panel.querySelector('.jem-search-filter-actions').getBoundingClientRect().top};
                    })()`);
                    assert.ok(Math.abs(compact.limit-compact.country)<2, JSON.stringify(compact));
                    assert.ok(compact.buttons>compact.limit, JSON.stringify(compact));
                }
                const result = await evaluate(`(() => {
                    const form = document.querySelector('#adminForm');
                    form.submit = function () { window.sent = Object.fromEntries(new FormData(this)); };
                    document.querySelector('[onclick="jem_search_clear();"]').click();
                    return { sent: window.sent, action: form.action };
                })()`);
                for (const key of ['filter_search', 'filter_date_from', 'filter_date_to', 'filter_continent',
                    'filter_country', 'filter_state', 'filter_city']) assert.equal(result.sent[key], '', key);
                assert.equal(result.sent.limitstart, '0');
                assert.equal(result.sent.limit, '10');
                assert.equal(result.sent.search_reset, '1');
                assert.equal(result.sent.filter_order, 'a.title');
                assert.equal(new URL(result.action).searchParams.get('Itemid'), '123');
                assert.equal(new URL(result.action).searchParams.has('filter_city'), false);
                assert.equal(new URL(result.action).searchParams.has('limitstart'), false);
                const change = await evaluate(`(() => {
                    const form = document.querySelector('#adminForm');
                    ['filter_country','filter_state','filter_city'].forEach(name => jem_search_set_filter(form,name,'stale'));
                    if (document.querySelector('#filter_continent')) {
                        jQuery('#filter_continent').val('EU').trigger('change');
                    } else {
                        jem_search_submit(form);
                    }
                    return window.sent;
                })()`);
                if (!hidden.includes('continent')) {
                    assert.equal(change.filter_country, '');
                    assert.equal(change.filter_state, '');
                    assert.equal(change.filter_city, '');
                    assert.equal(change.filter_venue_id, '0');
                }
                scenarios++;
            }
        }
    }
    console.log(JSON.stringify({ scenarios, templates: 3, widths: [375,768,1024,1440],
        clear: true, hiddenControls: true, dependentResets: true, longLabels: true, responsiveActionRow: true }));
    await send('Browser.close');
} finally {
    socket?.close();
    browser.kill();
    await closed;
    await new Promise(done => setTimeout(done, 300));
    // Only this unique mkdtemp browser profile, never a site path.
    if (resolve(profile).startsWith(resolve(tmpdir()) + '\\jem-search-filter-')
        || resolve(profile).startsWith(resolve(tmpdir()) + '/jem-search-filter-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
    server.close();
}


