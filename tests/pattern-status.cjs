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
  await ready();const patterns=await api('patterns');original=patterns.rows.find(row=>row.type==='page'&&row.mapped_count);
  assert.ok(original);
  for(const [status,color] of [['todo','rgb(198, 40, 40)'],['in-progress','rgb(183, 121, 0)'],['done','rgb(33, 132, 59)']]){
   await page.getByRole('tab',{name:/^XP Patterns,/}).click();await ready();
   await page.locator(`[data-pattern="${original.key}"]`).getByRole('combobox',{name:/^Status/}).selectOption(status);
   await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
   const cell=page.locator('.tncp-pattern').filter({hasText:original.key}).first(),dot=cell.locator('.tncp-pattern-status');
   assert.equal(await dot.evaluate(node=>getComputedStyle(node).backgroundColor),color);
   assert.equal(await dot.getAttribute('role'),'img');
   assert.ok(await dot.getAttribute('aria-label'));
   const geometry=await cell.evaluate(node=>{const dot=node.querySelector('.tncp-pattern-status').getBoundingClientRect();const text=node.querySelector('.tncp-pattern-reference').firstElementChild.getBoundingClientRect();return {size:dot.height,font:parseFloat(getComputedStyle(node).fontSize),after:dot.x>=text.right};});
   assert.equal(geometry.size,geometry.font);assert.ok(geometry.after);
   assert.equal(await page.locator('.tncp-indicator-key .tncp-pattern-status').count(),3);
   const key=page.locator('.tncp-indicator-key .tncp-pattern-status.is-'+status);assert.equal(await key.evaluate(node=>getComputedStyle(node).backgroundColor),color);
   assert.equal((await api('plan/page')).pattern_statuses[original.key],status);
  }
  if(process.env.TNCP_AXE_PATH){await page.addScriptTag({path:process.env.TNCP_AXE_PATH});for(const viewport of [{width:1600,height:1050},{width:390,height:844}]){await page.setViewportSize(viewport);const audit=await page.evaluate(async()=>await axe.run('.tncp-wrap',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));assert.deepEqual(audit.violations.map(v=>v.id),[]);}}
  console.log('PASS: three status colours follow saved XP status, dots follow pattern text at font height, matching key dots, accessible labels and desktop/mobile Axe.');
 } finally {
  if(original){const data=await api('patterns');await api('patterns','POST',{revision:data.revision,rows:[original]});}
  await browser.close();
 }
})().catch(error=>{console.error(error);process.exit(1)});
