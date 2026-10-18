import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {execFileSync, spawn} from 'node:child_process';
import {readFile, mkdtemp, writeFile} from 'node:fs/promises';
import {resolve, join} from 'node:path';
import {tmpdir} from 'node:os';

const root = process.cwd();
const joomla = process.env.JEM_TEST_JOOMLA_ROOT || 'C:/xampp/htdocs/jl602';
const fixture = JSON.parse(execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
    ['tests/JavaScript/fixtures/jed-review.php'], {encoding: 'utf8'}));
let dismissed = {donate:false, jed:false}, fail = false, writes = 0;
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (request.method === 'POST') {
            let body = '';
            for await (const chunk of request) body += chunk;
            assert.match(body, /name="fixture_token"\r\n\r\n1/);
            assert.match(body, /name="format"\r\n\r\njson/);
            assert.equal(request.headers.accept, 'application/json');
            const action = body.match(/name="review_action"\r\n\r\n(dismiss|show)/)?.[1];
            const card = body.match(/name="community_card"\r\n\r\n(donate|jed)/)?.[1];
            assert.equal(action, 'dismiss');
            assert.ok(card);
            if (!fail) {dismissed[card] = true; writes++;}
            response.setHeader('Content-Type', 'application/json');
            response.end(JSON.stringify({success: !fail, data: fail ? null : {dismissed:true,card}, message: 'Preference saved.'}));
        } else if (url.pathname === '/script.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(await readFile(join(root, 'media/js/jed-review.js')));
        } else if (url.pathname === '/bootstrap.css') {
            response.setHeader('Content-Type', 'text/css');
            response.end(await readFile(join(joomla, 'media/vendor/bootstrap/css/bootstrap.min.css')));
        } else if (url.pathname === '/atum.css') {
            response.setHeader('Content-Type', 'text/css');
            response.end(await readFile(join(joomla, 'media/templates/administrator/atum/css/template.min.css')));
        } else if (['/community-cards.css', '/backend.css'].includes(url.pathname)) {
            response.setHeader('Content-Type', 'text/css');
            response.end(await readFile(join(root, 'media/css', url.pathname.slice(1))));
        } else if (/^\/images\/icon-48-(donate|jed-review|help)\.svg$/.test(url.pathname) || ['/images/PayPal_DonateButton.webp', '/images/jem-review-banner.webp'].includes(url.pathname)) {
            response.setHeader('Content-Type', url.pathname.endsWith('.webp') ? 'image/webp' : 'image/svg+xml');
            response.end(await readFile(join(root, 'media', url.pathname.slice(1))));
        } else {
            response.setHeader('Content-Type', 'text/html');
            response.end(`<!doctype html><html lang="en" data-bs-theme="${url.searchParams.get('theme') === 'dark' ? 'dark' : 'light'}">
                <head><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/atum.css"><link rel="stylesheet" href="/backend.css"><link rel="stylesheet" href="/community-cards.css">
                <style>body{padding:16px}main{max-width:1100px;margin:auto}</style></head><body><main>
                <h1>JEM Dashboard</h1><a href="#events">Events</a>
                ${fixture[Number(dismissed.donate) + ':' + Number(dismissed.jed)]}</main><script defer src="/script.js"></script></body></html>`);
        }
    } catch (error) {console.error(error); response.writeHead(500); response.end(String(error));}
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const browser = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--disable-background-networking', '--disable-extensions', '--no-first-run',
    '--remote-debugging-port=0', '--user-data-dir=' + await mkdtemp(join(tmpdir(), 'jem-jed-test-')), 'about:blank'
], {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
let ws;
try {
    const endpoint = await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('Chrome startup timeout')), 15000);
        browser.stderr.on('data', chunk => {
            const match = chunk.toString().match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) {clearTimeout(timeout); resolve(match[1]);}
        });
        browser.on('error', reject);
    });
    ws = new WebSocket(endpoint);
    await new Promise(resolve => {ws.onopen = resolve;});
    let sequence = 0;
    const pending = new Map(), errors = [];
    ws.onmessage = event => {
        const data = JSON.parse(event.data);
        if (data.method === 'Runtime.exceptionThrown') errors.push(data.params);
        const call = pending.get(data.id);
        if (call) {pending.delete(data.id); data.error ? call.reject(data.error) : call.resolve(data.result);}
    };
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++sequence;
        pending.set(id, {resolve, reject});
        ws.send(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}));
    });
    const {targetId} = await send('Target.createTarget', {url: 'about:blank'});
    const {sessionId} = await send('Target.attachToTarget', {targetId, flatten: true});
    await send('Runtime.enable', {}, sessionId);
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true}, sessionId);
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const waitFor = async expression => {
        for (let i = 0; i < 60; i++) {
            if (await evaluate(expression)) return;
            await new Promise(resolve => setTimeout(resolve, 50));
        }
        throw new Error('Timeout: ' + expression + ' ' + JSON.stringify(await evaluate(`({
            status:document.querySelector('[data-jem-community-status]')?.textContent,
            active:document.activeElement?.outerHTML, url:location.href})`)));
    };
    const navigate = async (suffix = '') => {
        await send('Page.navigate', {url: 'http://127.0.0.1:' + server.address().port + suffix}, sessionId);
        await waitFor('document.readyState === "complete" && !!document.querySelector("[data-jem-community-cards]")');
    };
    await navigate();
    assert.equal(await evaluate('document.querySelector("[data-jem-community-card=jed]").hidden'), false);
    const link = await evaluate(`(() => {const a=document.querySelector('[data-jem-community-card=jed] a');return [a.href,a.target,a.rel,a.textContent]})()`);
    assert.equal(link[0], 'https://extensions.joomla.org/extension/jem/');
    assert.equal(link[1], '_blank');
    assert.equal(link[2], 'noopener noreferrer');
    assert.match(link[3], /external website, opens in a new tab/);
    await evaluate('window.noReloadMarker = 42; document.querySelector("[data-jem-community-card=jed] button").focus()');
    await send('Input.dispatchKeyEvent', {type:'keyDown', key:'Enter', code:'Enter', text:'\r', windowsVirtualKeyCode:13}, sessionId);
    await send('Input.dispatchKeyEvent', {type:'keyUp', key:'Enter', code:'Enter', windowsVirtualKeyCode:13}, sessionId);
    await waitFor('document.querySelector("[data-jem-community-card=jed]").hidden');
    assert.equal(await evaluate('window.noReloadMarker'), 42);
    assert.equal(await evaluate('document.activeElement.closest("[data-jem-community-button]").dataset.jemCommunityButton'), 'jed');
    assert.equal(await evaluate('document.querySelector("[data-jem-community-card=donate]").hidden'), false);
    assert.equal(await evaluate('document.querySelector("[data-jem-community-button=donate]").hidden'), true);
    assert.equal(writes, 1);
    await navigate();
    assert.equal(await evaluate('document.querySelector("[data-jem-community-card=jed]").hidden'), true);
    fail = true;
    await evaluate('document.querySelector("[data-jem-community-card=donate] button").click()');
    await waitFor('document.querySelector("[data-jem-community-status]").textContent.includes("could not be saved")');
    assert.equal(await evaluate('document.querySelector("[data-jem-community-card=donate]").hidden'), false);
    assert.equal(await evaluate('document.querySelector("[data-jem-community-card=donate] button").disabled'), false);
    assert.equal(writes, 1);
    fail = false;
    await evaluate('document.querySelector("[data-jem-community-card=donate] button").click()');
    await waitFor('document.querySelector("[data-jem-community-cards]").hidden');
    assert.equal(writes, 2);
    assert.equal(await evaluate('[...document.querySelectorAll("[data-jem-community-button]")].every(b=>!b.hidden)'), true);
    await navigate();
    assert.equal(await evaluate('getComputedStyle(document.querySelector("[data-jem-community-cards]")).display'), 'none');
    for (const theme of ['light', 'dark']) {
        for (const width of [1200, 390]) {
            await send('Emulation.setDeviceMetricsOverride', {width, height:800, deviceScaleFactor:1, mobile:width === 390}, sessionId);
            for (const state of [[false,false],[true,false],[false,true],[true,true]]) {
                dismissed = {donate:state[0],jed:state[1]};
                await navigate('/?theme=' + theme);
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
                assert.equal(await evaluate(`['donate','jed'].every(key=> {
                    const card=document.querySelector('[data-jem-community-card='+key+']');
                    const tile=document.querySelector('[data-jem-community-button='+key+']');
                    return (getComputedStyle(card).display !== 'none') !== (getComputedStyle(tile).display !== 'none');
                })`), true, 'Exactly one visible representation per action');
                if (!state[0] && !state[1]) {
                    const imagePosition = await evaluate(`(() => {
                        const img=document.querySelector('.jem-community-donate-image');
                        const text=img.parentElement.querySelector('p').getBoundingClientRect();
                        const image=img.getBoundingClientRect();
                        return {loaded:img.complete && img.naturalWidth>0,right:image.left>=text.right,below:image.top>=text.bottom};
                    })()`);
                    assert.equal(imagePosition.loaded, true, 'Original PayPal image is loaded');
                    assert.equal(width >= 768 ? imagePosition.right : imagePosition.below, true, 'Image is right on desktop and below on mobile');
                    const slogan = await evaluate(`(() => {
                        const el=document.querySelector('.jem-community-review-image');
                        const box=el.getBoundingClientRect();
                        const image=document.querySelector('.jem-community-donate-image').getBoundingClientRect();
                        const text=el.parentElement.querySelector('.card-text').getBoundingClientRect();
                        return {text:el.alt,loaded:el.complete && el.naturalWidth===720,sameWidth:Math.abs(box.width-image.width)<1,
                            sameHeight:Math.abs(box.height-image.height)<1,right:box.left>=text.right,
                            below:box.top>=text.bottom,fits:el.scrollHeight<=el.clientHeight};
                    })()`);
                    assert.match(slogan.text, /Your review\s+helps JEM grow/);
                    assert.equal(slogan.loaded, true, 'Local review banner is loaded');
                    assert.equal(slogan.sameWidth && slogan.sameHeight, true, 'Slogan matches PayPal size and ratio');
                    assert.equal(width >= 768 ? slogan.right : slogan.below, true, 'Slogan follows image placement');
                    assert.equal(slogan.fits, true, 'Slogan text fits without clipping');
                    const rects = await evaluate(`[...document.querySelectorAll('[data-jem-community-card]')].map(c=>{const r=c.getBoundingClientRect();return {top:r.top,left:r.left}})`);
                    assert.equal(rects[0].top === rects[1].top, width >= 768, 'Desktop columns and mobile stack');
                }
                if (state[0] && state[1]) {
                    assert.equal(await evaluate(`(() => {
                        const reference=document.querySelector('a[href="#help"] img').getBoundingClientRect();
                        return [...document.querySelectorAll('[data-jem-community-button] img')].every(i=> {
                            const rect=i.getBoundingClientRect();
                            return i.complete && i.naturalWidth>0 && rect.height===reference.height && rect.width===reference.width;
                        });
                    })()`), true, 'Shortcut icons match the standard dashboard image dimensions');
                    assert.equal(await evaluate(`[...document.querySelectorAll('[data-jem-community-button] a')].every(a=>
                        getComputedStyle(a,'::before').content==='none' && a.target==='_blank' &&
                        a.rel==='noopener noreferrer' && a.querySelector('.visually-hidden').textContent.includes('new tab'))`), true,
                    'Compact shortcuts omit the Atum marker but preserve external-link accessibility and safety');
                }
                if (!state[1]) {
                    assert.notEqual(await evaluate(`getComputedStyle(document.querySelector('[data-jem-community-card=jed] a'),'::before').content`), 'none',
                        'Atum external-link markers outside compact shortcuts are unchanged');
                }
                if (process.env.JEM_TEST_SCREENSHOT && state[0] === state[1]) {
                    const shot = await send('Page.captureScreenshot', {format:'png'}, sessionId);
                    await writeFile(resolve(process.env.JEM_TEST_SCREENSHOT + '-' + theme + '-' + width + (state[0]?'-buttons':'-cards') + '.png'), Buffer.from(shot.data,'base64'));
                }
            }
        }
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({keyboardDismiss:true, noReload:true, independentCards:true, reloadPersistence:true,
        exactlyOneAction:true, stateThemeWidthCombinations:16, safeExternalLink:true, serverFailure:true,
        widths:[1200,390], themes:['light','dark'], javascriptErrors:errors.length}));
} finally {
    ws?.close(); browser.kill(); server.closeAllConnections(); server.close();
}
