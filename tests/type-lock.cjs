const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const page=await browser.newPage({viewport:{width:1600,height:1050}});
 await page.goto('http://127.0.0.1:8765/wp-login.php');
 await page.evaluate(password=>{document.getElementById('user_login').value='tncp_admin';document.getElementById('user_pass').value=password;},process.env.TNCP_TEST_PASSWORD);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/');
 await page.goto('http://127.0.0.1:8765/wp-admin/admin.php?page=tn-content-planner');await page.getByRole('tab',{name:/^Pages,/}).click();await page.locator('tbody tr').first().waitFor();
 const assert=require('node:assert/strict');
 const ids=[];const token=String(Date.now()), unlinkedId='bin-unlinked-'+token;
 async function api(route,method='GET',body){return page.evaluate(async({route,method,body})=>{const r=await fetch(route.startsWith('/')?route:TNCP.api+route,{method,headers:{'X-WP-Nonce':TNCP.nonce,'Content-Type':'application/json'},body:body?JSON.stringify(body):undefined});const data=await r.json();if(!r.ok)throw Error(data.message);return data;},{route,method,body});}
 async function ready(){await page.waitForFunction(()=>document.getElementById('tncp-app').getAttribute('aria-busy')==='false');}
 let addedId;
 try {
  await ready();
  const lock=page.getByRole('button',{name:'Lock post type',exact:true});
  assert.ok(await lock.locator('.dashicons-unlock').count());
  await page.getByRole('button',{name:'Add row',exact:true}).click();
  await page.locator('[data-title]').last().fill('Lock fixture '+token);await page.locator('[data-title]').last().press('Tab');
  addedId=await page.locator('tbody tr').last().getAttribute('data-row');
  const fail=route=>route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({message:'Simulated save failure'})});
  await page.route('**/tncp/v1/save/page',fail);await lock.click();await page.getByText('Simulated save failure',{exact:true}).waitFor();await ready();
  assert.equal((await api('plan/page')).settings.locked,false);
  await page.unroute('**/tncp/v1/save/page',fail);
  await lock.click();await page.getByText('Post type locked.',{exact:true}).waitFor();await ready();
  const data=await api('plan/page');assert.equal(data.settings.locked,true);assert.ok(data.plan.rows.some(row=>row.id===addedId));
  assert.equal(await page.locator('#tncp-panel button:not(.tncp-lock):enabled, #tncp-panel input:enabled, #tncp-panel select:enabled').count(),0);
  const unlock=page.getByRole('button',{name:'Unlock post type',exact:true});assert.equal(await unlock.getAttribute('aria-pressed'),'true');
  assert.equal(await unlock.evaluate(n=>getComputedStyle(n).color),'rgb(217, 91, 0)');
  const codes=await page.evaluate(async revision=>Promise.all(['save','change','bin','apply','resolve','refresh'].map(async action=>(await fetch(TNCP.api+action+'/page',{method:'POST',headers:{'X-WP-Nonce':TNCP.nonce,'Content-Type':'application/json'},body:JSON.stringify({revision})})).status)),data.plan.revision);
  assert.deepEqual(codes,[423,423,423,423,423,423]);
  await page.getByRole('tab',{name:/^Posts,/}).click();await ready();assert.equal(await page.getByRole('button',{name:'Add row',exact:true}).isEnabled(),true);
  await page.getByRole('tab',{name:/^Pages,/}).click();await ready();assert.equal(await page.getByRole('button',{name:'Add row',exact:true}).isEnabled(),false);
  await page.reload();await ready();await page.getByRole('tab',{name:/^Pages,/}).click();await ready();assert.equal(await unlock.count(),1);
  if(process.env.TNCP_AXE_PATH){await page.addScriptTag({path:process.env.TNCP_AXE_PATH});const audit=await page.evaluate(async()=>await axe.run('.tncp-wrap',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));assert.deepEqual(audit.violations.map(v=>v.id),[]);}
  await page.screenshot({path:'tests/artifacts/post-type-locked.png',fullPage:true});
  await unlock.focus();await page.keyboard.press('Enter');await page.getByText('Post type unlocked.',{exact:true}).waitFor();await ready();assert.equal(await page.getByRole('button',{name:'Add row',exact:true}).isEnabled(),true);
  console.log('PASS: grey/unlocked default, orange lock, save-before-lock and failed-save recovery, all editing controls disabled, server write rejection, persistence, per-type scope, keyboard unlock and Axe.');
 } finally {
  const data=await api('plan/page');await api('lock/page','POST',{revision:data.plan.revision,locked:false});
  if(addedId)await api('save/page','POST',{revision:data.plan.revision,rows:data.plan.rows.filter(row=>row.id!==addedId)});
  await browser.close();
 }
})().catch(error=>{console.error(error);process.exit(1)});
