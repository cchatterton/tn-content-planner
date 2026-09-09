const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const page=await browser.newPage({viewport:{width:1600,height:1100}});
 await page.goto('http://127.0.0.1:8765/wp-login.php');
 await page.evaluate(password=>{document.getElementById('user_login').value='tncp_admin';document.getElementById('user_pass').value=password;},process.env.TNCP_TEST_PASSWORD);
 await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/');
 await page.goto('http://127.0.0.1:8765/wp-admin/admin.php?page=tn-content-planner');
 async function ready(){await page.waitForFunction(()=>document.getElementById('tncp-app').getAttribute('aria-busy')==='false');}
 await ready();await page.getByRole('tab',{name:/^XP Patterns,/}).click();await ready();
 const data=await page.evaluate(async()=>await(await fetch(TNCP.api+'patterns',{headers:{'X-WP-Nonce':TNCP.nonce}})).json());
 for(const row of data.rows){
  const tr=page.locator(`[data-pattern="${row.key}"]`);
  assert.equal(await tr.locator('td').first().innerText(),`${row.mapped_count}/${row.count}`);
  const ids=await tr.getByRole('combobox',{name:/^Example post/}).locator('option').evaluateAll(nodes=>nodes.filter(n=>n.value!=='0'&&!n.textContent.includes('unavailable')).map(n=>Number(n.value)));
  assert.deepEqual(ids.sort((a,b)=>a-b),row.example_ids.sort((a,b)=>a-b));
 }
 const widths=await page.locator('.tncp-patterns-table thead th').evaluateAll(nodes=>nodes.map(n=>n.getBoundingClientRect().width));
 assert.ok(widths.every((w,i)=>i===2||widths[2]>w),'Description must be the widest column');
 const chosen=data.rows.find(row=>row.type==='page'&&row.example_ids.length);
 assert.ok(chosen,'A mapped page pattern fixture is required');
 const tr=page.locator(`[data-pattern="${chosen.key}"]`),example=chosen.example_ids[0];
 await tr.getByRole('combobox',{name:/^Example post/}).selectOption(String(example));
 await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
 const links=page.locator('.tncp-pattern a').filter({hasText:chosen.key});
 assert.ok(await links.count()>0);assert.equal(await links.first().getAttribute('target'),'_blank');
 assert.match(await links.first().getAttribute('href'),new RegExp(`post=${example}&action=edit`));
 await page.getByRole('button',{name:'Add row',exact:true}).click();
 const title='Optional slug '+Date.now();await page.locator('[data-title]').last().fill(title);await page.locator('[data-title]').last().press('Tab');
 let blank=page.locator('tbody tr').last();const id=await blank.getAttribute('data-row');
 assert.ok(await blank.getByRole('checkbox').first().isDisabled());
 await page.getByRole('tab',{name:/^XP Patterns,/}).click();await ready();await page.getByRole('tab',{name:/^Pages,/}).click();await ready();
 blank=page.locator(`[data-row="${id}"]`);assert.ok(await blank.getByRole('checkbox').first().isDisabled());
 await page.getByRole('checkbox',{name:'Select all rows',exact:true}).check();assert.equal(await blank.getByRole('checkbox').first().isChecked(),false);
 await page.getByRole('checkbox',{name:'Select all rows',exact:true}).uncheck();
 await blank.getByRole('textbox',{name:'Content slug',exact:true}).fill('optional-slug-'+Date.now());await blank.getByRole('textbox',{name:'Content slug',exact:true}).press('Tab');
 await blank.locator('td:first-child input:enabled').waitFor();
 assert.equal(await blank.getByRole('checkbox').first().isDisabled(),false);await blank.getByRole('checkbox').first().check();
 await blank.getByRole('textbox',{name:'Content slug',exact:true}).fill('');await blank.getByRole('textbox',{name:'Content slug',exact:true}).press('Tab');
 await blank.locator('td:first-child input:disabled').waitFor();
 assert.equal(await blank.getByRole('checkbox').first().isChecked(),false);assert.ok(await blank.getByRole('checkbox').first().isDisabled());
 await page.getByRole('button',{name:'Save plan',exact:true}).click();await ready();
 await page.screenshot({path:'tests/artifacts/pattern-links-optional-slug.png',fullPage:true});
 await page.evaluate(async({id,key,original})=>{
  const h={'X-WP-Nonce':TNCP.nonce,'Content-Type':'application/json'};
  const plan=await(await fetch(TNCP.api+'plan/page',{headers:h})).json();
  const r=await fetch(TNCP.api+'save/page',{method:'POST',headers:h,body:JSON.stringify({revision:plan.plan.revision,rows:plan.plan.rows.filter(r=>r.id!==id)})});if(!r.ok)throw Error('Cleanup failed');
  const patterns=await(await fetch(TNCP.api+'patterns',{headers:h})).json();const row=patterns.rows.find(r=>r.key===key);row.post_id=original;
  const saved=await fetch(TNCP.api+'patterns',{method:'POST',headers:h,body:JSON.stringify({revision:patterns.revision,rows:[row]})});if(!saved.ok)throw Error('Pattern cleanup failed');
 },{id,key:chosen.key,original:chosen.post_id});
 await page.getByRole('tab',{name:/^XP Patterns,/}).click();await ready();await page.screenshot({path:'tests/artifacts/pattern-layout.png',fullPage:true});
 console.log('PASS: mapped/total counts, restricted examples, widest descriptions, linked pattern editors, optional slug autosave, select-all exclusion, and cleared-slug deselection.');
 await browser.close();
})().catch(error=>{console.error(error);process.exit(1)});
