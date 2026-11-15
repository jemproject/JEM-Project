import {spawn, execFileSync} from 'node:child_process';
import {createServer} from 'node:http';
import {mkdtemp, readFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join, resolve, sep} from 'node:path';
import assert from 'node:assert/strict';

const root = resolve(process.env.JEM_TEST_SOURCE_ROOT || process.cwd());

const fixture = JSON.parse(execFileSync(process.env.JEM_TEST_PHP || 'php', [root + '/tests/Support/registration_layout_probe.php'], {encoding: 'utf8', maxBuffer: 1024 * 1024}));
const styles = (await Promise.all(['jem.css', 'jem-responsive.css', 'registration-cancellation.css'].map(file => readFile(root + '/media/css/' + file, 'utf8')))).join('\n');
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
        res.end('<!doctype html><meta charset="utf-8"><script>window.Joomla={Text:{_:key=>({COM_JEM_CANCELLATION_FULL:"All places cancelled",COM_JEM_CANCELLATION_REMAINING:"Remaining: %s"}[key]||key)}};function check(box,send){send.disabled=!box.checked;}</script>' + '<style>' + styles + '</style><div id="jem"><div class="register"><dd class="registration">' + fixture.html[key] + '</dd></div></div><script src="/cancellation.js"></script>');
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
  for (const width of [1100, 375]) {
    await send('Emulation.setDeviceMetricsOverride', {width, height: 900, deviceScaleFactor: 1, mobile: false}, sessionId);
    for (const layout of ['default_regform.php', 'responsive/default_regform.php']) {
      await navigate(origin + '/fixture/' + encodeURIComponent(layout + '/classic_areas'));
      const initial=await evaluate(`(() => {
        const booking=document.querySelector('.jem-capacity-registration');
        const cancel=document.querySelector('.jem-cancellation-areas');
        const deadline=document.querySelector('.jem-cancellation-deadline');
        return {bookingInside:!!booking.closest('.jem-booking-action'),bookingVisible:!booking.hidden,
          bookingEnabled:[...booking.querySelectorAll('input')].every(input=>!input.disabled),
          cancelHidden:cancel.hidden&&cancel.disabled,
          deadlineInside:!!deadline.closest('.jem-cancellation-action'),
          deadlineCount:document.querySelectorAll('.jem-cancellation-deadline').length};
      })()`);
      assert.deepEqual(initial,{bookingInside:true,bookingVisible:true,bookingEnabled:true,cancelHidden:true,deadlineInside:true,deadlineCount:1});checks++;
      await evaluate('document.querySelector("#jem_unregister_event").click()');
      assert.ok(await evaluate(`document.querySelector('.jem-capacity-registration').hidden && [...document.querySelectorAll('.jem-capacity-registration input')].every(input=>input.disabled)`));checks++;

      const geometry = await evaluate(`(() => {
        const areas=document.querySelector('.jem-cancellation-areas');
        const action=areas.closest('li, .jem-cancellation-action');
        const send=document.querySelector('#jem_send_attend');
        return {visible: !areas.hidden, inside: !!action && action.contains(document.querySelector('#jem_unregister_event')),
          sendOutside: !action.contains(send), sendAfter: send.getBoundingClientRect().top >= areas.getBoundingClientRect().bottom,
          rows: [...areas.querySelectorAll('label')].map(label => {
            const a=label.querySelector('span').getBoundingClientRect(), b=label.querySelector('input').getBoundingClientRect();
            return a.right <= b.left && b.right <= innerWidth && Math.abs((a.top+a.bottom-b.top-b.bottom)/2) < 2;
          })};
      })()`);
      assert.deepEqual(geometry, {visible:true,inside:true,sendOutside:true,sendAfter:true,rows:[true,true]}); checks++;
      const appearance=await evaluate(`(() => {
        const total=document.querySelector('#cancelplaces'), send=document.querySelector('#jem_send_attend');
        return {readonly:total.readOnly, arrows:getComputedStyle(total).appearance,
          labelSize:getComputedStyle(document.querySelector('.jem-cancellation-areas legend')).fontSize,
          sendMargin:parseFloat(getComputedStyle(send).marginTop)};
      })()`);
      assert.deepEqual(appearance,{readonly:true,arrows:'textfield',labelSize:'16px',sendMargin:16});checks++;
      await evaluate(`const fields=document.querySelectorAll('[data-cancel-area]'); fields[0].value='0'; fields[1].value='1'; fields[1].dispatchEvent(new Event('input',{bubbles:true}));`);
      assert.equal(await evaluate('document.querySelector("#cancelplaces").value'), '1'); checks++;
      await evaluate('document.querySelector("#jem_send_attend").click()');
      await wait('document.querySelector("dialog").open');
      assert.equal(posted.length, 0); checks++;
      await evaluate('document.querySelector("[data-cancel-back]").click()');
      assert.equal(posted.length, 0); checks++;
      await evaluate(`document.querySelector('[name="reg_check"][value="1"]').click()`);
      const restored=await evaluate(`({visible:!document.querySelector('.jem-capacity-registration').hidden,
        enabled:[...document.querySelectorAll('.jem-capacity-registration input')].every(input=>!input.disabled),
        quantities:[...document.querySelectorAll('.jem-capacity-registration input')].map(input=>input.value),
        cancellationHidden:document.querySelector('.jem-cancellation-areas').hidden})`);
      assert.deepEqual(restored,{visible:true,enabled:true,quantities:['2','2'],cancellationHidden:true});checks++;
    }
  }
  console.log(JSON.stringify({checks, result:'passed', widths:[1100,375], actualRegistrationWrites:0}));
} finally {
  socket?.close(); browser.kill();
  await new Promise(r=>browser.exitCode !== null ? r() : browser.once('exit',r));
  await new Promise(r=>server.close(r));
  assert(profile.startsWith(tmpdir()) && profile.includes('jem2489-browser-'));
  await rm(profile, {recursive:true,force:true,maxRetries:4,retryDelay:150});
}
