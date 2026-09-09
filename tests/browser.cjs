// Run against the disposable local WordPress site, with Playwright available in NODE_PATH.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
let lastPage;
(async () => {
 const browser = await chromium.launch({headless:true,channel:'chrome'});
 const page = await browser.newPage({viewport:{width:1600,height:1050}});
 lastPage = page; page.setDefaultTimeout(15000);
 const errors = []; page.on('pageerror', error => errors.push(error.message));
 await page.goto('http://127.0.0.1:8765/wp-login.php');
 await page.locator('#user_login').fill('tncp_admin');
 await page.locator('#user_pass').fill(process.env.TNCP_TEST_PASSWORD);
 await page.locator('#wp-submit').click();
 await page.waitForURL('**/wp-admin/');
 await page.goto('http://127.0.0.1:8765/wp-admin/admin.php?page=tn-content-planner');
 if(process.env.TNCP_TEST_PUBLIC_TYPES){
  await page.getByRole('tab',{name:'Public demo',exact:true}).waitFor();
  await page.getByRole('tab',{name:'Hidden UI public demo',exact:true}).click();
  await page.getByRole('heading',{name:'Build your content structure',exact:true}).waitFor();
  assert.equal(await page.getByRole('tab',{name:'Internal demo',exact:true}).count(),0);
  assert.equal(await page.getByRole('tab',{name:'Patterns',exact:true}).count(),0);
 }
 await page.getByRole('tab',{name:'Pages',exact:true}).click();
 await page.getByRole('button',{name:'Add row',exact:true}).waitFor();
 fs.mkdirSync('tests/artifacts',{recursive:true});
 await page.screenshot({path:'tests/artifacts/empty-desktop.png',fullPage:true});
 const token = Date.now();
 const rootSlug = `browser-${token}-home`, childSlug = `browser-${token}-child`;
 const csv = 'title,slug\r\n' +
   `"<i class=""fa-solid fa-house"" aria-hidden=""true""></i> Home",${rootSlug}\r\n` +
   `"Services, overview",${childSlug}\r\n`;
 await page.locator('#tncp-csv').setInputFiles({name:'plan.csv',mimeType:'text/csv',buffer:Buffer.from(csv)});
 await page.getByText('2 rows imported.',{exact:false}).waitFor();
 assert.equal(await page.locator('tbody tr').count(),2);
 assert.equal(await page.locator('tbody tr').nth(1).locator('.tncp-pattern').innerText(),'page-0-single-0');
 assert.equal(await page.locator('tbody tr input[type="checkbox"]:checked').count(),0);
 const rootId=await page.locator('tbody tr').first().getAttribute('data-row');
 await page.locator('tbody tr').nth(1).getByRole('combobox',{name:'Parent',exact:true}).selectOption(`row:${rootId}`);
 await page.waitForFunction(()=>document.querySelectorAll('.tncp-pattern')[1]?.textContent==='page-1-single-0');
 assert.equal(await page.locator('tbody tr').first().locator('.fa-house').count(),1);
 const iconFont = await page.locator('.tncp-title .fa-house').evaluate(node => getComputedStyle(node).fontFamily);
 assert(iconFont.includes('Font Awesome'));
 await page.getByRole('button',{name:'Save plan',exact:true}).click();
 await page.getByText('Plan saved.',{exact:false}).waitFor();
 await page.getByRole('button',{name:'Select all',exact:true}).click();
 await page.getByRole('button',{name:'Review & create selected',exact:true}).click();
 await page.screenshot({path:'tests/artifacts/review-desktop.png',fullPage:true});
 await page.getByRole('button',{name:'Create / update selected',exact:true}).click();
 await page.getByText('2 rows applied.',{exact:false}).waitFor();
 const first = page.locator('tbody tr').first();
 assert.match(await first.locator('td').nth(11).innerText(),/^\d+$/);
 // Mapped title modal: cancel, then update and commit.
 await first.getByRole('button',{name:'Edit title:',exact:false}).click();
 await first.getByRole('textbox',{name:'Title, HTML allowed'}).fill('Home changed');
 await first.getByRole('textbox',{name:'Title, HTML allowed'}).press('Tab');
 await page.locator('dialog[open]').waitFor();
 await page.screenshot({path:'tests/artifacts/title-dialog.png',fullPage:true});
 await page.getByRole('button',{name:'Cancel',exact:true}).click();
 assert.equal(await first.locator('.tncp-title').innerText(),' Home');
 await first.getByRole('button',{name:'Edit title:',exact:false}).click();
 await first.getByRole('textbox',{name:'Title, HTML allowed'}).fill('Home changed');
 await first.getByRole('textbox',{name:'Title, HTML allowed'}).press('Tab');
 await page.getByRole('button',{name:'Change linked post',exact:true}).click();
 await page.getByRole('button',{name:'Save plan',exact:true}).click();
 await page.getByText('Plan saved.',{exact:false}).waitFor();
 await first.getByRole('checkbox',{name:'Select Home changed',exact:true}).check();
 await page.getByRole('button',{name:'Review & create selected',exact:true}).click();
 await page.getByRole('button',{name:'Create / update selected',exact:true}).click();
 await page.getByText('1 rows applied.',{exact:false}).waitFor();
 // Parent changes move row immediately and require explicit modal confirmation.
 await page.locator('tbody tr').nth(1).getByRole('combobox',{name:'Parent',exact:true}).selectOption('');
 await page.getByRole('button',{name:'Move linked post',exact:true}).click();
 await page.waitForFunction(() => document.querySelectorAll('.tncp-pattern')[1]?.textContent === 'page-0-single-0');
 // Creating a new item preserves original mapped row.
 const root = page.locator('tbody tr').first();
 await root.getByRole('button',{name:'Edit title:',exact:false}).click();
 await root.getByRole('textbox',{name:'Title, HTML allowed'}).fill('New content idea');
 await root.getByRole('textbox',{name:'Title, HTML allowed'}).press('Tab');
 await page.getByRole('button',{name:'Create new plan item',exact:true}).click();
 await page.waitForFunction(() => document.querySelectorAll('tbody tr').length === 3);
 assert.equal(await page.locator('tbody tr').last().locator('td').nth(11).innerText(),'—');
 await page.getByRole('button',{name:'Save plan',exact:true}).click();
 await page.getByText('Plan saved.',{exact:false}).waitFor();
 await page.screenshot({path:'tests/artifacts/planner-desktop.png',fullPage:true});
 // Trash actions remain labelled, discreet and protected by confirmation.
 const trash=page.locator('tbody tr').last().getByRole('button',{name:'Remove row: New content idea',exact:true});
 assert.equal(await trash.innerText(),'');
 assert.equal(await trash.getAttribute('title'),'Remove row');
 assert.equal(await trash.locator('.dashicons-trash').count(),1);
 await trash.click(); await page.getByRole('button',{name:'Cancel',exact:true}).click();
 assert.equal(await page.locator('tbody tr').count(),3);
 // CSV template download is exactly the documented blank header.
 const downloadPromise = page.waitForEvent('download');
 await page.getByRole('button',{name:'Download CSV template',exact:true}).click();
 const download = await downloadPromise;
 const path = await download.path();
 assert.equal(fs.readFileSync(path,'utf8').replace(/^\uFEFF/,''),'title,slug\r\n');
 // Invalid imports do not alter rows.
 await page.locator('#tncp-csv').setInputFiles({name:'bad.csv',mimeType:'text/csv',buffer:Buffer.from('title,slug,parent\r\nUnexpected,unexpected,extra\r\n')});
 await page.getByText('Use the column order in the downloadable CSV template.',{exact:false}).waitFor();
 assert.equal(await page.locator('tbody tr').count(),3);
 // Unauthenticated/missing nonce request fails through actual HTTP authentication.
 const denied = await page.evaluate(async () => (await fetch(TNCP.api+'save/page',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({revision:0,rows:[]})})).status);
 assert.equal(denied,401);
 await page.setViewportSize({width:390,height:844});
 await page.screenshot({path:'tests/artifacts/planner-mobile.png',fullPage:true});
 const overflow = await page.locator('.tncp-scroll').evaluate(node => node.scrollWidth > node.clientWidth);
 assert(overflow,'Table scrolls on mobile');
 const pageWidth = await page.evaluate(() => ({scroll:document.documentElement.scrollWidth,width:innerWidth}));
 assert(pageWidth.scroll <= pageWidth.width+2,'No whole-page horizontal overflow');
 await page.setViewportSize({width:800,height:700});
 await page.screenshot({path:'tests/artifacts/planner-narrow.png',fullPage:true});
 // Update links native WordPress plugin row.
 await page.goto('http://127.0.0.1:8765/wp-admin/plugins.php');
 const plugin = page.locator('tr[data-slug="tn-content-planner"]').first();
 assert.equal(await plugin.getByRole('link',{name:'GitHub',exact:true}).count(),1);
 assert.equal(await plugin.getByRole('link',{name:'Check for updates',exact:true}).count(),1);
 assert.equal(await plugin.getByRole('link',{name:'Visit plugin site',exact:true}).count(),0);
 assert.deepEqual(errors,[]);
 console.log('PASS: Browser workflow, CSV, HTML/icon rendering, modals, draft creation, responsive layouts, nonce rejection and plugin links.');
 await browser.close();
})().catch(async error => { console.error(error); if(lastPage){console.error(await lastPage.locator('#tncp-notice').innerText().catch(()=>''));await lastPage.screenshot({path:'tests/artifacts/failure.png',fullPage:true}).catch(()=>{});} process.exit(1); });
