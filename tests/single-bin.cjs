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
 let target;
 try {
  await ready();
  target=await api('/index.php?rest_route=/wp/v2/pages','POST',{title:'Single bin '+token,status:'draft'});
  await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
  const data=await api('plan/page'),linked=data.plan.rows.find(row=>row.post_id===target.id);
  await page.getByRole('button',{name:'Add row',exact:true}).click();
  await page.locator('[data-title]').last().fill('Unsaved bin companion '+token);await page.locator('[data-title]').last().press('Tab');
  const remove=()=>page.locator(`[data-row="${linked.id}"] .tncp-remove`).click();
  const trash=()=>page.getByRole('button',{name:'Remove row & move post to bin',exact:true});
  await remove();assert.equal(await trash().isDisabled(),false);
  await page.getByRole('button',{name:'Cancel',exact:true}).click();
  assert.equal((await api('/index.php?rest_route=/wp/v2/pages/'+target.id+'&context=edit')).status,'draft');
  const fail=route=>route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({message:'Simulated save failure'})});
  await page.route('**/tncp/v1/save/page',fail);
  await remove();await trash().click();await page.getByText('Simulated save failure',{exact:true}).waitFor();await ready();
  assert.equal((await api('/index.php?rest_route=/wp/v2/pages/'+target.id+'&context=edit')).status,'draft');
  assert.equal(await page.locator(`[data-row="${linked.id}"]`).count(),1);
  await page.unroute('**/tncp/v1/save/page',fail);
  const writes=[];page.on('request',request=>{if(request.method()==='POST')writes.push(request.url());});
  await remove();await trash().click();await page.getByText('Plan row removed and linked post moved to the WordPress bin.',{exact:true}).waitFor();await ready();
  assert.equal((await api('/index.php?rest_route=/wp/v2/pages/'+target.id+'&context=edit')).status,'trash');
  const saved=await api('plan/page');assert.equal(saved.plan.rows.some(row=>row.post_id===target.id),false);
  assert.equal(saved.plan.rows.some(row=>row.title==='Unsaved bin companion '+token),true);
  assert.ok(writes.findIndex(url=>url.includes('/save/page'))<writes.findIndex(url=>url.includes('/bin/page')));
  console.log('PASS: dirty-plan bin enabled, Cancel preserves post, save failure prevents deletion, retry saves edits before binning and removes the row.');
 } finally {
  const data=await api('plan/page');await api('save/page','POST',{revision:data.plan.revision,rows:data.plan.rows.filter(row=>row.post_id!==target?.id&&row.title!=='Unsaved bin companion '+token)});
  if(target)await api('/index.php?rest_route=/wp/v2/pages/'+target.id+'&force=true','DELETE');
  await browser.close();
 }
})().catch(error=>{console.error(error);process.exit(1)});
