// Optional real-browser regression: PHP=<php binary> EDGE=<Edge/Chromium binary> node this-file.mjs
import { spawn, execFileSync } from 'node:child_process';
import { mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { resolve, join } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const root = fileURLToPath(new URL('../../', import.meta.url));
const temp = await mkdtemp(join(tmpdir(), 'jem-day-layout-'));
const edge = process.env.EDGE ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const php = process.env.PHP ?? 'C:/xampp/php/php.exe';
const browser = spawn(edge, ['--headless=new', '--disable-gpu', '--no-first-run', '--disable-background-networking',
    '--disable-component-update', '--no-default-browser-check', '--remote-debugging-port=0',
    `--user-data-dir=${temp}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let socket;
let sequence = 0;
const pending = new Map();
const delay = ms => new Promise(done => setTimeout(done, ms));
const command = (method, params = {}) => new Promise((done, fail) => {
    const id = ++sequence;
    const timeout = setTimeout(() => { pending.delete(id); fail(new Error(`Timeout: ${method}`)); }, 15000);
    pending.set(id, result => { clearTimeout(timeout); result.error ? fail(new Error(JSON.stringify(result.error))) : done(result.result); });
    socket.send(JSON.stringify({ id, method, params }));
});

try {
    let port;
    for (let attempt = 0; attempt < 100; attempt++) {
        try { port = (await readFile(join(temp, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
        catch { await delay(100); }
    }
    assert.ok(port, 'Browser debugging endpoint started');
    const targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
    socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((done, fail) => { socket.onopen = done; socket.onerror = fail; });
    socket.onmessage = event => {
        const response = JSON.parse(event.data);
        if (pending.has(response.id)) { pending.get(response.id)(response); pending.delete(response.id); }
    };
    await command('Page.enable');
    let checked = 0;
    for (const layout of ['timetable', 'responsive/timetable']) {
        for (const orientation of ['horizontal', 'vertical']) {
            for (const hours of ['event_hours', 'all_hours']) {
                const html = execFileSync(php, [join(root, 'tests/Support/day_layout_fixture.php'), '--html', layout, orientation, hours]);
                const file = join(temp, 'fixture.html');
                await writeFile(file, html);
                for (const width of [375, 1400]) {
                    await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
                    await command('Page.navigate', { url: pathToFileURL(file).href + '?case=' + checked });
                    await delay(180);
                    const { result } = await command('Runtime.evaluate', { returnByValue: true, expression: `(() => {
                        const blocks = [...document.querySelectorAll('.jem-timetable-block')];
                        const overlaps = [], outside = [];
                        for (const track of document.querySelectorAll('.jem-timetable-track')) {
                            const cards = [...track.querySelectorAll('.jem-timetable-block')];
                            const bounds = track.getBoundingClientRect();
                            cards.forEach((a, i) => {
                                const x = a.getBoundingClientRect();
                                if (x.width <= 0 || x.height <= 0 || x.left < bounds.left - 1 || x.right > bounds.right + 1
                                    || x.top < bounds.top - 1 || x.bottom > bounds.bottom + 1) outside.push(a.dataset.eventId);
                                cards.slice(i + 1).forEach(b => {
                                    const y = b.getBoundingClientRect();
                                    if (Math.min(x.right, y.right) - Math.max(x.left, y.left) > .5
                                        && Math.min(x.bottom, y.bottom) - Math.max(x.top, y.top) > .5) overlaps.push([a.dataset.eventId, b.dataset.eventId]);
                                });
                            });
                        }
                        return { ids: [...new Set(blocks.map(b => Number(b.dataset.eventId)))].sort((a,b)=>a-b), overlaps, outside,
                            pageWidth: document.documentElement.scrollWidth, viewport: innerWidth };
                    })()` });
                    const label = `${layout} / ${orientation} / ${hours} / ${width}px`;
                    assert.deepEqual(result.value.ids, Array.from({ length: 17 }, (_, i) => i + 1), label + ' all events');
                    assert.deepEqual(result.value.overlaps, [], label + ' overlaps');
                    assert.deepEqual(result.value.outside, [], label + ' bounds');
                    assert.ok(result.value.pageWidth <= width + 1, label + ' page overflow ' + JSON.stringify(result.value));
                    console.log('PASS ' + label);
                    checked++;
                }
            }
        }
    }
    console.log(`${checked} browser scenarios passed (real templates and CSS; mocked Joomla services).`);
} finally {
    if (socket?.readyState === WebSocket.OPEN) {
        try { await command('Browser.close'); } catch {}
        socket.close();
    }
    browser.kill();
    await delay(500);
    // Only this mkdtemp-created, task-specific directory may be removed.
    if (resolve(temp).startsWith(resolve(tmpdir()) + '\\jem-day-layout-') || resolve(temp).startsWith(resolve(tmpdir()) + '/jem-day-layout-')) {
        await rm(temp, { recursive: true, force: true, maxRetries: 6, retryDelay: 300 });
    }
}
