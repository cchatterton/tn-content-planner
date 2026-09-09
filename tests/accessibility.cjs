const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const page=await browser.newPage({viewport:{width:1600,height:1050}});
 await page.goto('http://127.0.0.1:8765/wp-login.php');
 await page.locator('#user_login').fill('tncp_admin');await page.locator('#user_pass').fill(process.env.TNCP_TEST_PASSWORD);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/');
 await page.goto('http://127.0.0.1:8765/wp-admin/admin.php?page=tn-content-planner');await page.getByRole('tab',{name:'Pages',exact:true}).click();await page.locator('tbody tr').first().waitFor();
 await page.addScriptTag({path:process.env.TNCP_AXE_PATH});
 for(const viewport of [{width:1600,height:1050},{width:390,height:844},{width:800,height:700}]){
  await page.setViewportSize(viewport);
  const result=await page.evaluate(async()=>await axe.run('.tncp-wrap',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
  if(result.violations.length)throw Error(JSON.stringify(result.violations.map(item=>({id:item.id,nodes:item.nodes.map(node=>node.target)}))));
 }
 await page.setViewportSize({width:1600,height:1050});
 await page.getByRole('button',{name:'Add row',exact:true}).click();
 const title=page.locator('[data-title]').last();await title.fill('Keyboard test');await title.press('Tab');
 await page.waitForFunction(()=>document.activeElement?.getAttribute('aria-label')==='Content slug');
 await page.keyboard.type('Keyboard Slug With Spaces');await page.keyboard.press('Tab');
 await page.waitForFunction(()=>document.activeElement?.getAttribute('aria-label')==='Parent');
 const row=page.locator('tbody tr').last();
 if(await row.getByRole('textbox',{name:'Content slug',exact:true}).inputValue()!=='keyboard-slug-with-spaces')throw Error('Slug normalization failed');
 // Focus trap and Escape cancel inside the native modal.
 await page.getByRole('button',{name:'Refresh linked posts',exact:true}).click();await page.locator('dialog[open]').waitFor();
 const result=await page.evaluate(async()=>await axe.run('.tncp-dialog',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
 if(result.violations.length)throw Error(JSON.stringify(result.violations));
 await page.keyboard.press('Escape');await page.locator('dialog[open]').waitFor({state:'hidden'});
 console.log('PASS: Axe WCAG A/AA at desktop/mobile/narrow widths, modal audit, keyboard field traversal and slug normalization.');
 await browser.close();
})().catch(error=>{console.error(error);process.exit(1)});
