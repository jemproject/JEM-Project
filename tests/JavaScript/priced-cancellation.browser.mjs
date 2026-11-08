import {spawn, execFileSync} from 'node:child_process';
import {createServer} from 'node:http';
import {mkdtemp, readFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join, resolve, sep} from 'node:path';
import assert from 'node:assert/strict';

const root = resolve(process.env.JEM_TEST_SOURCE_ROOT || process.cwd());
assert(process.env.JEM_TEST_JOOMLA_ROOT && process.env.JEM_TEST_PRICED_CANCELLATION === '1', 'Disposable SQL fixture opt-in required');
const fixture = JSON.parse(execFileSync(process.env.JEM_TEST_PHP || 'php', [root + '/tests/Support/priced_frontend_cancellation_probe.php', process.env.JEM_TEST_JOOMLA_ROOT, root], {encoding: 'utf8', maxBuffer: 1024 * 1024}));
const javascript = await readFile(root + '/media/js/registration-cancellation.js', 'utf8');
const previews = [], posted = [];
const server = createServer(async (req, res) => {
    if (req.method === 'POST') {
        const chunks = [];
        for await (const part of req) chunks.push(part);
        const request = new Request('http://localhost' + req.url, {method: 'POST', headers: req.headers, body: Buffer.concat(chunks)});
        const data = Object.fromEntries(await request.formData());
        if (data.task === 'event.cancellationPreview') {
            previews.push(data);
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify({success: true, data: {required: data.cancelplaces !== '0', fingerprint: 'a'.repeat(64),
                message: 'Confirm selected cancellation', events: [{label: 'Workshop', remaining: 4 - Number(data.cancelplaces), areas: []}]}}));
        } else {
            posted.push(data);
            res.end('Recorded cancellation test POST');
        }
        return;
    }
    if (req.url === '/cancellation.js') { res.setHeader('Content-Type', 'text/javascript'); res.end(javascript); return; }
    const key = decodeURIComponent(req.url.slice('/fixture/'.length));
    if (req.url.startsWith('/fixture/') && fixture.html[key]) {
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        res.end('<!doctype html><meta charset="utf-8"><script>window.Joomla={Text:{_:key=>({COM_JEM_CANCELLATION_FULL:"All places cancelled",COM_JEM_CANCELLATION_REMAINING:"Remaining: %s"}[key]||key)}};function check(box,send){send.disabled=!box.checked;}</script>' + fixture.html[key] + '<script src="/cancellation.js"></script>');
    } else { res.writeHead(404); res.end(); }
});
await new Promise(r => server.listen(0, '127.0.0.1', r));
const origin = 'http://127.0.0.1:' + server.address().port;
const profile = await mkdtemp(join(tmpdir(), 'jem2489-browser-'));
const browser = spawn(process.env.JEM_TEST_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
let socket;
try {
  const endpoint=await new Promise((r,j)=>{browser.stderr.on('data',d=>{const m=d.toString().match(/DevTools listening on (ws:\/\/\S+)/);if(m)r(m[1]);});setTimeout(()=>j(Error('Browser timeout')),15000).unref();});
  socket=new WebSocket(endpoint);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
  let seq=0,nextAccept=false,dialogs=[];const pending=new Map();
  const send=(method,params={},sessionId)=>new Promise((r,j)=>{const id=++seq;pending.set(id,{r,j});socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));});
  socket.onmessage=e=>{const d=JSON.parse(e.data),p=pending.get(d.id);if(p){pending.delete(d.id);d.error?p.j(d.error):p.r(d.result);}if(d.method==='Page.javascriptDialogOpening'){dialogs.push(d.params.message);send('Page.handleJavaScriptDialog',{accept:nextAccept},d.sessionId);}};
  const {targetId}=await send('Target.createTarget',{url:'about:blank'});
  const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
  await send('Page.enable',{},sessionId);
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId);if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
  const wait=async expr=>{for(let i=0;i<100;i++){if(await evaluate(expr))return;await new Promise(r=>setTimeout(r,50));}throw Error('Wait failed: '+expr);};
  const navigate=async url=>{await send('Page.navigate',{url},sessionId);await wait('location.href === '+JSON.stringify(url)+' && document.readyState === "complete"');};


    let checks = 0;
    const check = (actual, expected) => { assert.deepEqual(actual, expected); checks++; };
    for (const layout of ['default_regform.php', 'responsive/default_regform.php']) {
        for (const scenario of ['priced_existing', 'priced_areas', 'priced_series']) {
            await navigate(origin + '/fixture/' + encodeURIComponent(layout + '/' + scenario));
            check(await evaluate('document.querySelector("#cancelplaces").type'), 'hidden');
            check(await evaluate('document.querySelector("#cancelplaces").value'), '4');
            check(await evaluate('document.querySelectorAll("[data-cancel-area]").length'), 0);
            check(await evaluate('document.querySelector("#jem_send_attend").textContent.trim()'), 'Cancel my entire reservation');
            if (scenario === 'priced_series') await evaluate('document.querySelector("[name=reg_check_series]").click()');
            const saved = posted.length, reviewed = previews.length;
            await evaluate('document.querySelector("#jem_send_attend").click()');
            await wait('document.querySelector("dialog").open');
            check(previews.length, reviewed + 1);
            check(posted.length, saved);
            check(await evaluate('document.querySelector("dialog").textContent.includes("All places cancelled")'), true);
            await evaluate('document.querySelector("[data-cancel-back]").click()');
            check(await evaluate('document.querySelector("[name=jem_cancel_confirm]").value'), '0');
            check(posted.length, saved);
            await evaluate('document.querySelector("#jem_send_attend").click()');
            await wait('document.querySelector("dialog").open');
            await evaluate('document.querySelector("[data-cancel-proceed]").click(); document.querySelector("[data-cancel-proceed]").click();');
            await wait('document.body.textContent.includes("Recorded cancellation test POST")');
            check(posted.length, saved + 1);
            check(posted.at(-1).cancelplaces, '4');
            check(posted.at(-1).reg_check, '-1');
            check(posted.at(-1).jem_cancel_confirm, '1');
            check(posted.at(-1).jem_cancel_fingerprint, 'a'.repeat(64));
            check(posted.at(-1).addplaces, undefined);
            check(posted.at(-1).reg_check_series, scenario === 'priced_series' ? '1' : undefined);
        }
        for (const scenario of ['priced_new', 'priced_closed', 'priced_cancelled']) {
            await navigate(origin + '/fixture/' + encodeURIComponent(layout + '/' + scenario));
            check(await evaluate('document.querySelectorAll("form").length'), 0);
        }
        await navigate(origin + '/fixture/' + encodeURIComponent(layout + '/classic_existing'));
        await evaluate('document.querySelector("#jem_unregister_event").click(); document.querySelector("#cancelplaces").value="1"; document.querySelector("#cancelplaces").dispatchEvent(new Event("input",{bubbles:true})); document.querySelector("#jem_send_attend").click();');
        await wait('document.querySelector("dialog").open');
        check(await evaluate('document.querySelector("dialog").textContent.includes("Remaining: 3")'), true);
        await evaluate('document.querySelector("[data-cancel-proceed]").click()');
        await wait('document.body.textContent.includes("Recorded cancellation test POST")');
        check(posted.at(-1).cancelplaces, '1');
        await navigate(origin + '/fixture/' + encodeURIComponent(layout + '/requested_no_answer'));
        await evaluate('document.querySelector("#jem_send_attend").click()');
        await wait('document.body.textContent.includes("Recorded cancellation test POST")');
        check(posted.at(-1).cancelplaces, '0');
        check(posted.at(-1).jem_cancel_confirm, '0');
    }
    console.log(JSON.stringify({passed: true, checks, submitted: posted.length, previews: previews.length}));
    await send('Browser.close');
} finally {
    socket?.close(); browser.kill(); server.close();
    const absoluteProfile = resolve(profile), temporaryRoot = resolve(tmpdir());
    if (absoluteProfile.startsWith(temporaryRoot + sep + 'jem2489-browser-')) {
        await rm(absoluteProfile, {recursive: true, force: true, maxRetries: 5, retryDelay: 200});
    }
}
