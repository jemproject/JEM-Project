// Focused browser regression, using an existing Joomla installation read-only.
// JEM_TEST_JOOMLA_ROOT, PHP_BINARY and CHROME_BINARY may override the local defaults.
import {spawn, execFileSync} from 'node:child_process';
import {readFile, mkdtemp, writeFile} from 'node:fs/promises';
import {createServer} from 'node:http';
import {tmpdir} from 'node:os';
import {resolve, join, extname} from 'node:path';
import assert from 'node:assert/strict';

const root = process.cwd();
const joomla = process.env.JEM_TEST_JOOMLA_ROOT || 'C:/xampp/htdocs/jl602';
const fixture = JSON.parse(execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
    ['tests/JavaScript/fixtures/image-credit-editor.php'], {encoding: 'utf8',
        env: {...process.env, JEM_TEST_JOOMLA_ROOT: joomla}}));
const server = createServer(async (request, response) => {
    try {
        const pathname = new URL(request.url, 'http://localhost').pathname;
        if (pathname === '/') {
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.end(`<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width">
                <link rel="stylesheet" href="/joomla/media/vendor/bootstrap/css/bootstrap.min.css">
                <link rel="stylesheet" href="/source/media/css/image-metadata.css">
                <style>body{padding:16px;font-family:system-ui}form{max-width:1100px;margin:auto}h2{font-size:1rem}</style>
                </head><body>${fixture.html.replaceAll(/data-jem-editor-base="[^"]*"/g,
                    'data-jem-editor-base="/joomla/media/vendor/tinymce"')}
                ${request.url.includes('fallback=1') ? '' : '<script src="/joomla/media/vendor/tinymce/tinymce.min.js"></script>'}
                <script src="/joomla/media/vendor/jquery/js/jquery.min.js"></script>
                <script src="/source/media/js/other.js"></script>
                <script src="/source/media/js/image-metadata.js"></script></body></html>`);
            return;
        }
        const base = pathname.startsWith('/source/') ? root : joomla;
        const path = resolve(base, pathname.replace(/^\/(source|joomla)\//, ''));
        if (!path.startsWith(resolve(base) + (process.platform === 'win32' ? '\\' : '/'))) throw new Error('Path');
        response.setHeader('Content-Type', {'.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml'}[extname(path)] || 'application/octet-stream');
        response.end(await readFile(path));
    } catch {response.writeHead(404); response.end();}
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const browser = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--disable-background-networking', '--disable-extensions', '--no-first-run',
    '--remote-debugging-port=0', '--user-data-dir=' + await mkdtemp(join(tmpdir(), 'jem-credit-test-')), 'about:blank'
], {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
let ws;
try {
    const endpoint = await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('Browser startup timeout')), 15000);
        browser.stderr.on('data', chunk => {
            const match = chunk.toString().match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) {clearTimeout(timeout); resolve(match[1]);}
        });
        browser.on('error', reject);
    });
    ws = new WebSocket(endpoint);
    await new Promise(resolve => {ws.onopen = resolve;});
    let sequence = 0;
    const pending = new Map();
    ws.onmessage = event => {
        const data = JSON.parse(event.data);
        const entry = pending.get(data.id);
        if (entry) {pending.delete(data.id); data.error ? entry.reject(data.error) : entry.resolve(data.result);}
    };
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++sequence; pending.set(id, {resolve, reject});
        ws.send(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}));
    });
    const {targetId} = await send('Target.createTarget', {url: 'about:blank'});
    const {sessionId} = await send('Target.attachToTarget', {targetId, flatten: true});
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true}, sessionId);
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    await send('Page.navigate', {url: 'http://127.0.0.1:' + server.address().port}, sessionId);
    let ready;
    for (let attempt = 0; attempt < 100; attempt++) {
        ready = await evaluate('window.tinymce && tinymce.get().length === 16 && tinymce.get().every(e => e.initialized)');
        if (ready) break;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.ok(ready, 'Sixteen caption/credit editors initialise across six FE/BE forms');
    assert.equal(await evaluate('!document.querySelector("#empty_credit details").open'), true, 'Empty metadata starts collapsed');
    assert.equal(await evaluate('[...document.querySelectorAll(".jem-image-upload-panel details")].every(d=>d.open)'), true, 'Populated metadata starts expanded');
    const result = await evaluate(`(() => {
        const results = [];
        for (const editor of tinymce.get().filter(e=>e.targetElm.hasAttribute('data-jem-credit-editor'))) {
            const field = editor.targetElm, card = field.closest('.jem-image-metadata__card');
            const noPreview = !card.querySelector('[data-jem-credit-preview]');
            const label = card.querySelector('[data-jem-credit-label]');
            const position = card.querySelector('select[name$="[footer]"]');
            const initial = field.value;
            editor.setContent('Jane Smith, Museum, CC BY-SA 4.0');
            const node = editor.getBody().querySelector('p').firstChild;
            const range = editor.getDoc().createRange(); range.setStart(node, 0); range.setEnd(node, 10);
            editor.selection.setRng(range);
            editor.execCommand('mceInsertLink', false, {href:'https://example.org/author?a=1&b=2'});
            editor.save(); field.dispatchEvent(new Event('input', {bubbles:true}));
            const saved = new DOMParser().parseFromString(field.value, 'text/html');
            const linked = saved.querySelector('a')?.textContent === 'Jane Smith';
            const href = saved.querySelector('a')?.getAttribute('href');
            editor.selection.select(editor.getBody().querySelector('a'));
            editor.execCommand('unlink'); editor.save();
            const unlinked = !field.value.includes('<a ') && field.value.includes('Jane Smith');
            editor.setContent('<p>Author</p><p>Source</p>'); editor.save();
            const breaks = field.value.includes('Author<br>Source');
            if (!breaks) throw new Error('Unexpected serialized credit: ' + field.value);
            label.value = 'none'; label.dispatchEvent(new Event('change',{bubbles:true}));
            const none = card.querySelector('[data-jem-credit-custom]').hidden && field.value === 'Author<br>Source';
            label.value = 'custom'; label.dispatchEvent(new Event('change',{bubbles:true}));
            const custom = card.querySelector('[data-jem-credit-custom] input');
            custom.value = '<img src=x onerror=alert(1)>:'; custom.dispatchEvent(new Event('input',{bubbles:true}));
            const escaped = !card.querySelector('img') && !custom.parentElement.hidden;
            position.value = '1'; position.dispatchEvent(new Event('change',{bubbles:true}));
            const savedPosition = new FormData(field.form).get(position.name) === '1';
            editor.setContent('<a href="javascript:alert(1)">bad</a><img src=x onerror=alert(1)><script>alert(1)</script>');
            editor.save();
            const safe = !/<a |<img|<script|onerror|javascript:/i.test(field.value);
            // Exercise the same input/change sequence used by the existing Clear handler.
            field.value = ''; field.dispatchEvent(new Event('input',{bubbles:true}));
            field.dispatchEvent(new Event('change',{bubbles:true}));
            const cleared = editor.getContent() === '';
            results.push({id:field.id, noPreview, linked, href, unlinked, breaks, none, escaped, savedPosition, safe, cleared,
                initialPreserved:initial.includes('https://example.org/source')});
        }
        return results;
    })()`);
    for (const item of result) {
        assert.equal(item.href, 'https://example.org/author?a=1&b=2');
        for (const [key, value] of Object.entries(item)) if (!['id', 'href'].includes(key)) assert.equal(value, true, item.id + ':' + key);
    }
    await evaluate('document.querySelectorAll("form").forEach(f => f.reset())');
    await new Promise(resolve => setTimeout(resolve, 150));
    assert.equal(await evaluate('tinymce.get().filter(e=>e.targetElm.hasAttribute("data-jem-credit-editor")).every(e => e.getContent().includes("https://example.org/source"))'), true, 'Form reset restores all credit editors');
    const captions = await evaluate(`(() => {
        const editors=tinymce.get().filter(e=>e.targetElm.hasAttribute('data-jem-caption-editor'));
        return editors.map(e=>{
            e.setContent('Museum workshop');
            e.selection.select(e.getBody().querySelector('p'),true);
            e.execCommand('Bold'); e.execCommand('Italic'); e.save();
            const formatted=e.targetElm.value.includes('<strong>') && e.targetElm.value.includes('<em>');
            const toolbar=e.options.get('toolbar') === 'bold italic forecolor';
            return {formatted,toolbar};
        });
    })()`);
    assert.equal(captions.length,8);
    captions.forEach(c=>{assert.ok(c.formatted);assert.ok(c.toolbar);});
    const colours = await evaluate(`(() => tinymce.get().map(e => {
        e.setContent('<p>Colour sample</p>');
        e.selection.select(e.getBody().querySelector('p'),true);
        e.execCommand('ForeColor',false,'#1234ab'); e.save();
        const value=e.targetElm.value;
        const applied=value.includes('style="color: #1234ab;"');
        e.setContent(value); e.save();
        const roundTrip=e.targetElm.value===value;
        e.selection.select(e.getBody().querySelector('p'),true);
        e.execCommand('mceRemoveTextcolor','forecolor'); e.save();
        const removed=!e.targetElm.value.includes('style=') && e.targetElm.value.includes('Colour sample');
        return {applied,roundTrip,removed,compact:!e.options.get('toolbar').includes('undo') && !e.options.get('toolbar').includes('redo')};
    }))()`);
    assert.equal(colours.length,16);
    colours.forEach(c=>Object.entries(c).forEach(([key,value])=>assert.ok(value,'Text colour: '+key)));
    await evaluate('document.querySelectorAll("form").forEach(f=>f.reset())');
    await new Promise(resolve=>setTimeout(resolve,100));
    assert.equal(await evaluate('tinymce.get().filter(e=>e.targetElm.hasAttribute("data-jem-caption-editor")).every(e=>e.getContent().includes("Museum workshop") && !e.getContent().includes("<strong>"))'),true,'Caption reset restores original content');
    await evaluate('tinymce.get().find(e=>e.targetElm.hasAttribute("data-jem-credit-editor")).execCommand("mceLink", false, {dialog:true})');
    assert.equal(await evaluate('!!document.querySelector(".tox-dialog input")'), true, 'Native link dialog opens');
    await evaluate('tinymce.get().find(e=>e.targetElm.hasAttribute("data-jem-credit-editor")).windowManager.close()');
    assert.equal(await evaluate(`(() => {
        const form=document.querySelector('#site_venue_advanced');
        form.querySelector('.jem-image-clear').click();
        const field=form.querySelector('[data-jem-credit-editor]');
        const caption=form.querySelector('[data-jem-caption-editor]');
        return !form.querySelector('details').open && field.value === '' && tinymce.get(field.id).getContent() === '' && tinymce.get(caption.id).getContent() === '';
    })()`), true, 'Actual Clear button empties and collapses the image metadata');
    await evaluate('document.querySelector("#empty_credit summary").click()');
    let lazy;
    for (let attempt=0; attempt<50; attempt++) {
        lazy=await evaluate('tinymce.get().length === 18 && tinymce.get().every(e=>e.initialized)');
        if(lazy) break;
        await new Promise(resolve=>setTimeout(resolve,100));
    }
    assert.ok(lazy, 'Empty credit editor loads when its disclosure opens');
    const typography=await evaluate(`(() => {
        const fixture=document.createElement('div');
        fixture.innerHTML='<div id="jem"><div class="jem-image-caption__text">Default <strong>Bold <em>Both</em></strong> <em>Italic</em></div></div><div class="lightbox"><div class="lb-caption"><div class="jem-lightbox-caption">Default <strong>Bold <em>Both</em></strong> <em>Italic</em></div></div></div><div class="jem-image-popup__caption">Default <strong>Bold <em>Both</em></strong> <em>Italic</em></div>';
        document.body.append(fixture);
        const result=[...fixture.querySelectorAll('.jem-image-caption__text,.jem-lightbox-caption,.jem-image-popup__caption')].map(e=>({
            base:getComputedStyle(e).fontWeight,bold:getComputedStyle(e.querySelector('strong')).fontWeight,
            italic:getComputedStyle(e.querySelector('em')).fontStyle}));
        fixture.remove();return result;
    })()`);
    typography.forEach(t=>assert.deepEqual(t,{base:'600',bold:'700',italic:'italic'}));
    const displayColours=await evaluate(`(() => {
        const fixture=document.createElement('div');
        fixture.innerHTML='<div id="jem"><div class="jem-image-caption__text"></div><div class="jem-image-credit"></div><div class="jem-image-credit jem-image-credit--footer"></div></div><div class="lightbox"><div class="lb-caption"><div class="jem-lightbox-caption"></div><div class="jem-lightbox-credit"></div></div></div><div class="jem-image-popup__caption"></div><div class="jem-image-popup__credit"></div>';
        const blocks=fixture.querySelectorAll('[class]');
        const targets=[...blocks].filter(e=>e.className!=='lightbox' && e.className!=='lb-caption');
        targets.forEach(e=>{e.innerHTML='<span style="color:#1234ab">Colour</span>';});
        document.body.append(fixture);
        const colours=targets.map(e=>getComputedStyle(e.firstChild).color);
        fixture.remove();return colours;
    })()`);
    assert.equal(displayColours.length,7);
    displayColours.forEach(c=>assert.equal(c,'rgb(18, 52, 171)'));
    assert.equal(await evaluate(`(() => {const d=document.querySelector('details'),s=d.querySelector('summary');
        return [d,s].every(e=>getComputedStyle(e).borderTopWidth==='0px' && getComputedStyle(e).paddingTop==='0px');})()`),true,'Disclosure has no border or padding');
    for (const width of [1400, 390]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: false}, sessionId);
        assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true, 'No overflow at ' + width);
        if (process.env.JEM_TEST_SCREENSHOT) {
            const shot = await send('Page.captureScreenshot', {format:'png'}, sessionId);
            await writeFile(resolve(process.env.JEM_TEST_SCREENSHOT + '-' + width + '.png'), Buffer.from(shot.data, 'base64'));
        }
    }
    await send('Page.navigate', {url:'http://127.0.0.1:' + server.address().port + '/?fallback=1'}, sessionId);
    let fallback;
    for (let attempt = 0; attempt < 50; attempt++) {
        fallback = await evaluate(`document.readyState === 'complete' && !window.tinymce &&
            document.querySelectorAll('[data-jem-credit-editor]').length === 9 &&
            [...document.querySelectorAll('[data-jem-credit-editor]')].every(f =>
                getComputedStyle(f).display !== 'none' && (f.form.id === 'empty_credit' || new FormData(f.form).get(f.name).includes('https://example.org/source')))`);
        if (fallback) break;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.ok(fallback, 'Textarea remains usable without TinyMCE');
    console.log(JSON.stringify({formChecks:fixture.formChecks, editorCases:result.length, reset:true, widths:[1400,390], results:result}, null, 2));
} finally {
    ws?.close(); browser.kill(); server.closeAllConnections(); server.close();
}
