"""Read-only browser check. Needs a synthetic administrator and a preexisting training access group.
AFRA_TEST_PASSWORD_FILE points to a local secret file outside the repository.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import json,os
secret=Path(os.environ['AFRA_TEST_PASSWORD_FILE']).read_text();screens=Path(os.environ.get('AFRA_SCREENSHOT_DIR','/tmp/afra-access-screenshots'));screens.mkdir(parents=True,exist_ok=True);errors=[];failed=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
 for lang in ['fa','en']:
  page=browser.new_page(viewport={'width':1440,'height':1050},timezone_id='Asia/Tehran');page.on('pageerror',lambda e:errors.append(str(e)));page.on('response',lambda r:failed.append([r.status,r.url]) if r.status>=400 and '/api/' in r.url else None)
  page.goto(os.environ.get('AFRA_TEST_URL','http://127.0.0.1:5173'));page.evaluate('(lang)=>{localStorage.setItem("afra.language",lang);localStorage.setItem("afra.theme","dark")}',lang);page.reload();page.get_by_label('ایمیل' if lang=='fa' else 'Email',exact=True).fill(os.environ['AFRA_TEST_EMAIL']);page.get_by_label('گذرواژه' if lang=='fa' else 'Password',exact=True).fill(secret);page.get_by_role('button',name='ورود به سامانه' if lang=='fa' else 'Sign in',exact=True).click();page.get_by_role('heading',name='نمای کلی' if lang=='fa' else 'Overview',exact=True).wait_for();page.get_by_role('menuitem',name='پروژه و افراد' if lang=='fa' else 'Project & people',exact=False).click();page.get_by_role('tab',name='گروه‌های دسترسی' if lang=='fa' else 'Access groups',exact=True).click();page.get_by_role('button',name='ویرایش' if lang=='fa' else 'Edit',exact=False).first.click();dialog=page.get_by_role('dialog',name='گروه دسترسی' if lang=='fa' else 'Access group',exact=True);dialog.get_by_placeholder('جست‌وجوی مجوزها' if lang=='fa' else 'Search permissions').fill('cases.create')
  if lang=='fa':page.set_viewport_size({'width':390,'height':844})
  page.wait_for_timeout(350);page.screenshot(path=str(screens/('access-mobile-fa.png' if lang=='fa' else 'access-permissions-en.png')));assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1');box=dialog.locator('.ant-drawer-footer').bounding_box();assert box and box['y']+box['height']<=page.viewport_size['height']+1
  page.close()
 browser.close()
print(json.dumps({'page_errors':errors,'failed_api_requests':failed}));assert not errors and not failed
