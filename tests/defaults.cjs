const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const page=await browser.newPage({viewport:{width:1800,height:1000}});
 await page.goto('http://127.0.0.1:8765/wp-login.php');
 await page.evaluate(password=>{document.getElementById('user_login').value='tncp_admin';document.getElementById('user_pass').value=password;},process.env.TNCP_TEST_PASSWORD);
 await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/');
 await page.goto('http://127.0.0.1:8765/wp-admin/admin.php?page=tn-content-planner');
 async function ready(){await page.waitForFunction(()=>document.getElementById('tncp-app').getAttribute('aria-busy')==='false');}
 async function api(route,body){return page.evaluate(async({route,body})=>{const r=await fetch(TNCP.api+route,{method:body?'POST':'GET',headers:{'X-WP-Nonce':TNCP.nonce,'Content-Type':'application/json'},body:body?JSON.stringify(body):undefined});const d=await r.json();if(!r.ok)throw Error(d.message);return d;},{route,body});}
 await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
 const original=(await api('plan/page')).plan;
 try {
  await page.getByRole('checkbox',{name:'Level 1 Local',exact:true}).check();
  await page.getByRole('checkbox',{name:'Level 2 Related',exact:true}).check();
  await page.getByRole('tab',{name:/^XP Patterns,/}).click();await ready();
  assert.equal(await page.locator('#tncp-pattern-heading').textContent(),'XP Patterns');
  await page.getByRole('button',{name:'Show Mine',exact:true}).click();assert.equal(await page.locator('#tncp-pattern-heading').textContent(),'XP Patterns');
  assert.ok((await api('plan/page')).plan.defaults[1].local);
  await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
  const offsets=await page.evaluate(()=>Array.from({length:5},(_,i)=>{
   const a=document.querySelector(`.tncp-table tbody tr td:nth-child(${i+6}) input`).getBoundingClientRect();
   return [...document.querySelectorAll(`.tncp-defaults-table tbody tr td:nth-child(${i+6}) input`)].map(n=>Math.abs(n.getBoundingClientRect().x-a.x));
  }).flat());assert.ok(offsets.every(x=>x<1),JSON.stringify(offsets));
  await page.getByRole('button',{name:'Add row',exact:true}).click();
  let row=page.locator('.tncp-table tbody tr').last();
  assert.ok(await row.locator('td:nth-child(6) input').isChecked());
  const parent=row.getByRole('combobox',{name:'Parent',exact:true});
  const value=await parent.locator('option').evaluateAll(ns=>ns.find(n=>n.value)?.value);assert.ok(value);
  await parent.selectOption(value);await page.getByRole('button',{name:'Use level defaults',exact:true}).click();
  const moved=await page.evaluate(()=>{const n=[...document.querySelectorAll('.tncp-table tbody tr')].find(n=>n.querySelector('input[aria-label="Content slug"]')?.value==='');return {local:n.querySelector('td:nth-child(6) input').checked,related:n.querySelector('td:nth-child(7) input').checked};});
  assert.equal(moved.local,false);assert.equal(moved.related,true);
  // Remove the unfinished row before testing the lock, which saves first.
  await page.reload();await ready();
  await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
  await page.getByRole('button',{name:'Lock post type',exact:true}).click();await ready();
  assert.ok(await page.getByRole('checkbox',{name:'Level 1 Local',exact:true}).isDisabled());
  await page.getByRole('button',{name:'Unlock post type',exact:true}).click();await ready();
  await page.screenshot({path:'tests/artifacts/defaults.png',fullPage:true});
  console.log('PASS: aligned level defaults, autosave persistence, root row defaults, move prompt, lock protection and plain XP Patterns heading.');
 } finally {
  const current=(await api('plan/page')).plan;
  await api('lock/page',{revision:current.revision,locked:false});
  const latest=(await api('plan/page')).plan;
  await api('save/page',{revision:latest.revision,rows:original.rows,defaults:original.defaults||{}});
  await browser.close();
 }
})().catch(e=>{console.error(e);process.exit(1);});
