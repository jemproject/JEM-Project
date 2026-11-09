// Focused offline regression: node tests/JavaScript/map-location-controls.browser.mjs
// Requires PHP and Chromium (PHP_BINARY / CHROME_BINARY can override their paths).
// Real template PHP/JS, DOM and Leaflet; simulated geolocation, clock and Google Maps API.
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, rm, realpath } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname, isAbsolute, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(process.env.JEM_MAP_TEST_ROOT || join(dirname(fileURLToPath(import.meta.url)), '../..'));
const payload = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [
    join(root, 'tests/Support/map_location_controls.php'), root,
], { encoding: 'utf8', windowsHide: true }));
const leaflet = await readFile(join(root, 'media/js/leaflet.js'), 'utf8');
const tempRoot = await realpath(tmpdir());
const profile = await mkdtemp(join(tempRoot, 'jem-map-location-'));
const binary = process.env.CHROME_BINARY || (process.platform === 'win32'
    ? 'C:/Program Files/Google/Chrome/Application/chrome.exe' : 'chromium');
const browser = spawn(binary, ['--headless', '--disable-gpu', '--disable-background-networking',
    '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'],
{ windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
const closed = new Promise(done => { browser.once('exit', done); browser.once('error', done); });
let socket;
try {
    const endpoint = await new Promise((done, fail) => {
        const timer = setTimeout(() => fail(new Error('Browser startup timeout')), 12000);
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
            clearTimeout(call.timer); pending.delete(data.id);
            data.error ? call.fail(data.error) : call.done(data.result);
        }
    };
    const send = (method, params = {}, sessionId) => new Promise((done, fail) => {
        const id = ++sequence;
        const timer = setTimeout(() => fail(new Error(method + ' timeout')), 20000);
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
    await evaluate(leaflet);

    /**
     * Run production handlers against real browser controls and deterministic API responses.
     * @param {Array} sources Rendered template handlers and visible-control flags.
     * @param {string} provider Leaflet or a Google Maps boundary adapter.
     * @returns {{checks: number}} Number of behavioural checks completed in the browser.
     */
    function exercise(sources, provider) {
        window.testMaps?.forEach(map => { if (map.remove) map.remove(); });
        document.body.innerHTML = '';
        window.testMaps = [];
        const requests = [], timers = new Map(), alerts = [], controls = [];
        let timerSequence = 0, checks = 0;
        const check = (condition, message) => {
            checks++;
            if (!condition) throw new Error(message);
        };
        const originalTimeout = window.setTimeout, originalClear = window.clearTimeout;
        window.setTimeout = (fn, delay, ...args) => {
            if (delay !== 1000) return originalTimeout(fn, delay, ...args);
            const id = 100000 + (++timerSequence);
            timers.set(id, fn);
            return id;
        };
        window.clearTimeout = id => { if (!timers.delete(id)) originalClear(id); };
        const flushHelp = () => {
            for (const [id, fn] of [...timers]) { timers.delete(id); fn(); }
        };
        const geo = { getCurrentPosition: (success, error) => requests.push({ success, error }) };
        Object.defineProperty(navigator, 'geolocation', { configurable: true, value: geo });
        window.alert = message => alerts.push(message);
        class GoogleLayer {
            constructor(params) {
                this.map = params.map;
                this.position = params.position || params.center;
                this.map.layers.add(this);
            }
            setMap() { this.map.layers.delete(this); }
        }
        window.google = { maps: { Circle: class extends GoogleLayer {}, Marker: class extends GoogleLayer {} } };
        try {
            sources.forEach((source, index) => {
                const mapId = 'location-test-' + index;
                const replace = text => text.replaceAll('__MAP_ID__', mapId);
                const wrapper = document.createElement('section');
                wrapper.innerHTML = (source.visible
                    ? '<button id="' + replace(source.ids['locate-me-btn']) + '">Locate ' + index + '</button>'
                    + '<div id="' + replace(source.ids['location-help']) + '">Help</div>'
                    + '<div id="' + replace(source.ids['permission-instructions']) + '" style="display:none">Instructions</div>' : '')
                    + '<div id="' + mapId + '" style="width:300px;height:180px"></div>';
                document.body.appendChild(wrapper);
                controls.push({ button: wrapper.querySelector('button'), help: wrapper.querySelector('[id^=location-help]'),
                    instructions: wrapper.querySelector('[id^=permission-instructions]') });
            });
            sources.forEach((source, index) => {
                const mapId = 'location-test-' + index;
                const map = provider === 'osm'
                    ? L.map(mapId, { zoomAnimation: false, fadeAnimation: false }).setView([40, -3], 5)
                    : { layers: new Set(), setCenter() {}, setZoom() {}, getZoom() { return 5; } };
                testMaps.push(map);
                new Function('map', 'infoWindow', 'mylocIcon', source.code.replaceAll('__MAP_ID__', mapId))(
                    map, { setContent() {}, open() {} }, {});
            });
            const ids = [...document.querySelectorAll('[id]')].map(element => element.id);
            check(new Set(ids).size === ids.length, 'Duplicate control IDs');
            const markers = () => testMaps.map(map => provider === 'osm'
                ? Object.values(map._layers).filter(layer => layer instanceof L.Marker).map(layer => layer.getLatLng().lat)
                : [...map.layers].filter(layer => layer instanceof google.maps.Marker).map(layer => layer.position.lat));
            const instructions = () => controls.map(control => control.instructions?.style.display === 'block');
            const complete = (request, latitude) => request.success({ coords: { latitude, longitude: -4, accuracy: 20 } });
            const start = index => {
                const before = requests.length;
                controls[index].button.click();
                check(requests.length === before + 1, 'One clicked button must make one request');
                check(controls[index].button.disabled, 'Only the clicked button enters loading state');
                controls[index].button.dispatchEvent(new Event('click'));
                check(requests.length === before + 1, 'Repeated clicks while pending must not add requests');
                return requests[before];
            };
            const visible = controls.flatMap((control, index) => control.button ? [index] : []);
            check(requests.length === 0, 'No automatic location requests on load');
            for (const index of visible) {
                const before = markers();
                const request = start(index);
                controls.forEach((control, other) => {
                    if (other !== index && control.button) check(!control.button.disabled, 'Another map button was disabled');
                });
                complete(request, 41 + index);
                const after = markers();
                check(JSON.stringify(after[index]) === JSON.stringify([41 + index]), 'Location marker belongs to clicked map');
                after.forEach((locations, other) => {
                    if (other !== index) check(JSON.stringify(locations) === JSON.stringify(before[other]), 'Another map changed');
                });
                check(!controls[index].button.disabled, 'Button recovers after success');
                check(timers.size === 0, 'Success cancels pending help');
                flushHelp();
                check(instructions().every(value => !value), 'No stale permission instructions after success');

                const delayed = start(index);
                flushHelp();
                check(instructions().every((value, other) => value === (other === index)), 'Pending help belongs only to clicked map');
                complete(delayed, 42 + index);
                check(instructions().every(value => !value), 'Resolving a delayed request hides help');
                check(markers()[index].length === 1, 'A retry replaces, not duplicates, its marker');

                for (const code of [1, 2, 3]) {
                    const beforeError = JSON.stringify(markers()), alertCount = alerts.length;
                    const failed = start(index);
                    failed.error({ code, PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 });
                    check(alerts.length === alertCount + 1, 'Exactly one error is reported');
                    check(JSON.stringify(markers()) === beforeError, 'Failed location must not change markers');
                    check(!controls[index].button.disabled, 'Button recovers after error');
                    check(timers.size === 0, 'Error cancels pending help');
                    flushHelp();
                    check(instructions().every(value => !value), 'No stale instructions after error');
                }
                Object.defineProperty(navigator, 'geolocation', { configurable: true, value: undefined });
                const count = requests.length;
                controls[index].button.click();
                check(requests.length === count && !controls[index].button.disabled, 'Unsupported API does not leave a loading button');
                Object.defineProperty(navigator, 'geolocation', { configurable: true, value: geo });
            }
            if (visible.length >= 2) {
                const [a, b] = visible;
                const first = start(a), second = start(b);
                check(timers.size === 2, 'Concurrent requests need separate help timers');
                complete(first, 48);
                check(timers.size === 1, 'Finishing one request must not cancel another timer');
                flushHelp();
                check(instructions()[b] && !instructions()[a], 'Only the still-pending map shows help');
                complete(second, 49);
                check(instructions().every(value => !value), 'All completed requests hide instructions');
            }
            return { checks };
        } finally {
            window.setTimeout = originalTimeout;
            window.clearTimeout = originalClear;
        }
    }

    const scenarios = [
        ['module alone', [['module', true]]], ['events alone', [['events', true]]],
        ['responsive alone', [['responsive', true]]], ['venues alone', [['venues', true]]],
        ['two modules', [['module', true], ['module', true]]],
        ['module + events', [['module', true], ['events', true]]],
        ['module + responsive', [['module', true], ['responsive', true]]],
        ['venues + module', [['venues', true], ['module', true]]],
        ['module + hidden view control', [['module', true], ['events', false]]],
        ['hidden module control + venues', [['module', false], ['venues', true]]],
        ['two modules + view', [['module', true], ['module', true], ['events', true]]],
        ['no location controls', [['module', false], ['venues', false]]],
    ];
    let scenariosPassed = 0, checks = 0;
    for (const provider of ['osm', 'google']) {
        for (const [name, instances] of scenarios) {
            const sources = instances.map(([kind, visible]) => {
                const source = payload.find(item => item.kind === kind && item.provider === provider);
                assert.ok(source, 'Missing source for ' + kind + '/' + provider);
                return { ...source, visible };
            });
            try {
                const result = await evaluate('(' + exercise.toString() + ')(' + JSON.stringify(sources) + ',' + JSON.stringify(provider) + ')');
                checks += result.checks;
                scenariosPassed++;
            } catch (error) {
                throw new Error(provider + ' / ' + name + ': ' + error.message, { cause: error });
            }
        }
    }
    console.log(JSON.stringify({ passed: true, scenarios: scenariosPassed, checks, root }));
} finally {
    socket?.close();
    browser.kill();
    await closed;
    const actualProfile = await realpath(profile);
    const relativeProfile = relative(tempRoot, actualProfile);
    if (relativeProfile && !relativeProfile.startsWith('..') && !isAbsolute(relativeProfile)) {
        await rm(actualProfile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 });
    }
}
