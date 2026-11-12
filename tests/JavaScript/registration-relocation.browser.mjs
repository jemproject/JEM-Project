import {spawn, execFileSync} from 'node:child_process';
import {createServer} from 'node:http';
import {mkdtemp, readFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join, resolve, sep} from 'node:path';
import assert from 'node:assert/strict';

const root = resolve(process.env.JEM_TEST_SOURCE_ROOT || process.cwd());
const html = execFileSync(process.env.JEM_TEST_PHP || 'php', [root + '/tests/Support/relocation_editor_probe.php', root], {encoding:'utf8'});
const js = await readFile(root + '/media/js/registration-relocation.js', 'utf8');
const requests = []; let delay = 0, reject = false;
const server = createServer(async (req, res) => {
    if (req.method === 'POST') {
        const chunks=[]; for await(const chunk of req) chunks.push(chunk);
        const request=new Request('http://localhost'+req.url,{method:'POST',headers:req.headers,body:Buffer.concat(chunks)});
        const data=Object.fromEntries(await request.formData()); requests.push(data);
        res.setHeader('Content-Type','application/json');
        if(data.task==='attendee.relocationConfirm') {res.end(JSON.stringify({success:true,data:{saved:true}}));return;}
        if(data.task==='attendee.relocationNotify') {res.end('Notice queued');return;}
        await new Promise(r=>setTimeout(r,delay));
        if(reject){res.end(JSON.stringify({success:false,message:'Destination full'}));return;}
        res.end(JSON.stringify({success:true,data:{token:'review-token',review:{
            before:[{label:'Hall <img src=x onerror=alert(1)>',quantity:1},{label:'Area B',quantity:0}],
            after:[{quantity:0},{quantity:Number(data['capacity_areas[area:2]'])}],currency:'EUR',
            financial:{old:1000,quoted:3000,difference:2000,adjustment:0,total:3000,paid:1000,due:2000,refund:0}
        }}})); return;
    }
    res.setHeader('Content-Type','text/html; charset=utf-8');
    res.end('<!doctype html><meta charset="utf-8"><script>window.Joomla={Text:{_:k=>k}};</script>'+
        '<form id="adminForm" method="post"><input name="id" value="41"><input name="capacity_areas[area:2]" value="2"><input name="csrf-fixture" value="1">'+html+'<input name="task" value=""></form><script>'+js+'</script>');
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const origin='http://127.0.0.1:'+server.address().port;
const profile=await mkdtemp(join(tmpdir(),'jem-relocation-browser-'));
const browser=spawn(process.env.JEM_TEST_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless','--disable-gpu','--disable-background-networking','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],
    {windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try {
    const endpoint=await new Promise((r,j)=>{browser.stderr.on('data',d=>{const m=d.toString().match(/DevTools listening on (ws:\/\/\S+)/);if(m)r(m[1]);});setTimeout(()=>j(Error('Browser timeout')),15000).unref();});
    socket=new WebSocket(endpoint);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
    let seq=0;const pending=new Map();
    const send=(method,params={},sessionId)=>new Promise((r,j)=>{const id=++seq;pending.set(id,{r,j});socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));});
    socket.onmessage=e=>{const d=JSON.parse(e.data),p=pending.get(d.id);if(p){pending.delete(d.id);d.error?p.j(d.error):p.r(d.result);}};
    const {targetId}=await send('Target.createTarget',{url:'about:blank'});
    const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
    await send('Page.enable',{},sessionId);
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId);if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const wait=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,50));}throw Error('Wait failed: '+expression);};
    await send('Page.navigate',{url:origin},sessionId);
    await wait('document.readyState==="complete" && !!document.getElementById("jem-relocation-open")');
    let checks=0; const check=(a,b)=>{assert.deepEqual(a,b);checks++;};
    check(await evaluate('document.getElementById("jem-relocation-panel").hidden'),true);
    await evaluate('document.getElementById("jem-relocation-open").click();document.getElementById("jem-relocation-reason").value="Two places together";document.getElementById("jem-relocation-preview").click()');
    await wait('!document.getElementById("jem-relocation-confirm").disabled');
    check(requests.length,1);check(requests[0].task,'attendee.relocationPreview');
    check(await evaluate('document.querySelectorAll("#jem-relocation-review img").length'),0);
    check(await evaluate('document.querySelector("#jem-relocation-choice option[value=promotion]").hidden'),false);
    check(await evaluate('document.querySelector("#jem-relocation-choice option[value=agreement]").hidden'),true);
    await evaluate('document.getElementById("jem-relocation-choice").value="promotion";document.getElementById("jem-relocation-choice").dispatchEvent(new Event("change",{bubbles:true}))');
    check(await evaluate('document.getElementById("jem-relocation-confirm").disabled'),true);
    delay=200;
    await evaluate('document.getElementById("jem-relocation-preview").click();document.getElementById("jem-relocation-reason").value="Changed while reviewing";document.getElementById("jem-relocation-reason").dispatchEvent(new Event("input",{bubbles:true}))');
    await wait('!document.getElementById("jem-relocation-preview").disabled');
    check(await evaluate('document.getElementById("jem-relocation-confirm").disabled'),true);
    reject=true;
    await evaluate('document.getElementById("jem-relocation-preview").click()');
    await wait('!document.getElementById("jem-relocation-error").hidden');
    check(await evaluate('document.getElementById("jem-relocation-error").textContent'),'Destination full');
    check(await evaluate('document.getElementById("jem-relocation-confirm").disabled'),true);
    reject=false;delay=0;
    await evaluate('document.getElementById("jem-relocation-preview").click()');
    await wait('!document.getElementById("jem-relocation-confirm").disabled');
    await evaluate('document.getElementById("jem-relocation-confirm").click();document.getElementById("jem-relocation-confirm").click()');
    for(let i=0;i<50&&!requests.some(r=>r.task==='attendee.relocationConfirm');i++) await new Promise(r=>setTimeout(r,20));
    const confirmed=requests.filter(r=>r.task==='attendee.relocationConfirm');
    check(confirmed.length,1);check(confirmed[0].relocation_token,'review-token');check(confirmed[0]['csrf-fixture'],'1');
    await wait('document.readyState==="complete" && document.getElementById("jem-relocation-panel").hidden');
    await evaluate('document.getElementById("jem-relocation-notify").click()');
    for(let i=0;i<50&&!requests.some(r=>r.task==='attendee.relocationNotify');i++) await new Promise(r=>setTimeout(r,20));
    const retried=requests.filter(r=>r.task==='attendee.relocationNotify');
    check(retried.length,1);check(retried[0].id,'41');
    console.log(JSON.stringify({passed:true,checks,confirmations:confirmed.length,retries:retried.length}));
    await send('Browser.close');
} finally {
    socket?.close();browser.kill();server.close();
    // Delete only this test-created temporary browser profile, after checking its absolute scope.
    if(resolve(profile).startsWith(resolve(tmpdir())+sep+'jem-relocation-browser-'))
        await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:200});
}
