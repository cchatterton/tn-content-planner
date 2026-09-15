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
 let original;
 try {
  await ready();
  const patterns=await api('patterns');original=patterns.rows.find(row=>row.type==='page');assert.ok(original);
  const filter=page.getByRole('combobox',{name:'Filter by XP Pattern',exact:true});
  const data=await api('plan/page');
  const keys=[...new Set(data.plan.rows.map(row=>row.pattern))].sort();
  assert.deepEqual((await filter.locator('option').evaluateAll(nodes=>nodes.map(n=>n.value))).filter(Boolean).sort(),keys);
  const toolbar=page.locator('.tncp-plan-tools');const boxes=await toolbar.evaluate(n=>{const a=n.querySelector('button').getBoundingClientRect(),b=n.querySelector('select').getBoundingClientRect();return {same:Math.abs(a.y-b.y)<3,right:b.x>a.right};});assert.ok(boxes.same&&boxes.right);
  await page.getByRole('tab',{name:/^XP Patterns,/}).click();await ready();
  const row=page.locator(`[data-pattern="${original.key}"]`);const changed='Filter navigation '+token;
  await row.getByRole('textbox').fill(changed);
  const fail=route=>route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({message:'Simulated save failure'})});
  await page.route('**/tncp/v1/patterns',fail);
  await row.locator('th a').click();await page.getByText('Simulated save failure',{exact:true}).waitFor();await ready();
  assert.equal(await page.getByRole('tab',{name:/^XP Patterns,/}).getAttribute('aria-selected'),'true');
  await page.unroute('**/tncp/v1/patterns',fail);
  await row.locator('th a').click();await ready();assert.equal(await filter.inputValue(),original.key);
  const displayed=await page.locator('.tncp-table tbody tr').count();assert.ok(displayed>0);
  assert.ok((await page.locator('.tncp-pattern-reference').allTextContents()).every(text=>text===original.key));
  assert.equal((await api('patterns')).rows.find(r=>r.key===original.key).description,changed);
  await page.getByRole('checkbox',{name:'Select all rows',exact:true}).check();
  assert.equal(await page.locator('.tncp-table tbody td:first-child input:checked').count(),await page.locator('.tncp-table tbody td:first-child input:enabled').count());
  await filter.selectOption('');assert.equal(await page.locator('.tncp-table tbody td:first-child input:checked').count(),0);
  assert.equal(await page.locator('.tncp-table tbody tr').count(),(await api('plan/page')).plan.rows.length);
  await page.getByRole('button',{name:'Lock post type',exact:true}).click();await ready();assert.ok(await filter.isEnabled());await filter.selectOption(original.key);
  assert.equal(await page.getByRole('button',{name:'Add row',exact:true}).isEnabled(),false);
  await page.getByRole('button',{name:'Unlock post type',exact:true}).click();await ready();
  await page.screenshot({path:'tests/artifacts/pattern-filter.png',fullPage:true});
  console.log('PASS: right-aligned unique pattern filter, preset navigation, auto-save/failure preservation, visible-only selection, clearing filter and read-only locked filtering.');
 } finally {
  const data=await api('plan/page');await api('lock/page','POST',{revision:data.plan.revision,locked:false});
  if(original){const patterns=await api('patterns');await api('patterns','POST',{revision:patterns.revision,rows:[original]});}
  await browser.close();
 }
})().catch(error=>{console.error(error);process.exit(1)});
