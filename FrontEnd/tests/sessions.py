"""Browser regression using DISPOSABLE synthetic training fixtures only.
AFRA_TEST_PASSWORD_FILE must point outside Git; AFRA_TEST_EMAIL is the training admin.
Requirements flow also needs AFRA_REVIEWER_EMAIL (independent reviewer, same training password).
Requirements flow writes a requirement, a linked case, revision and compatibility record.
Never run on operational data. Requires Python Playwright and /usr/bin/chromium.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import json,os
secret=Path(os.environ['AFRA_TEST_PASSWORD_FILE']).read_text();errors=[];failed=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage']);context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.on('response',lambda r:failed.append([r.status,r.url]) if r.status>=400 and '/api/'in r.url else None)
 try:
  page.goto(os.environ.get('AFRA_TEST_URL','http://127.0.0.1:5173'));page.get_by_label('ایمیل',exact=True).fill(os.environ['AFRA_TEST_EMAIL']);page.get_by_label('گذرواژه',exact=True).fill(secret);page.get_by_role('button',name='ورود به سامانه',exact=True).click();page.get_by_role('heading',name='نمای کلی',exact=True).wait_for();page.reload();page.get_by_role('heading',name='نمای کلی',exact=True).wait_for();other=context.new_page();other.on('pageerror',lambda e:errors.append(str(e)));other.goto(os.environ.get('AFRA_TEST_URL','http://127.0.0.1:5173'));other.get_by_role('heading',name='نمای کلی',exact=True).wait_for();page.reload();other.reload();page.get_by_role('heading',name='نمای کلی',exact=True).wait_for();other.get_by_role('heading',name='نمای کلی',exact=True).wait_for();assert page.evaluate('Object.keys(localStorage).every(key=>!/(token|csrf|password)/i.test(key))');page.route('**/api/auth/end-session',lambda route:route.abort('failed'));page.locator('.account-menu').click();page.get_by_role('menuitem',name='خروج',exact=False).click();page.get_by_text('Failed to fetch',exact=False).wait_for();assert page.get_by_role('heading',name='نمای کلی',exact=True).is_visible();page.unroute('**/api/auth/end-session');page.locator('.account-menu').click();page.get_by_role('menuitem',name='خروج',exact=False).click();page.get_by_role('button',name='ورود به سامانه',exact=True).wait_for();other.get_by_role('button',name='ورود به سامانه',exact=True).wait_for();page.reload();page.get_by_role('button',name='ورود به سامانه',exact=True).wait_for();assert not errors and not failed;print(json.dumps({'page_errors':errors,'failed_api_requests':failed,'reload_restore':True,'two_tab_restore':True,'logout_propagation':True}))
 except Exception:
  page.screenshot(path='/tmp/afra-session-debug.png');print(json.dumps({'page_errors':errors,'failed_api_requests':failed}));raise
 finally:browser.close()
