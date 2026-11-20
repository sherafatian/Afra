# Browser smoke flow / بررسی مرورگر

Use a disposable local training database with an administrator, project, approved cases and an execution run. Never use production accounts or real personal data for guide screenshots. Install Python Playwright through a virtual environment; the script currently uses `/usr/bin/chromium` (adjust to your installed browser).

Provide `AFRA_TEST_EMAIL`, `AFRA_TEST_PASSWORD` and optionally `AFRA_TEST_URL` in the process environment. Run `python tests/browser_smoke.py` from FrontEnd. Screenshots default to `/tmp/afra-smoke-screenshots`; use `AFRA_SCREENSHOT_DIR` explicitly to regenerate approved guide images. Never commit the credential environment or print it. This smoke test is not comprehensive browser coverage.

فقط با دیتابیس آموزشی دورریختنی، کاربر مدیر، پروژه، تست تأییدشده و اجرای موجود اجرا کنید. رمز را در محیط فرایند بگذارید، نه سورس یا خروجی لاگ. تصاویر پیش‌فرض در پوشه موقت‌اند. این جریان جای آزمون جامع رابط و دسترس‌پذیری را نمی‌گیرد.
