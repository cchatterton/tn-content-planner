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
 const bulk=page.locator('.tncp-footer').getByRole('button',{name:'Send selected to bin',exact:true});
 try {
  await ready();assert.equal(await bulk.isDisabled(),true);assert.equal(await page.getByRole('button',{name:'Map Selected',exact:true}).count(),1);
  const parent=await api('/index.php?rest_route=/wp/v2/pages','POST',{title:'Bin parent '+token,status:'draft'});ids.push(parent.id);
  const child=await api('/index.php?rest_route=/wp/v2/pages','POST',{title:'Bin child '+token,status:'draft',parent:parent.id});ids.push(child.id);
  await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
  let data=await api('plan/page');
  await api('save/page','POST',{revision:data.plan.revision,rows:[...data.plan.rows,{id:unlinkedId,title:'Uncreated bin fixture',slug:'uncreated-bin-'+token,parent:'',template:'single',flags:{},post_id:0,confirmed:{}}]});
  await page.getByRole('tab',{name:/^Pages,/}).click();await ready();data=await api('plan/page');
  const parentRow=data.plan.rows.find(r=>r.post_id===parent.id),childRow=data.plan.rows.find(r=>r.post_id===child.id);
  const check=id=>page.locator(`[data-row="${id}"] td:first-child input`);
  await check(parentRow.id).check();await bulk.click();await page.getByText('Select the linked child rows too, or move the remaining child rows before binning their parent.',{exact:true}).waitFor();
  assert.equal(await page.locator('dialog[open]').count(),0);
  await check(childRow.id).check();await check(unlinkedId).check();await bulk.click();
  await page.getByText('1 selected rows have no linked post and will remain in the plan.',{exact:true}).waitFor();
  if(process.env.TNCP_AXE_PATH){await page.addScriptTag({path:process.env.TNCP_AXE_PATH});const audit=await page.evaluate(async()=>await axe.run('.tncp-dialog',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));assert.deepEqual(audit.violations.map(v=>v.id),[]);}
  await page.getByRole('button',{name:'Cancel',exact:true}).click();assert.equal((await api('/index.php?rest_route=/wp/v2/pages/'+parent.id+'&context=edit')).status,'draft');
  let calls=0;const order=[];
  const failSecond=async route=>{if(route.request().method()==='POST'){calls++;order.push(route.request().postDataJSON().row_id);if(calls===2){await route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({message:'Simulated bin failure'})});return;}}await route.continue();};
  await page.route('**/tncp/v1/bin/page',failSecond);
  await bulk.click();await page.locator('dialog').getByRole('button',{name:'Send selected to bin',exact:true}).click();
  await page.getByText(/1 of 2 posts moved to the bin.*Simulated bin failure/).waitFor();await ready();
  assert.deepEqual(order,[childRow.id,parentRow.id]);
  assert.equal((await api('/index.php?rest_route=/wp/v2/pages/'+child.id+'&context=edit')).status,'trash');
  assert.equal((await api('/index.php?rest_route=/wp/v2/pages/'+parent.id+'&context=edit')).status,'draft');
  assert.equal(await check(parentRow.id).isChecked(),true);assert.equal(await check(unlinkedId).isChecked(),true);
  await page.unroute('**/tncp/v1/bin/page',failSecond);
  await bulk.click();await page.locator('dialog').getByRole('button',{name:'Send selected to bin',exact:true}).click();await page.getByText('1 posts moved to the bin. Unlinked rows remain in the plan.',{exact:true}).waitFor();await ready();
  assert.equal((await api('/index.php?rest_route=/wp/v2/pages/'+parent.id+'&context=edit')).status,'trash');
  assert.equal(await check(unlinkedId).isChecked(),true);assert.equal(await bulk.isDisabled(),true);
  data=await api('plan/page');assert.equal(data.plan.rows.some(r=>ids.includes(r.post_id)),false);
  console.log('PASS: bulk bin confirmation/cancel, child protection and ordering, mixed unlinked rows, partial failure and retry, persisted row removal and selection state.');
 } finally {
  const data=await api('plan/page');await api('save/page','POST',{revision:data.plan.revision,rows:data.plan.rows.filter(r=>!ids.includes(r.post_id)&&r.id!==unlinkedId)});
  for(const id of ids.reverse())await api('/index.php?rest_route=/wp/v2/pages/'+id+'&force=true','DELETE');
  await browser.close();
 }
})().catch(error=>{console.error(error);process.exit(1)});
