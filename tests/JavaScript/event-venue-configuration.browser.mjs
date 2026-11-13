import {mkdtemp, rm} from 'node:fs/promises';
import {spawn, execFileSync} from 'node:child_process';
import {tmpdir} from 'node:os';
import {join, resolve, dirname, basename} from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';

// Real rendered JEM panels and Joomla form fields, no installed-site changes.
const root=resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const joomla=process.env.JEM_TEST_JOOMLA_ROOT || 'C:/xampp/htdocs/jl602';
const profile=await mkdtemp(join(tmpdir(),'jem2524-analysis-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
 '--headless','--disable-gpu','--disable-background-networking','--disable-extensions','--no-first-run',
 '--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
const closed=new Promise(resolve=>{browser.once('exit',resolve);browser.once('error',resolve);});
let socket;
try {
 const endpoint=await new Promise((resolve,reject)=>{
  const timer=setTimeout(()=>reject(Error('Chrome timeout')),15000);
  browser.stderr.on('data',chunk=>{const match=chunk.toString().match(/DevTools listening on (ws:\/\/\S+)/);if(match){clearTimeout(timer);resolve(match[1]);}});
  browser.once('error',reject);
 });
 socket=new WebSocket(endpoint);await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
 let sequence=0;const pending=new Map();
 socket.onmessage=event=>{const data=JSON.parse(event.data);const call=pending.get(data.id);if(call){pending.delete(data.id);clearTimeout(call.timer);data.error?call.reject(Error(JSON.stringify(data.error))):call.resolve(data.result);}};
 /** @returns {Promise<object>} Bounded Chrome protocol response. */
 const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++sequence;const timer=setTimeout(()=>reject(Error('CDP '+method)),12000);pending.set(id,{resolve,reject,timer});socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));});
 const results=[];

 for(const scenario of ['pricing','capacity','new','snapshot','none','custom','venue-change','stale-request','capacity-only']) {
   const fixture=['pricing','capacity','custom','venue-change','stale-request'].includes(scenario)?'saved-event':scenario;
   const html=execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe',[
     '-d','extension=sqlite3',join(root,'tests/JavaScript/fixtures/event-venue-configuration.php'),joomla,fixture
   ],{encoding:'utf8'});
   const {targetId}=await send('Target.createTarget',{url:'about:blank'});
   const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
   /** @returns {Promise<unknown>} Result of a real browser operation on rendered JEM panels. */
   const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId);if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
   await evaluate('window.fixtureErrors=[]; window.addEventListener("error",event=>fixtureErrors.push(event.message));');
   await evaluate(`document.open();document.write(${JSON.stringify(html)});document.close();`);
   await evaluate('new Promise(resolve=>setTimeout(resolve,50))');
   await evaluate(`window.readState=()=>{
     const data=new FormData(document.getElementById('adminForm'));
     const fields={};
     for(const key of ['venue_configuration_key','venue_assignment_ids','reload_venue_capacity','venue_profile_id','venue_profile_revision','venue_snapshot']) fields[key]=data.getAll('jform['+key+']');
     const duplicates=Array.from(new Set(Array.from(document.querySelectorAll('[id]')).map(e=>e.id)))
       .map(id=>({id,count:document.querySelectorAll('[id="'+id+'"]').length})).filter(x=>x.count>1);
     return {fields,duplicates,errors:fixtureErrors,selectors:Array.from(document.querySelectorAll('select[id$="-configuration-select"]')).map(s=>s.value),
       profiles:Array.from(document.querySelectorAll('select[id$="-custom-profile"]')).map(s=>s.value),
       pool:document.querySelector('[name$="[capacity_pool_id]"]')?.value,
       maxPlaces:document.getElementById('jform_maxplaces').value,
       pricingHidden:document.querySelector('.jem-event-pricing .jem-event-venue-configuration')?.hidden};
   };window.change=(selector,value)=>{const field=document.querySelector(selector);field.value=value;field.dispatchEvent(new Event('change',{bubbles:true}));};`);
   const before=await evaluate('readState()');
   assert.deepEqual(before.duplicates,[],scenario+' duplicate IDs');
   assert.deepEqual(before.errors,[]);
   for(const key of ['venue_configuration_key','venue_assignment_ids','venue_profile_id','venue_profile_revision','venue_snapshot']) assert.equal(before.fields[key].length,1,key);
   assert.deepEqual(before.fields.reload_venue_capacity,[],scenario+' initialisation must not request reload');
   if(scenario==='none') {
     assert.equal(before.fields.venue_assignment_ids[0],'[]');
     assert.equal(before.fields.venue_configuration_key[0],'');
     assert.equal(before.pricingHidden,true);
     await evaluate(`change('#jform_venue_allocation_mode','profile')`);
   } else if(scenario==='custom') {
     await evaluate(`change('#jem-event-price-venue-configuration-select','custom');change('#jem-event-price-venue-custom-profile','4');document.querySelector('.jem-event-pricing .jem-event-venue-custom-grid input[value="13"]').click();`);
     const custom=await evaluate('readState()');
     assert.deepEqual(custom.profiles,['4','4']);
     assert.deepEqual(custom.selectors,['custom','custom']);
     assert.equal(custom.fields.venue_assignment_ids[0],'[13]');
     assert.equal(custom.pool,'source:standing');
     await evaluate(`change('#jem-event-venue-custom-profile','3');document.querySelector('.jem-event-capacity .jem-event-venue-custom-grid input[value="11"]').click();`);
   } else if(['venue-change','stale-request'].includes(scenario)) {
     await evaluate(`window.pendingRequests=[];window.fetch=()=>new Promise(resolve=>pendingRequests.push(resolve));
       window.payload={venue_exists:true,capacity_ready:true,country_code:'ES',configuration_custom_required:false,
       configuration_profiles:[{profile_id:8,profile_name:'New'}],
       configuration_assignments:[{id:21,profile_id:8,space_name:'New hall',layout_name:'Seats',capacity:70,layout_id:81}],
       configuration_options:[{key:'profile:8:selection:21',label:'New hall',assignment_ids:[21],capacity:70,pool_candidates:[{code:'new',name:'New hall',capacity:70,venue_layout_id:81}]}]};
       change('#jform_locid_id','2');`);
     assert.equal(await evaluate('pendingRequests.length'),1,'One request owned by Capacity');
     if(scenario==='stale-request') {
       await evaluate(`change('#jform_locid_id','3');pendingRequests[1]({ok:true,json:async()=>({success:true,data:payload})});`);
       await evaluate('new Promise(resolve=>setTimeout(resolve,20))');
       await evaluate(`pendingRequests[0]({ok:true,json:async()=>({success:true,data:{...payload,configuration_options:[]}})});`);
     } else {
       await evaluate(`pendingRequests[0]({ok:true,json:async()=>({success:true,data:payload})});`);
     }
   } else {
     if(scenario==='snapshot') assert.equal(before.fields.venue_assignment_ids[0],'[99]');
     const selector=['capacity','capacity-only'].includes(scenario)?'#jem-event-venue-configuration-select':'#jem-event-price-venue-configuration-select';
     await evaluate(`change(${JSON.stringify(selector)},'profile:3:selection:12');`);
   }
   // Yield to MutationObserver: rebuilding unchanged area options must not loop indefinitely.
   await evaluate('new Promise(resolve=>setTimeout(resolve,50))');
   const after=await evaluate('readState()');
   assert.deepEqual(after.errors,[]);
   assert.deepEqual(after.duplicates,[]);
   const ids=scenario==='none'?'[11,12]':scenario==='custom'?'[11]':
     ['venue-change','stale-request'].includes(scenario)?'[21]':'[12]';
   assert.deepEqual(after.fields.venue_assignment_ids,[ids],scenario+' canonical submitted assignment');
   assert.equal(new Set(after.selectors).size,1,scenario+' synchronised selectors');
   assert.deepEqual(after.fields.reload_venue_capacity,scenario==='new'?[]:['1']);
   assert.equal(after.maxPlaces,scenario==='new'?'50':'100',scenario+' event capacity');
   if(['pricing','capacity','new','snapshot'].includes(scenario)) assert.equal(after.pool,'source:balcony');
   if(scenario==='custom') assert.equal(after.pool,'source:floor');
   if(['venue-change','stale-request'].includes(scenario)) assert.equal(after.pool,'source:new');
   results.push({scenario,passed:true,assignments:after.fields.venue_assignment_ids[0]});
   await send('Target.closeTarget',{targetId});
 }
 console.log(JSON.stringify({joomla,passed:results.length,results},null,2));
 await send('Browser.close');await closed;
} finally {
 if(socket)socket.close();
 if(browser.exitCode===null){browser.kill();await closed;}
 const target=resolve(profile);
 if(dirname(target)!==resolve(tmpdir())||!basename(target).startsWith('jem2524-analysis-')) throw Error('Unsafe temporary path');
 await rm(target,{recursive:true,force:true});
}
