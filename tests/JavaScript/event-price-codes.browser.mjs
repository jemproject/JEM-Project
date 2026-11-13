import {readFile, mkdtemp, rm} from 'node:fs/promises';
import {spawn} from 'node:child_process';
import {tmpdir} from 'node:os';
import {join, resolve, dirname, basename} from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';

// Exercise actual Joomla subform and JEM add/copy handlers in a disposable document.
// No installed application login, data mutation, mail or HTTP requests.
const root=resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const joomla=process.env.JEM_TEST_JOOMLA_ROOT || 'C:/xampp/htdocs/jl602';
const source=await readFile(join(root, 'admin/views/event/tmpl/edit_pricing.php'),'utf8');
/** @returns {string} Actual JEM handlers between the supplied source boundaries. */
const extract=(start,end)=>source.slice(source.indexOf(start),source.indexOf(end,source.indexOf(start)));
const rows=extract('    const priceSubform =','    const updateMode =');
const decorate=extract('    const fieldKey =','    const decimalMinorUnits =');
const copy=extract('    const copyRow =',"    root.addEventListener('click'");
const handler=extract("    root.addEventListener('subform-row-add'", "    root.addEventListener('subform-row-remove'");
/** @returns {string} A minimal admission subform row using Joomla's actual field naming. */
const row=(index,code=null)=>`<div class="subform-repeatable-group" data-group="event_prices${index}" data-base-name="event_prices">
 <div class="btn-toolbar"><div class="btn-group"><button type="button" class="add">+</button><button type="button" class="remove">-</button></div></div>
 ${[['id',code===null?'0':String(index+1)],['code',code || ''],['name',code===null?'':'Adult'],['amount',code===null?'0.00':'12.00'],['tax_rate_id','1']].map(([key,value])=>`<div class="control-group"><input name="jform[event_prices][event_prices${index}][${key}]" value="${value}"></div>`).join('')}
 </div>`;
/** @returns {string} Disposable editor; stored codes come from the supplied fixture item. */
const html=codes=>`<style>.jem-price-advanced-field{display:none}</style><select id="jform_pricing_mode"><option>single</option><option>multiple</option></select><input id="jform_default_tax_rate_id" value="1"><div class="jem-event-pricing"><div class="jem-event-price-options"><joomla-field-subform name="jform[event_prices]" repeatable-element=".subform-repeatable-group" button-add=".add" button-remove=".remove" minimum="0" maximum="100">${codes.map((code,index)=>row(index,code)).join('')}<template class="subform-repeatable-template-section">${row('X')}</template></joomla-field-subform></div></div>`;
const profile=await mkdtemp(join(tmpdir(),'jem2523-analysis-'));
const browser=spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--disable-background-networking','--disable-extensions','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
const closed=new Promise(resolve=>{browser.once('exit',resolve);browser.once('error',resolve);});
let socket;
try {
 const endpoint=await new Promise((resolve,reject)=>{
   const timer=setTimeout(()=>reject(Error('Browser timeout')),15000);
   browser.stderr.on('data',chunk=>{const match=chunk.toString().match(/DevTools listening on (ws:\/\/\S+)/);if(match){clearTimeout(timer);resolve(match[1]);}});
   browser.once('error',reject);
 });
 socket=new WebSocket(endpoint);await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
 let sequence=0;const pending=new Map();
 socket.onmessage=event=>{const data=JSON.parse(event.data);const call=pending.get(data.id);if(call){pending.delete(data.id);clearTimeout(call.timer);data.error?call.reject(Error(JSON.stringify(data.error))):call.resolve(data.result);}};
 /** @returns {Promise<object>} One bounded Chrome protocol response. */
 const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++sequence;const timer=setTimeout(()=>reject(Error('CDP '+method)),12000);pending.set(id,{resolve,reject,timer});socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));});
 const results=[];
 const component=await readFile(join(joomla,'media/system/js/fields/joomla-field-subform.js'),'utf8');
 for(const scenario of ['add-copy','removed-stored','maximum-length','normalised-collision']) {
     const initialCodes=scenario==='removed-stored'?['admission','admission-2']:
         scenario==='maximum-length'?['a'.repeat(64)]:['adult'];
     const {targetId}=await send('Target.createTarget',{url:'about:blank'});
     const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
     /** @returns {Promise<unknown>} Execute the actual extracted handlers in the fixture document. */
     const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId);if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
     await evaluate(`document.body.innerHTML=${JSON.stringify(html(initialCodes))}`);
     await evaluate(component);
     // Only the PHP-provided item data and unrelated tax/capacity-preview dependencies are fixtures.
     const rowScript=rows.replace(/<\?php[\s\S]*?\?>/,JSON.stringify(initialCodes));
     await evaluate(`(()=>{const root=document.querySelector('.jem-event-pricing');const mode=document.getElementById('jform_pricing_mode');const copyText='Copy',copySuffix=' (copy)';const filterTaxRates=()=>{},populateCapacityOptions=()=>{},updatePreviews=()=>{};${rowScript}\n${decorate}\n${copy}\n${handler}\ndecorateRows();window.probe={rows:priceRows,copy:copyRow,normalise:normalisePriceCode};return true;})()`);
     const result=await evaluate(`(()=>{
       const p=window.probe,scenario=${JSON.stringify(scenario)};
       const code=r=>r.querySelector('[name$="[code]"]').value;
       const first=p.rows()[0];
       let hidden=true;
       if(scenario==='add-copy') {
         first.querySelector('.add').click();
         const added=p.rows()[1];
         added.querySelector('[name$="[name]"]').value='Child Ticket';
         added.querySelector('[name$="[amount]"]').value='7.50';
         hidden=getComputedStyle(added.querySelector('[name$="[code]"]').closest('.control-group')).display==='none';
         p.copy(first);first.querySelector('.add').click();
       } else if(scenario==='removed-stored') {
         first.querySelector('.remove').click();p.rows()[0].querySelector('.add').click();
       } else if(scenario==='maximum-length') {
         p.copy(first);p.copy(first);
       } else {
         first.querySelector('.add').click();
         p.rows()[1].querySelector('[name$="[code]"]').value=' ADMISSION! ';
         first.querySelector('.add').click();
       }
       return {hidden,codes:p.rows().map(code),normalised:p.rows().map(r=>p.normalise(code(r))),
         saved:p.rows().filter(r=>Number(r.querySelector('[name$="[id]"]').value)>0).map(r=>({code:code(r),readOnly:r.querySelector('[name$="[code]"]').readOnly}))};
     })()`);
     assert.equal(result.hidden,true);
     assert.equal(new Set(result.normalised).size,result.codes.length);
     assert.ok(result.normalised.every(code=>code.length>0&&code.length<=64));
     assert.ok(result.saved.every(row=>row.readOnly&&initialCodes.includes(row.code)));
     if(scenario==='add-copy') assert.deepEqual(result.codes.sort(),['adult','adult-copy','admission','admission-2'].sort());
     if(scenario==='removed-stored') assert.deepEqual(result.codes.sort(),['admission-2','admission-3']);
     if(scenario==='maximum-length') assert.deepEqual(result.codes.sort(),['a'.repeat(64),'a'.repeat(59)+'-copy','a'.repeat(57)+'-copy-2'].sort());
     if(scenario==='normalised-collision') assert.deepEqual(result.normalised.sort(),['adult','admission','admission-2'].sort());
     results.push({scenario,...result});
     await send('Target.closeTarget',{targetId});
 }
 console.log(JSON.stringify({joomla,passed:results.length,results},null,2));
 await send('Browser.close');await closed;
} finally {
 if(socket)socket.close();
 if(browser.exitCode===null){browser.kill();await closed;}
 const cleanupTarget=resolve(profile);
 if (dirname(cleanupTarget)!==resolve(tmpdir()) || !basename(cleanupTarget).startsWith('jem2523-analysis-')) throw Error('Unsafe temporary profile path');
 await rm(cleanupTarget,{recursive:true,force:true});
}
