// Read-only acceptance checks for the reported jl602 venue and installed map filters.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdtemp, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {pathToFileURL} from 'node:url';

const base = process.env.JEM_TEST_BASE_URL || 'http://localhost/jl602/';
assert.ok(['localhost', '127.0.0.1'].includes(new URL(base).hostname));
const profile = await mkdtemp(join(tmpdir(), 'jem-venue-map-'));
const browser = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--disable-background-networking', '--no-first-run',
    '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank',
], {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
let socket;
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
try {
    const endpoint = await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(Error('Chrome startup timeout')), 15000);
        browser.stderr.on('data', chunk => {
            const match = chunk.toString().match(/DevTools listening on (ws:\/\/\S+)/);
            if (match) { clearTimeout(timer); resolve(match[1]); }
        });
        browser.once('error', reject);
    });
    socket = new WebSocket(endpoint);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = event => {
        const data = JSON.parse(event.data), call = pending.get(data.id);
        if (call) {
            clearTimeout(call.timer); pending.delete(data.id);
            data.error ? call.reject(data.error) : call.resolve(data.result);
        }
    };
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => reject(Error('CDP timeout: ' + method)), 15000);
        pending.set(id, {resolve, reject, timer});
        socket.send(JSON.stringify({id, method, params, sessionId}));
    });
    const {targetId} = await send('Target.createTarget', {url: 'about:blank'});
    const {sessionId} = await send('Target.attachToTarget', {targetId, flatten: true});
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', {expression, returnByValue: true}, sessionId);
        if (result.exceptionDetails) throw Error(result.exceptionDetails.text);
        return result.result.value;
    };
    const wait = async expression => {
        for (let i = 0; i < 150; i++) {
            if (await evaluate(expression)) return;
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        throw Error('Page condition timed out');
    };
    const navigate = async url => {
        await evaluate('window.previousDocument = true');
        await send('Page.navigate', {url}, sessionId);
        await wait('!window.previousDocument && document.readyState === "complete"');
    };
    await send('Page.enable', {}, sessionId);
    await send('Network.enable', {}, sessionId);
    await send('Network.setCacheDisabled', {cacheDisabled: true}, sessionId);
    const venueUrl = new URL('index.php?option=com_jem&view=venue&id=864&layout=default', base).href;
    for (const width of [1440, 1100, 960, 900, 390]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: false}, sessionId);
        await navigate(venueUrl);
        await wait('!!document.querySelector(".jem-venue-overview-media img")');
        const sizes = await evaluate(`Object.fromEntries(['panel','details','media'].map(name=>{
            const e=document.querySelector('.jem-venue-overview-'+name);
            return [name,{width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height}];
        }).concat([['value', {width:document.querySelector('.jem-venue-overview-details dd').getBoundingClientRect().width}]]))`);
        check(sizes.value.width > 140, 'Readable venue values at ' + width);
        check(sizes.media.width <= sizes.panel.width, 'Image fits at ' + width);
        await evaluate('document.querySelector(".jem-venue-overview-media").remove()');
        check(await evaluate(`document.querySelector('.jem-venue-overview-details').getBoundingClientRect().width
            > document.querySelector('.jem-venue-overview-panel').getBoundingClientRect().width * .85`),
        'No-image details use full width at ' + width);
    }
    await navigate(venueUrl);
    if (process.env.JEM_VENUE_SCREENSHOT) {
        await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false}, sessionId);
        const shot = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: true}, sessionId);
        await writeFile(process.env.JEM_VENUE_SCREENSHOT, Buffer.from(shot.data, 'base64'));
    }
    await navigate(new URL('index.php?option=com_jem&view=venuesmap&Itemid=3528&jem_map_filter_country=ES&jem_map_filter_city=Mairena+del+Aljarafe', base).href);
    check(await evaluate('document.querySelector("[name=jem_map_filter_city]").value === "Mairena del Aljarafe"'), 'Reported city remains selected');
    check(await evaluate(`!performance.getEntriesByType('resource').some(r=>/geodata|country_catalog|map-geography|venue-geography/.test(r.name))`),
        'Map filters make no geographical catalogue requests');
    check(await evaluate('(()=>{const e=document.querySelector("[name=jem_map_filter_city]");return !Array.from(e.list?.options||e.options).some(o=>o.value === "Berlin")})()'), 'Frontend cities narrowed by country');
    await evaluate(`window.previousDocument=true; const country=document.querySelector('[name=jem_map_filter_country]');
        country.value='DE'; country.dispatchEvent(new Event('change',{bubbles:true}));`);
    await wait('!window.previousDocument && document.readyState === "complete"');
    check(await evaluate('document.querySelector("[name=jem_map_filter_city]").value === ""'), 'Changing country clears city');
    check(await evaluate('(()=>{const e=document.querySelector("[name=jem_map_filter_city]");return !Array.from(e.list?.options||e.options).some(o=>o.value === "Madrid")})()'), 'German city choices exclude Madrid');

    if (process.env.JEM_MAP_OPTIONS_FIXTURE) {
        await navigate(pathToFileURL(process.env.JEM_MAP_OPTIONS_FIXTURE).href);
        const values = name => evaluate(`(()=>{const e=document.querySelector('[name="jform[params][default_${name}]"]');return Array.from(e.list?.options||e.options).map(o=>o.value)})()`);
        check(await evaluate(`document.querySelector('[name="jform[params][default_county]"]').tagName === 'SELECT'`),
            'Menu location filter remains a native selector');
        check(!(await values('county')).includes('Baden-Württemberg'), 'Initial menu county choices exclude Germany');
        await evaluate(`const country=document.querySelector('[name="jform[params][default_country]"]');
            country.value='DE';country.dispatchEvent(new Event('change',{bubbles:true}));`);
        check(await evaluate(`(()=>{
            const expected=[...new Set(Joomla.getOptions('jem.map.locations').filter(r=>r.country==='DE').map(r=>r.county).filter(Boolean))].sort();
            const e=document.querySelector('[name="jform[params][default_county]"]');
            const actual=Array.from(e.list?.options||e.options).map(o=>o.value).filter(Boolean).sort();
            return expected.length>0 && expected.every(value=>actual.includes(value));
        })()`), 'Menu choices use German venue counties');
        check(!(await values('county')).includes('Community of Madrid'), 'Menu country change removes Spanish counties');
        check(!(await values('city')).includes('Madrid'), 'Menu country change removes Spanish cities');
        await evaluate(`const county=document.querySelector('[name="jform[params][default_county]"]');
            county.value=Array.from(county.options).find(option=>option.value).value;county.dispatchEvent(new Event('change',{bubbles:true}));`);
        check(await evaluate(`(()=>{
            const county=document.querySelector('[name="jform[params][default_county]"]').value;
            const expected=[...new Set(Joomla.getOptions('jem.map.locations').filter(r=>r.country==='DE' && r.county===county).map(r=>r.city).filter(Boolean))].sort();
            const e=document.querySelector('[name="jform[params][default_city]"]');
            const actual=Array.from(e.list?.options||e.options).map(o=>o.value).filter(Boolean).sort();
            return expected.length>0 && JSON.stringify(expected)===JSON.stringify(actual);
        })()`), 'County selection contains exactly its existing venue cities');
    }
    if (process.env.JEM_MAP_LANGUAGE_CHECKS === '1') {
        for (const [language, label] of [['es', 'España'], ['en', 'Spain'], ['de', 'Spanien']]) {
            await navigate(new URL('index.php?option=com_jem&view=venuesmap&Itemid=3528&lang=' + language, base).href);
            const state = await evaluate(`({language:document.documentElement.lang,
                country:Array.from(document.querySelector('[name=jem_map_filter_country]').options)
                    .find(o=>o.value==='ES')?.textContent.trim()})`);
            // Check JEM's label, not the template's HTML language metadata (the
            // local German page currently emits "ge" instead of "de").
            check(state.country === label,
                'Country label follows requested language ' + language + '; value stays ES: ' + JSON.stringify(state));
        }
    }
    console.log(JSON.stringify({result: 'PASS', checks, profile}));
} finally {
    socket?.close();
    browser.kill();
}
