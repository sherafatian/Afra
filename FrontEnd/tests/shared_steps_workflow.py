"""Mutating browser acceptance on DISPOSABLE synthetic data only.
Requires AFRA_TEST_EMAIL, AFRA_TEST_REVIEWER_EMAIL (distinct training system admins),
AFRA_TEST_PASSWORD_FILE outside Git, optional AFRA_TEST_URL / AFRA_TEST_API.
Creates a reviewed shared block and a draft case; leaves synthetic data in place.
Requires Python Playwright and /usr/bin/chromium. No operational credentials/data.
"""
from pathlib import Path
import json
import os
import time
import urllib.request
import urllib.parse
from playwright.sync_api import sync_playwright, expect

secret = Path(os.environ['AFRA_TEST_PASSWORD_FILE']).read_text()
email = os.environ['AFRA_TEST_EMAIL']
stamp = str(int(time.time()))
title = 'مراحل مشترک آموزشی ' + stamp
case_title = 'اتصال مراحل آموزشی ' + stamp
errors, failed = [], []

def api(path, body=None, token=None, method=None, version=None):
    headers = {'Accept': 'application/json', 'Content-Type': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    if version:
        headers['If-Match'] = str(version)
    request = urllib.request.Request(os.environ.get('AFRA_TEST_API', 'http://127.0.0.1:8000/api/') + path,
        data=json.dumps(body, ensure_ascii=False).encode() if body is not None else None,
        headers=headers, method=method)
    with urllib.request.urlopen(request) as response:
        return json.load(response)['data']

author = api('auth/login', {'email': email, 'password': secret})['access_token']
reviewer = api('auth/login', {'email': os.environ['AFRA_TEST_REVIEWER_EMAIL'], 'password': secret})['access_token']
project = api('v1/projects?per_page=100', token=author)['data'][0]['id']
base = 'v1/projects/' + project
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', headless=True,
        args=['--no-sandbox', '--disable-dev-shm-usage'])
    page = browser.new_page(viewport={'width': 1440, 'height': 1050}, timezone_id='Asia/Tehran')
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('response', lambda response: failed.append([response.status, response.url])
        if response.status >= 400 and '/api/' in response.url else None)
    try:
        page.goto(os.environ.get('AFRA_TEST_URL', 'http://127.0.0.1:5173'))
        page.get_by_label('ایمیل', exact=True).fill(email)
        page.get_by_label('گذرواژه', exact=True).fill(secret)
        page.get_by_role('button', name='ورود به سامانه', exact=True).click()
        page.get_by_role('heading', name='نمای کلی', exact=True).wait_for()
        page.get_by_role('menuitem', name='تست‌ها و نیازمندی‌ها', exact=False).click()
        page.get_by_role('menuitem', name='مراحل مشترک', exact=True).click()
        page.get_by_role('button', name='افزودن', exact=False).click()
        dialog = page.get_by_role('dialog', name='تعریف جدید', exact=True)
        dialog.get_by_label('عنوان', exact=True).fill(title)
        dialog.get_by_label('اقدام', exact=True).fill('ورود به سامانه آموزشی')
        dialog.get_by_label('نتیجه مورد انتظار همین مورد', exact=True).fill('صفحه خانه نمایش داده شود')
        dialog.get_by_role('button', name='ذخیره', exact=True).click()
        dialog.wait_for(state='hidden')
        row = page.get_by_role('row').filter(has_text=title)
        row.get_by_role('button', name='درخواست بازبینی', exact=True).click()
        row.get_by_text('در انتظار بازبینی', exact=True).wait_for()
        block = next(row for row in api(base + '/records/shared-steps?q=' + urllib.parse.quote(title), token=author)['data'] if row['title'] == title)
        api(base + '/shared-steps/' + block['id'] + '/review', {'version': 1, 'decision': 'approve'}, reviewer)
        page.get_by_role('menuitem', name='تست کیس‌ها', exact=True).click()
        page.get_by_role('button', name='افزودن', exact=False).click()
        dialog = page.get_by_role('dialog', name='تست کیس جدید', exact=True)
        dialog.get_by_label('عنوان تست کیس', exact=True).fill(case_title)
        dialog.get_by_label('کاری که باید انجام شود', exact=True).fill('بررسی پرداخت')
        dialog.get_by_label('نتیجه مورد انتظار همین مورد', exact=True).fill('پرداخت موفق باشد')
        control = dialog.get_by_role('combobox').last
        control.fill(title)
        page.locator('.ant-select-dropdown:visible .ant-select-item-option-content').filter(has_text=title).click()
        dialog.get_by_text('ورود به سامانه آموزشی', exact=False).wait_for()
        page.set_viewport_size({'width': 390, 'height': 844})
        page.wait_for_timeout(400)
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        page.screenshot(path='/tmp/afra-shared-mobile.png')
        dialog.get_by_role('button', name='ذخیره', exact=True).click()
        dialog.wait_for(state='hidden')
        case = next(row for row in api(base + '/cases?q=' + urllib.parse.quote(case_title), token=author)['data'] if row['title'] == case_title)
        content = json.loads(case['content']) if isinstance(case['content'], str) else case['content']
        assert len(content['checklist']) == 2
        assert content['shared_blocks'][0]['version'] == 1
        assert len(content['local_checklist']) == 1
        print(json.dumps({'page_errors': errors, 'failed_api_requests': failed, 'pinned_revision': 1, 'combined_steps': 2}, ensure_ascii=False))
        assert not errors and not failed
    except Exception:
        page.screenshot(path='/tmp/afra-shared-debug.png')
        print(json.dumps({'page_errors': errors, 'failed_api_requests': failed}, ensure_ascii=False))
        raise
    finally:
        browser.close()
