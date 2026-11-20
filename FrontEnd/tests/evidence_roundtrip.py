"""Writes synthetic evidence/results. Run ONLY against disposable training data.
Needs an assigned tester and a run containing required checklist steps; never run on production.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import json,hashlib,time,os
secret=Path(os.environ['AFRA_TEST_PASSWORD_FILE']).read_text();screens=Path(os.environ.get('AFRA_SCREENSHOT_DIR','/tmp/afra-evidence-screenshots'));screens.mkdir(parents=True,exist_ok=True);errors=[];failed=[];proof=Path('/tmp/afra-training-proof-'+str(int(time.time()))+'.txt');proof.write_text('Afra training evidence. Synthetic data only.\n')
with sync_playwright()as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage']);page=browser.new_page(viewport={'width':1440,'height':1050});page.on('pageerror',lambda e:errors.append(str(e)));page.on('response',lambda r:failed.append([r.status,r.url])if r.status>=400 and '/api/'in r.url else None)
 try:
  page.goto(os.environ.get('AFRA_TEST_URL','http://127.0.0.1:5173'));page.get_by_label('ایمیل',exact=True).fill(os.environ['AFRA_TEST_EMAIL']);page.get_by_label('گذرواژه',exact=True).fill(secret);page.get_by_role('button',name='ورود به سامانه',exact=True).click();page.get_by_role('heading',name='نمای کلی',exact=True).wait_for();page.get_by_role('menuitem',name='اجرا و تاریخچه',exact=False).click();page.get_by_role('button',name='اجرای تست',exact=True).first.click();page.get_by_role('button',name='ثبت نتیجه',exact=True).first.click();dialog=page.get_by_role('dialog');dialog.locator('input[type=file]').set_input_files(str(proof));dialog.get_by_text(proof.name,exact=True).wait_for()
  def choose(control,value):
   control.locator('xpath=ancestor::div[contains(@class,"ant-select")][1]').click();popup_id=control.get_attribute('aria-controls');popup=page.locator('.ant-select-dropdown').filter(has=page.locator('[id="'+popup_id+'"]'));option=popup.locator('.ant-select-item-option-content:visible').filter(has_text=value);print('select',value,popup_id,'options',popup.locator('.ant-select-item-option-content').all_text_contents(),flush=True);option.click()
  controls=dialog.get_by_label('نتیجه مورد',exact=True)
  for i in range(controls.count()):choose(controls.nth(i),'pass')
  choose(dialog.get_by_label('نتیجه نهایی',exact=True),'pass');dialog.get_by_label('توضیح نتیجه',exact=False).fill('شواهد آموزشی ثبت شد و انتظارهای الزامی مطابق بودند.');page.screenshot(path=str(screens/'execution-evidence-fa.png'),full_page=False);dialog.get_by_role('button',name='ذخیره',exact=True).click();dialog.wait_for(state='hidden');page.get_by_role('button',name='تاریخچه تلاش‌ها',exact=True).first.click();dialog=page.get_by_role('dialog',name='تاریخچه تلاش‌ها',exact=True);dialog.get_by_text(proof.name,exact=True).wait_for();page.screenshot(path=str(screens/'result-history-fa.png'),full_page=False)
  with page.expect_download()as pending:dialog.get_by_role('button',name='دریافت '+proof.name,exact=True).click()
  download=pending.value;assert Path(download.path()).read_bytes()==proof.read_bytes();print(json.dumps({'page_errors':errors,'failed_api_requests':failed,'download_sha256':hashlib.sha256(proof.read_bytes()).hexdigest()},ensure_ascii=False));assert not errors and not failed
 except Exception:
  page.screenshot(path='/tmp/afra-evidence-debug.png',full_page=False);print(json.dumps({'page_errors':errors,'failed_api_requests':failed},ensure_ascii=False),flush=True);raise
 finally:browser.close()
