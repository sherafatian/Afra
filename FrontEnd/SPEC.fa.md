# شرح مستقل FrontEnd — فارسی

این فایل نیازمندی کامل است؛ وضعیت کد در docs/implementation-status.fa.md مشخص می‌شود.

# افرا — دستور پیاده‌سازی مستقل FrontEnd

نسخه دامنه: ۴٫۱ | تاریخ: ۱۴۰۵/۰۸/۲۸ | مقصد: پوشه FrontEnd مخزن sherafatian/Afra

این فایل مستقل شامل قرارداد فرانت و کل شرح محصول در ادامه است. از آن یک برنامه React واقعی و قابل اجرا بسازید؛ HTML همراه نمونه بصری/تعامل است، نه جایگزین React یا سرور JWT. بازسازی باید رفتارها و جزئیات این سند را حفظ کند؛ برای تطابق دقیق ظاهر، HTML مرجع مکمل است ولی قواعد لازم در خود این فایل آمده‌اند.

## A. فناوری و ساختار

React + TypeScript strict + Ant Design، Vite، React Router، TanStack Query برای داده سرور، React Hook Form یا AntD Form با Schema مشترک اعتبارسنجی، i18next برای متن‌ها. مدیریت احراز هویت/تم/زبان در Context یا store کوچک؛ داده سرور در Query Cache بماند. به یک سامانه فرضی Next.js یا بک‌اند یکپارچه تغییر ندهید.

ساختار پیشنهادی:

```text
src/
  app/                 router, providers, bootstrap, permission boundary
  api/                 typed fetch client, error decoder, contracts, auth refresh
  features/auth/       login, forced password change, sessions
  features/people/     users, profile, avatar, groups, roles, effective access
  features/projects/   project picker, section tree, membership
  features/design/     cases, checklist, templates, shared steps, requirements
  features/planning/   plans, views, environments, builds, configuration profiles
  features/execution/  preview, manual runner, immutable history, drafts, retest
  features/defects/     defects, transitions, force close
  features/quality/    releases, traceability, coverage, reports, evidence
  features/services/   catalog, clients, one-time secrets, delegations
  components/          FormDrawer, MobileSheet, DataTable, UserAvatar, DateField
  locales/             fa, en; translation keys and resource bundles
  theme/               tokens, algorithms, typography, rtl utilities
  utils/               dates, safe formatting, validation, file limits
```

API داخلی `/api/v1`، auth `/api/auth`، service management زیر `/api/v1/system`. endpointهای external در SPA اجرا نمی‌شوند و client secret در bundle نگهداری نشود. Feature flag mock فقط برای توسعه و صریح باشد؛ در حالت API، شکست درخواست نباید با ساخت داده محلی موفق نشان داده شود.

## B. احراز هویت JWT و رفتار کلاینت

- Access JWT فقط در حافظه. JWT، Refresh، رمز و راز service client را در localStorage/sessionStorage/URL/analytics قرار ندهید. Theme و language و نمای انتخابی کاربر قابل ذخیره‌اند؛ داده شخصی و رمز پایدار محلی مجاز نیست.
- Login POST email/password؛ در حالت عادی access_token و user summary از سرور، Refresh فقط HttpOnly Cookie. پس از reload، bootstrap با CSRF و refresh کنترل‌شده سپس GET me انجام شود؛ صفحه ورود پیش از اتمام bootstrap چشمک نزند.
- fetch wrapper با Authorization Bearer، Accept-Language، X-Request-ID و credentials برای auth. پاسخ 401 فقط یک بار و با single-flight refresh در چند درخواست موازی بازیابی شود؛ پس از شکست، داده حساس Cache پاک و مسیر ورود با پیام مناسب نمایش داده شود. loop refresh یا retry برای 403/422 ممنوع.
- کد auth access ۱۵ دقیقه و refresh حداکثر خانواده ۷ روز است؛ سرور مرجع اعتبار است. account_disabled/expired یا session_revoked را با پاک کردن نشست و فرم‌های حساس پاسخ دهید. ذخیره نسخه قدیمی ACL در JWT کافی نیست؛ capabilities از سرور و پس از تغییر مجوز invalidation شود.
- اجبار اولین تغییر: سرور requires_password_change و challenge_token پنج‌دقیقه‌ای می‌دهد؛ هیچ مسیر محصول، منو، Deep link یا cache قبلی باز نشود. فقط تغییر رمز اولیه و خروج مجاز است؛ پس از موفقیت توکن عادی از سرور دریافت یا ورود تازه انجام شود. challenge را در Authorization endpoint محصول به کار نبرید.
- تغییر رمز خود: رمز فعلی، جدید و تأیید، حداقل ۱۲/حداکثر ۱۲۸ نویسه. reset مدیر: رمز موقت تازه و reason، تأیید ابطال نشست‌ها و اجبار تغییر. رمز اولیه جدید پیش‌فرض force change روشن. همه inputهای رمز type=password و autocomplete مناسب؛ پس از بسته‌شدن/ثبت از حافظه فرم پاک شوند؛ هیچ پیش‌پرکردن رمز واقعی نداشته باشند.
- درخواست refresh/logout نیازمند X-CSRF-Token از endpoint CSRF مجاز و Origin allowlist است. جابه‌جایی زیر دامنه به معنی حذف حفاظت CSRF نیست. هماهنگی refresh در چند تب با Web Locks/BroadcastChannel بدون اشتراک راز پایدار انجام شود.

## C. قرارداد API، خطا و رقابت داده

پاسخ واحد: `{data,meta}`؛ خطا `{error:{code,message,fields,request_id}}`؛ تاریخ ISO خالص یا UTC RFC3339؛ شناسه‌ها رشته. فهرست‌ها page/per_page و sort allowlist، پیش‌فرض 25 و حداکثر 100. خطای fields روی همان فیلد و خلاصه ماندگار بالای فرم با role=alert نشان داده شود؛ اولین فیلد نامعتبر focus بگیرد و ورودی‌ها حفظ شوند. Stack Trace و متن برنامه‌نویسی را به کاربر نشان ندهید؛ request_id برای پشتیبانی قابل کپی باشد.

فرم ویرایش با version/If-Match؛ تعارض 409/412 داده کاربر را پاک نکند و نسخه جاری/مقایسه/بارگذاری دوباره را روشن کند. پیش‌نمایش اجرا preview_id و امضای سرور می‌گیرد؛ تغییر تست/نیازمندی/داده/زمینه باعث توقف ثبت و refresh صریح پیش‌نمایش شود. برای POSTهای حساس از UUID/Idempotency-Key استفاده و دکمه در حین ارسال یک‌بار غیرفعال شود؛ شکست، قفل دائمی روی دکمه نگذارد. نتیجه انجام‌نشده را optimistic موفق نکنید.

## D. طراحی، تم، زبان و موبایل

فارسی پیش‌فرض، انگلیسی قابل انتخاب، AntD ConfigProvider direction و locale هماهنگ. تمام متن‌های رابط کلید ترجمه داشته باشند؛ نام و محتوای کاربر ترجمه خودکار نشود. CSS logical properties به جای left/right. تم light/dark/system در localStorage، با تغییر زنده prefers-color-scheme. Tokens فقط مالک ریشه برنامه باشند؛ دکمه انتخاب تم نباید متغیر ریشه را عوض کند.

روشن: background #fff7ef، panel #fffdfb، text #322820، brand #f47712، primary-fill #c94c00، softorange #fff0df و line #efdfd2. تاریک: background #11151c، panel #1b2029، text #f3eae1، brand/primary #ff8d32، softorange #412916، line #37312d. متن سفید روی دکمه تیره روشن و متن بسیار تیره روی دکمه نارنجی دارک؛ سبز/قرمز Pass/Fail با متن/آیکن همراه باشد. فونت UI Vazirmatn داخلی یا همان فونت HTML، متن عمومی حدود 15px، جدول 13px، عنوان 26px؛ با فونت Word (وزیر/Cambria) اشتباه نشود.

منوی اصلی هفت فضای کاری دارد؛ قابلیت‌های فرعی با تب، نه ۳۰ قلم سایدبار. تنظیمات از پروفایل، اعلان از زنگ. سرویس و تفویض فقط برای مدیر سامانه در تب فضای پروژه/افراد ظاهر شود؛ تعداد گروه اصلی زیاد نشود. درختواره بخش‌ها در دسکتاپ حدود 290px (نسخه قبل 235px) با عرض محتوای minmax(0,1fr)؛ در عرض کمتر از 1000px تک‌ستونی و جمع‌پذیر شود.

در حداکثر 760px، FormDrawer و Modal به باتم‌شیت با گوشه بالایی 22px، دستگیره تغییر ارتفاع، محتوای اسکرولی، سر و دکمه‌های پایین ثابت، Safe Area و ارتفاع هماهنگ با visualViewport تبدیل شوند. نوار پایین: نمای کلی، تست‌ها، اجرا، بیشتر. بیشتر همه هفت گروه، تعویض پروژه و تنظیمات را باز کند. شیت با دستگیره تغییر ارتفاع دهد؛ کشیدن نباید ذخیره یا حذف فرم کند. حفظ فوکوس، inert پس‌زمینه، Escape، بازگشت فوکوس و reduce-motion لازم‌اند. ورودی موبایل حداقل 16px و فضای لمس 44px.

چک‌لیست اجرا Grid: شماره، متن انعطاف‌پذیر و دکمه در دسکتاپ؛ یادداشت در ردیف مستقل. زیر 1050px دکمه‌ها زیر متن و یادداشت ردیف بعد؛ متن طولانی، خروجی، پیوست و اختیاری نباید متن را یک‌حرفی کنند. دکمه موفق/ناموفق متن و آیکن و aria-pressed داشته باشند.

## E. تاریخ شمسی و ورودی‌ها

تمام تاریخ‌های قابل نمایش، در هر دو زبان، هجری شمسی‌اند؛ زبان انگلیسی نباید تقویم را میلادی کند. Intl با calendar=persian؛ زمان رویداد Asia/Tehran، تاریخ خالص بدون تبدیل منطقه زمانی. فرم DateField، تقویم شمسی، ارقام فارسی/عربی/لاتین، شنبه آغاز هفته، ماه/سال، امروز، پاک کردن، min/max و کنترل صفحه‌کلید داشته باشد. استفاده از input type=date بومی میلادی به‌عنوان UI نهایی ممنوع.

مقدار ارسالی همیشه ISO است؛ تبدیل دوطرفه و مرز نوروز/اسفند کبیسه آزموده شود. تاریخ پایان حساب آخرین روز مجاز است؛ UI این معنی را توضیح دهد. تولد آینده رد شود. شناسه ملی/موبایل رشته باشند و شماره صفرابتدا حفظ شود. خطای سمت سرور بر اعتبارسنجی مرورگر مقدم است.

## F. کاربران، پروفایل و رمز

فهرست کاربران فقط برای مدیر سامانه: عکس، نام، ایمیل، فعال/غیرفعال/منقضی، پایان دسترسی، الزام تغییر رمز و عملیات ویرایش/بازنشانی. فیلتر وضعیت و جستجو، جلوگیری از قفل آخرین مدیر، تأیید واضح و دلیل برای تغییر وضعیت. کاربران غیرفعال در انتخاب مسئول اجرای جدید غیرفعال یا با علت غیرقابل انتخاب باشند؛ تاریخچه نام آن‌ها باقی بماند.

فرم مدیر: name، email، photo، mobile، birth_date، national_id، department، job_title، employee_code، bio، active، expires_on، system admin و رمز اولیه برای ساخت. رمز هنگام ویرایش از سرور پر نشود؛ reset عملیات جداست. هنگام ایجاد force change پیش‌فرض روشن و قابل انتخاب مدیر است.

پروفایل خود: مشاهده اطلاعات و سطح دسترسی، ویرایش نام، موبایل، تولد، عکس و bio؛ کد ملی تنها اولین تکمیل و پس از آن readonly با توضیح درخواست اصلاح از مدیر. ایمیل/نقش/وضعیت/انقضا/اطلاعات سازمانی توسط خود فرد تغییر نکند. بخش امنیت، تغییر رمز و نشست‌ها داشته باشد؛ مسیر ورود اجباری راه دور زدن از پروفایل نداشته باشد.

عکس: انتخاب/پیش‌نمایش/حذف، JPEG/PNG/WebP تا512KiB، برش مربعی و بارگذاری امن؛ خطای decode و فایل بزرگ روشن باشد. Data URL محلی پیش‌نمایش فقط در حافظه و پس از بسته‌شدن آزاد شود؛ عکس نهایی از storage مجاز سرور بیاید. SVG و آدرس دلخواه ناشناس نپذیرید. تصویر با alt و حالت حروف اول جایگزین شود.

در نسخه HTML نمونه، رمزها صرفاً برای نمایش گردش کار با PBKDF2/WebCrypto و داده محلی مدیریت می‌شوند؛ در محصول واقعی همه ورود/Hash/Reset/JWT سمت Laravel است و PBKDF2 مرورگر جانشین Argon2id سرور نیست. نمونه HTML حق استفاده از اطلاعات واقعی محرمانه ندارد.

## G. نقش، گروه و مجوز ریز

صفحه کاربران و دسترسی تب‌های اعضا، کاربران سامانه، نقش‌های پیش‌فرض، نقش سفارشی، دسترسی بخش، گروه‌های دسترسی و مجوز مستقیم داشته باشد. role/group/grant را یکی نکنید. هر عملیات با عنوان فارسی و کلید فنی ثابت در صورت نیاز معرفی شود؛ ورودی inherit/allow/deny روشن باشد. جستجوی عملیات و دسته‌بندی منابع برای فرم‌های طولانی مفید است.

منع صریح در نقش/گروه/مستقیم بر allowance مقدم است؛ بخش و مسئولیت هم اثر دارند. پیش‌نمایش دسترسی مؤثر از capabilities سرور و توضیح منبع حق/منع نمایش داده شود. گروه متعلق به پروژه است، چندعضوی و قابل غیرفعال‌سازی؛ عضو/مجوز حذف‌شده تاریخچه را تغییر ندهد.

دکمه‌ها و routeهای غیرمجاز محافظت شوند؛ ولی چون داده مجوز سرور ممکن است عوض شود، 403 هنگام کلیک را هم درست پاسخ دهید. فقط مخفی کردن دکمه امنیت نیست. اجازه ساخت نباید خودکار ویرایش/حذف یا تخصیص بدهد. تغییر assignee/substitute عملیات مستقل cases.assign است؛ هنگام ایجاد، خودتخصیصی می‌تواند در سیاست صریح مجاز باشد ولی تخصیص دیگری بدون حق ممنوع است. عملگر مدیریت دسترسی نتواند مجوزی بالاتر از اختیار خودش یا system-level بدهد.

## H. خطا و بستن اجباری

جزئیات خطا: شرح زیر نام فرد در تاریخچه، دکمه تست مجدد در پایین با علت غیرفعالی و توضیح فرق رفع‌شده/بسته. آزمایشگر مسئول/جانشین دارای runs.retest لازم نیست managePlans داشته باشد. سرور نتیجه همان defectId و زمان بعد از resolved_at را بررسی می‌کند؛ تیک پیش‌نویس کافی نیست.

برای defects.force_close، دکمه مستقل قرمز «بستن اجباری خطا». Modal/Sheet با متن «نتیجه موفق ایجاد نمی‌شود و تأیید رفع نیست»، دلیل ۱۰..۱۰۰۰ نویسه و تأیید صریح. endpoint جداگانه فراخوانی شود، نه ارسال status=closed به مسیر معمول. نتیجه با برچسب «بسته‌شده اجباری؛ تأیید رفع انجام نشده»، شخص، زمان و دلیل در جزئیات/تاریخچه باقی بماند. Pass/verified_at در کلاینت جعل نشود. مدیر سامانه و پروژه پیش‌فرض این حق را دارند؛ دیگران فقط با grant صریح.

## I. مدیریت سرویس و تفویض

فقط مدیر سامانه؛ سه بخش کاتالوگ سرویس، مصرف‌کننده و تفویض. دو endpoint اولیه: ثبت تست و گزارش پیشرفت هر پروژه. سرویس در ابتدا خاموش؛ فعال/غیرفعال با توضیح اثر. API جدید اجرایی با URL یا کد دلخواه نسازید.

مصرف‌کننده: نام، پروژه‌ها، scopes، انقضا، rate limit، وضعیت، ساخت/ویرایش/لغو/چرخش کلید. راز ایجاد/چرخش فقط یک بار در Modal محافظت‌شده و قابل کپی باشد؛ بعد از بسته شدن هرگز از سرور دوباره نمایش داده نشود. وضعیت expiry/revoked از زمان واقعی سرور باشد. لغو، تفویض‌های وابسته را فوراً غیرمؤثر نشان دهد.

تفویض: client، تفویض‌کننده، گیرنده متفاوت، پروژه‌ها، عملیات، انقضا و دلیل. UI گزینه‌ها را با حدود client/کاربران محدود و علت رد را نمایش دهد؛ سرور دوباره کنترل می‌کند. یک سطح تفویض و بدون بازتفویض ضمنی. رکورد صادرشده immutable؛ تغییر با لغو و صدور تازه. فعال بودن مؤثر به account و group و service هم وابسته است؛ فقط revoked=false کافی نیست.

مبنای گزارش سرویس بیرونی، هر پروژه است: project_id الزامی، بدون تجمیع بی‌اجازه همه پروژه‌ها. مثال درخواست، پاسخ counts و مخرج‌ها و خطاهای Scope در UI راهنمای سرویس نمایش داده شود. اثر Force close در خروجی با closed_forced_unverified جدا باشد؛ پیشرفت تست از وضعیت واقعی اقلام محاسبه شود.

HTML آفلاین فقط مدیریت نمایشی metadata را دارد و JWT/کلید قابل مصرف یا سرویس شبکه صادر نمی‌کند. در React عملیاتی، پنجره‌های صدور و چرخش از سرور واقعی استفاده کنند. توضیح مرز نمونه را از محصول عملیاتی حذف یا متناسب محیط نگه دارید؛ پیام «سرویس فعال است» فقط با موفقیت API واقعی گفته شود.

## J. قرارداد مسیرها و نمونه داده

عمده مسیرها:

- auth login/refresh/logout/password/first زیر /api/auth؛ پروفایل /api/v1/me/profile و avatar؛ users/reset-password زیر /api/v1/system/users.
- CRUD پروژه، بخش، تست، نسخه و بازبینی، نیازمندی/اثر تغییر، shared/template/dataset، plans/profiles/previews/executions/results/drafts/retests، defects/transitions/force-close، release/readiness/reports زیر /api/v1 و project-scoped.
- access-groups/roles/grants/section-rules و me/capabilities در پروژه؛ service-catalog/clients/rotate-secret/delegations در system.
- JSONهای پاسخ {data,meta} و {error}؛ ids رشته، checklist با text/expected/required، custom_fields تایپ‌دار؛ تاریخ خالصISO و timestampsUTC. Filterها و enumها از OpenAPI مشترک تولید شوند؛ کلید نامعتبر به سرور فرستاده نشود.

نام‌های فنی پیشنهادشده مانند test-cases و test-plans در BackEnd قرارداد ثابت‌اند؛ اگر path بدنه/پارامتر را تغییر می‌دهید، OpenAPI و هر دو کلاینت را با هم هماهنگ کنید. هیچ درخواست موفق فرضی برای endpoint پیاده‌نشده برنگردانید.

## K. آزمون و تحویل

آزمون مؤثر با Vitest/Testing Library و مرورگر واقعی Playwright برای: سه تم و System، RTL/LTR، عرض320/390/768/1440، شمسی و keyboard، فرم‌های طولانی/خطا/رقابت، modal focus/keyboard، چک‌لیست بلند و layout واقعی. رفتار named-control فرم با فیلد name=dataset باید در مرورگر واقعی یا شبیه‌سازی صریح shadowing تست شود؛ نوع فرم را از data attribute/React state بخوانید، نه form.dataset که ممکن است با کنترل نام‌دار تداخل کند.

جریان‌های اصلی: create→review مستقل→preview→manual result→defect→resolved→linked retest→close؛ مسیر force close بدون نتیجه؛ group deny و منع عملیات direct؛ reset و challenge اولین ورود؛ حساب منقضی؛ client scope و delegation revoke؛ preview تغییرکرده و حفظ داده فرم؛ فایل/Excel و Snapshot قدیمی. تست ابزار توسعه برای کد محصول با «قابلیت تست خودکار در محصول» فرق دارد و مجاز است.

تحویل: source کامل React، package lock، README اجرا، .env.example با VITE_API_BASE_URL و allowed build flags بدون secrets، routes، translation bundles و tokenها، typeهایAPI و اعتبارسنجی، تست‌ها و نتیجه build/typecheck. ابتدا نیازمندی‌ها را کامل کنید و پس از آماده شدن قابل بازبینی، درخواست انتشار جداگانه مطرح شود. این دستور اجازه deploy خودکار نیست.

---

# پیوست قرارداد HTTP و DTO مشترک نسخه ۴

این بخش در هر دو README عیناً وجود دارد. نام فیلدهای HTTP snake_case است؛ فرانت می‌تواند در لایه adapter مدل camelCase داشته باشد ولی روی شبکه نام‌ها را تغییر ندهد. تمام پاسخ‌ها داخل `{data,meta}` قرار می‌گیرند؛ مثال‌های این بخش محتوای data را نشان می‌دهند.

## احراز هویت و پروفایل

| عملیات | ورودی یا خروجی مهم |
|---|---|
| POST /api/auth/login | ورودی email,password؛ عادی: access_token,token_type="Bearer",expires_in=900,requires_password_change=false,user,session_id و Refresh Cookie فقط HttpOnly |
| ورود نیازمند تغییر | requires_password_change=true,challenge_token,expires_in=300؛ بدون Refresh و بدون access_token محصول |
| POST /api/auth/password/first | challenge در Authorization، temporary_password,new_password,new_password_confirmation؛ با موفقیت نشست عادی تازه یا requires_login=true |
| POST /api/auth/refresh | کوکی و X-CSRF-Token؛ همان پاسخ توکن عادی و چرخش Cookie؛ خانواده منقضی تمدید مطلق نشود |
| POST /api/auth/logout | کوکی/توکن و X-CSRF-Token؛ 204، پاک کردن کوکی و ابطال نشست |
| PATCH /api/auth/password | current_password,new_password,new_password_confirmation؛ 204 و ابطال نشست‌های قبلی؛ ورود دوباره |
| POST /api/v1/system/users/{id}/reset-password | temporary_password,temporary_password_confirmation,reason؛ خروجی must_change_password=true,auth_version؛ رمز خام برنگردد |
| POST /api/v1/system/users | name,email,initial_password,force_password_change=true,active=true,expires_on nullable,birth_date,national_id,mobile,department,job_title,employee_code,bio,is_system_admin=false |
| PATCH /api/v1/system/users/{id} | همان فیلدهای مجاز به‌جز initial_password؛ version/If-Match؛ رمز فقط از reset مستقل |
| GET/PATCH /api/v1/me/profile | PATCH فقط name,mobile,birth_date,bio,national_id در تکمیل اولیه؛ خروجی self شامل expires_on,active,must_change_password,department,job_title,employee_code و avatar_url مجاز |
| PUT/DELETE /api/v1/me/avatar | multipart field=file یا حذف؛ مسیر مدیریتی /api/v1/system/users/{id}/avatar با Policy یکسان و هدف مجاز |

تاریخ روی شبکه میلادی ISO است ولی در رابط کاملاً شمسی. `expires_on` آخرین روز مجاز در تهران و `expires_at` مرز قطعی زمان UTC سمت سرور است؛ API می‌تواند هر دو را برای رفع ابهام برگرداند. ملی و موبایل اختیاری و رشته‌اند؛ response عمومی کاربر باید PII را حذف کند. Photo مستقل بارگذاری می‌شود؛ اگر ایجاد کاربر موفق و عکس ناموفق شد، UI دقیقاً «کاربر ایجاد شد؛ عکس بارگذاری نشد» بگوید و Retry عکس بدهد، نه اینکه ساخت کاربر را تکرار کند.

## Enumها و تست

priority = critical|high|medium|low؛ test type = functional|regression|smoke|security|performance؛ review status = draft|in_review|approved|returned|archived؛ result status = passed|failed|blocked|skipped و نبود نتیجه = not_run؛ defect status = open|in_fix|resolved|closed|reopened|deferred|rejected.

```json
{
 "project_id":"1","section_id":"2","title":"رد ورود با کد منقضی",
 "priority":"high","type":"functional","assignee_id":"2","substitute_id":null,
 "preconditions":"حساب آزمایشی فعال باشد.","expected_result":"ورود انجام نشود.",
 "checklist":[{"text":"کد منقضی را تأیید کنید.","expected":"پیام انقضا نمایش داده شود.","required":true}],
 "requirement_ids":["1"],"tags":["manual"],"estimate_minutes":5,
 "dataset_id":null,"test_data":{"mobile":"09120000001"},"custom_fields":{"business_area":"هویت"},
 "shared_references":[]
}
```

حد title250، متن مورد1000، checklist1..100، estimate1..1440، tags حداکثر20. TestCaseResource شامل id,code,revision,review_status,version و روابط قابل مشاهده است. referenced shared block باید version مشخص approved و position/step_keys داشته باشد؛ client نمی‌تواند از متن ویرایش‌شده، ارجاع معتبر جعلی بسازد. مقدار فیلد سفارشی و schema نسخه‌دار در Snapshot نگهداری شوند.

## گروه، نقش و مجوز

PUT /projects/{p}/members/{u}/grants و فرم گروه/نقش:

```json
{"name":"طراحان تست","active":true,"user_ids":["2","4"],"permissions":{"cases.create":"allow","cases.update":"deny","defects.force_close":"inherit"},"version":1}
```

فقط کلیدهای catalog مجازند؛ inherit را می‌توان از storage حذف کرد. مجوز category مانند sections.manage می‌تواند همه عملیات بخش را بدهد، ولی deny فرزند مثل sections.delete آن عملیات را می‌بندد؛ deny دسته همه فرزندان را می‌بندد. allow فرزند حق دسته‌های نامربوط ایجاد نمی‌کند. GET me/capabilities پاسخ version و effective{key:boolean} و در صورت درخواست مجاز توضیح sources/reasons بدهد. جزئیات منبع مجوز کاربر دیگر فقط مدیر مجاز ببیند.

نقش پیش‌فرض ثابت است؛ نقش سفارشی و گروه پروژه editable و مجوز مستقیم per-user/per-project هستند. حذف گروه/نقش دارای مصرف بهتر است deactivate یا نیازمند انتقال اعضا باشد. برداشت permission و cache در همان تراکنش acl_version را بالا ببرد.

## پیش‌نمایش، اجرا و نتیجه

POST /projects/{p}/execution-previews با plan_id، configuration_profile_id یا single_context، selected optional variants. خروجی: preview_id، expires_at، signature، unique_case_count، variant_count، variants[{key,case_id,case_revision,configuration,dataset_row,required,review_status,impact_status}]، warnings و blocking_errors. Preview حداکثر۱۰ دقیقه و پروژه/کاربر/مجوز همان زمان را دارد؛ ساخت واقعی دوباره مجوز و تمام نسخه‌ها را بررسی می‌کند.

POST /projects/{p}/executions با preview_id,signature,name,assignee_override_id,selected_variant_keys؛ سرور Snapshot واقعی را می‌سازد، نه اینکه JSON client را معتبر فرض کند. required variant قابل حذف نیست. نتیجه با POST /projects/{p}/execution-items/{item}/results و body status,check_results[{index,status,note}],comment,minutes,attachment_ids,request_id ثبت می‌شود. تاریخ/actor و نتیجه aggregate از سرورند؛ client آن‌ها را تعیین نکند. پیش‌نویس PUT /.../draft هیچ TestResult نمی‌سازد.

POST /projects/{p}/defects/{id}/retest بدون body Snapshot دلخواه؛ از همان item_id مبدأ و defect_id لینک می‌سازد. GET history همه Attemptها را readonly برمی‌گرداند. اجرای تازه و نسخه تازه شناسه مستقل دارند.

## Force close

POST /projects/{p}/defects/{id}/force-close با reason و version؛ پاسخ closure_type=forced,status=closed,verified_at=null,force_closed_by,force_closed_at,force_close_reason. مسیر transitions برای بستن عادی همچنان Pass مرتبط تازه می‌خواهد. نشان اجباری در جدول و جزئیات، گزارش و تاریخچه قابل تشخیص باشد؛ اضافه کردن successful TestResult در هر دو client/server ممنوع است.

## مصرف‌کننده و تفویض

ServiceClient create/update: name,project_ids,scopes,expires_on,rate_limit_per_minute,version. Scope اولیه cases.create و reports.read است. پاسخ create/rotate ممکن است client_id و client_secret یک‌بارمصرف داشته باشد؛ فهرست و GET هرگز secret را بازنگردانند. revoke با reason، token_version را باطل کند.

Delegation create: client_id,delegator_user_id,delegate_user_id,project_ids,scopes,expires_on,reason. پس از صدور immutable؛ revoke و ایجاد مجدد برای تغییر. status مؤثر شامل active/expired/revoked/invalid_scope/inactive_actor/service_disabled و invalid_reasons با مجوز مدیریتی است. expiry از محدودترین والد بلندتر نشود و unknown actor هرگز به مدیر پیش‌فرض fallback نکند.

توکن سرویس:

```text
POST /api/external/token
Authorization: Basic <client_id:client_secret>
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&scope=reports.read&project_id=1
```

تفویض: grant_type=urn:ietf:params:oauth:grant-type:token-exchange و subject_token کاربر گیرنده، subject_token_type=urn:ietf:params:oauth:token-type:access_token، delegation_id و scope/project subset؛ client معتبر هم باید احراز شود. پاسخ استاندارد access_token,token_type=Bearer,expires_in حداکثر300 و scope است؛ این توکن هرگز endpoint داخلی یا تغییر رمز را باز نمی‌کند.

GET `/api/external/v1/test-progress?project_id=1` فقط پروژه مجاز؛ counts وضعیت اقلامِ آخرین baseline و retest متناظر را دارد. مجموع counts = execution_items؛ completed = passed+failed+blocked+skipped؛ progress = completed/total و pass = passed/total، با مخرج صفر، صفر. تازگی جدا در freshness{stale_items,pending_requirement_impacts} و verified_coverage ارزیابی شود؛ Pass با نسخه قدیمی شاهد نیازمندی جدید نیست. defects.closed_forced_unverified از verified جداست و هیچ‌یک counts نتیجه را مصنوعی زیاد نمی‌کنند.

## حداقل پاسخ خطا برای فرانت

validation_failed(422) با fields؛ forbidden(403)؛ account_unavailable/session_revoked(401 در نشست موجود، پیام یکسان هنگام ورود)؛ password_change_required(403)؛ review_required/requirement_impact_pending/preview_changed/record_version_conflict(409)؛ retest_pass_required(409)؛ service_disabled/client_expired/delegation_invalid(403)؛ idempotency_conflict(409)؛ rate_limited(429). HTTP status و code هر دو ثابت باشند؛ UI برای ترجمه به متن آزاد message وابسته نشود.


# افزوده قرارداد مشترک HTTP — نسخه ۴٫۱

GET `/api/v1/projects/{p}/activity`:

```text
from=2026-11-17&to=2026-11-19&actor_id=2&operation=create&entity_type=case&search=...&page=1&per_page=20
```

from/to تاریخ‌های خالص ISO متناظر روز شمسی UI در Asia/Tehran، هر دو inclusive. unknown code، بازه معکوس یا تاریخ ناموجود 422؛ نبود نتیجه 200 data=[]، meta.total=0. event DTO شامل id,occurred_at,actor{id,name},operation_code,entity_type,entity_id,description و request_id مجاز است. API کد enum مستقل از ترجمه می‌دهد، نه اینکه UI متن را برای تشخیص نوع پردازش کند.

GET `/api/v1/system/service-usage` و `/summary` همان queryها به‌علاوه service_key,client_id,project_id,outcome و search(request_id/error_code). outcome = success|client|server|other. project_id این گزارش مدیریت مصرف می‌تواند برای مدیر سامانه خالی و به معنی همه پروژه‌های مجاز باشد؛ با سرویس خارجی test-progress که project_id در آن الزامی است اشتباه نشود.

RequestUsageResource:

```json
{"request_id":"uuid","occurred_at":"2026-11-19T09:00:00Z","service_key":"reports.read","method":"GET","client_id":"c-1","client_name":"مصرف‌کننده گزارش","project_id":"1","project_name":"فروشگاه","status_code":200,"outcome":"success","duration_ms":75,"response_bytes":1500,"error_code":null,"delegation_id":null,"data_source":"actual"}
```

هیچ headers/body/secret/token به DTO اضافه نشود. summary شامل snapshot_as_of,total,success_count,client_error_count,server_error_count,other_count,success_percent,median_ms,p95_ms,by_service,daily_trend است. snapshot_as_of را به درخواست فهرست نیز بدهید تا دامنه زمانی یکسان باشد. صفحۀ فهرست هرچقدر کوچک باشد، summary مخرج کل فیلتر است. source=sample فقط fixture توسعه و با نمایش صریح، هیچ رکورد sample داخل آمار عملیاتی actual نباشد.

قرارداد permissions بدون تغییر است: input Radio/Segmented فرانت فقط نمای جدید DTO permissions{key:inherit|allow|deny} است. نام checkboxهای انتخاب چندگانه و checked boolean Switch را به متن یا عدد قراردادی تبدیل نکنید. چاپ/API dataset باید همان scope و permission و filterهای JSON را رعایت کند.


## L. افزوده طراحی نسخه ۴٫۱ — ده اصلاح پذیرفته‌شده

### ۱. تاریخ بدون هم‌پوشانی

کنترل عددی تاریخ همیشه direction=ltr داشته باشد. متن و آیکون تقویم دو سلول مستقل Grid یا Input suffix استاندارد Ant Design با فضای واقعی جدا هستند؛ از آیکون absolute روی متن با padding وابسته به جهت متفاوت استفاده نکنید. در هر دو RTL/LTR و عرض320px، انتهای تاریخ دیده شود. focus-visible، حالت خطا و بازشدن تقویم باقی بماند. تاریخ نامعتبر در فیلتر نباید با فیلتر معتبر قبلی بی‌صدا خروجی بسازد: مقدار کاربر و پیام خطا بماند، گزارش/خروجی مسدود و پاک کردن فیلتر ممکن باشد.

### ۲ و ۳. یک منوی حساب در آواتار هدر

بخش پروفایل و دکمه‌های پروفایل/تنظیمات/خروج از پایین سایدبار حذف و یک Avatar Trigger بالای صفحه اضافه شود. منوی Popup چهار هدف روشن دارد: پروفایل من، تغییر رمز، ظاهر و زبان، خروج. پروفایل به بخش حساب و ظاهر/زبان به ترجیحات می‌رود؛ دکمه تکراری با مقصد یکسان نسازید. مشخصات کاربر در سربرگ منو، aria-haspopup/menuitem/expanded، ArrowUp/Down، Home/End، Escape، click-away و بازگشت focus لازم‌اند. منو داخل viewport بماند و روی resize بسته یا دوباره مکان‌یابی شود.

در موبایل آواتار باقی بماند؛ میان‌برهای تکراری تم/زبان هدر می‌توانند مخفی و از منو قابل دسترس باشند تا هدر سرریز نکند. اگر تغییر رمز اجباری است، فقط تغییر رمز و خروج فعال باشند. پایین سایدبار از حساب و تبلیغ زائد خلوت شود. اطلاعات تکراری بالای صفحه حساب نیز ادغام شود.

### ۴. عکس پروفایل

عکس و ابزارها Grid با ستون ثابت پیش‌نمایش و ستون انعطاف‌پذیر ابزار داشته باشند. انتخاب و حذف در Flex wrap مستقل، توضیح محدودیت در خط زیر آن‌ها؛ همه برچسب‌ها خوانا، فاصله یکنواخت و هدف لمس مناسب. حذف بدون عکس غیرفعال و بعد از بارگذاری/حذف به‌روز شود. در موبایل باریک زیر380px، عکس و ابزار می‌توانند یک‌ستونی شوند. منطق اعتبارسنجی/برش/بازکدگذاری و سیاست دسترسی نسخه۴ حفظ شود.

### ۵. Switch برای حالت‌های دوتایی

حساب فعال، مدیر سامانه، اجبار تغییر رمز، فعال بودن گروه/فیلد، الزامی بودن مورد چک‌لیست یا ردیف پیکربندی، ترجیح اعلان، حالت نمونه گزارش و گزینه‌های چاپ از Switch استفاده کنند. value boolean واقعی و role=switch با aria-checked، Space و focus-visible لازم است. فهرست انتخاب تست/کاربر/پروژه/Scope/ستون‌ها همچنان Checkbox چندانتخابی است؛ این‌ها را Switch نکنید. Radioهای مجوز نیز Switch نیستند.

### ۶. حذف برچسب تزئینی زائد

Eyebrowهای AFRA / TEAM و هم‌خانواده آن از بالای عنوان صفحه حذف شوند. عنوان اصلی، نام پروژه، breadcrumb و شناسه عملکردی تست/اجرا/خطا حفظ شوند. شعار انگلیسی تزئینی ورود نیز در صورت تکرار حذف شود؛ این درخواست مجوز حذف اطلاعات دامنه یا کد موجودیت نیست.

### ۷. رویداد پروژه با فیلتر

بازه تاریخ شمسی شامل دو سر، انجام‌دهنده، نوع عملیات، نوع موجودیت، جستجوی شرح/شناسه، پاک کردن، شمارنده و pagination20تایی. تمام فیلترها قابل ترکیب‌اند و تغییرشان صفحه را به1 بازمی‌گرداند. source داده، پروژه جاری و بخش‌های قابل مشاهده است. actor غیرفعال ولی دارای سابقه در گزینه‌ها باقی بماند. صفر نتیجه از خطای فیلتر متمایز باشد. CSV/PDF تمام سطرهای فیلترشده مجاز را برگرداند. نوع عملیات از API code مستقل از زبان بیاید؛ تشخیص متنی فقط برای داده قدیمی نمونه است.

### ۸. مجوز گروه‌بندی‌شده با انتخاب آیکون‌دار

مجوزهای نسخه۴ بر اساس منبع گروه شوند: پروژه، تست، بخش، مراحل مشترک، قالب/داده، نیازمندی، برنامه/پروفایل/اجرا، خطا، کیفیت، همکاری و مدیریت دسترسی. گروه‌ها collapsible با عنوان و تعداد؛ باز/جمع کردن همه و جستجو بدون از دست رفتن مقدار. حالت انتخاب هر مجوز سه‌قسمتی و مجزا از عنوان عملیات است: ارث‌بری با نماد پیوند، مجاز با تیک، منع با ضربدر. Legend متنی و Tooltip داشته باشد؛ رنگ تنها نشانه نباشد.

از Radio Group/Segmented کنترل‌شده استفاده کنید، با نام یکتا برای هر operation و مقادیر inherit/allow/deny؛ ناوبری با Arrow و اعلام صحیح screen reader. خروجی همان DTO قبلی است، نه تغییر معنای ارث‌بری یا deny. هنگام جستجو عناصر فقط پنهان شوند و فرم مقادیر همه گروه‌ها را نگه دارد؛ unregister شدن فیلد مخفی نباید grant را حذف کند. در موبایل انتخابگر ثابت و متن عملیاتی انعطاف‌پذیر باشد، نه فشرده شدن متن به یک حرف.

### ۹. گزارش مصرف وب‌سرویس

تب زیر فضای «پروژه و افراد»، فقط مدیر دارای system.services.manage؛ منوی اصلی هشتم نسازید. از کاتالوگ سرویس به این گزارش پیوند باشد. فیلتر زمان، سرویس، مصرف‌کننده، پروژه، outcome و جستجوی request_id/error_code؛ شمارنده، pagination، جزئیات، CSV و PDF. کارت‌ها تعداد، درصد2xx، میانه و p95 با واحد میلی‌ثانیه و مخرج روشن‌اند. شکست4xx و5xx و سایر پاسخ‌ها جدا باشند. خلاصه از تمام filtered dataset است، نه صفحه جاری.

در HTML آفلاین، ledger واقعی خالی است و نمونه آموزشی با Switch جدا و Banner آشکار ارائه می‌شود؛ Sample data با Actual مخلوط نشود و export نوع منبع را بنویسد. در React عملیاتی، API واقعی source است؛ fixture فقط در حالت توسعه صریح و با همان نشان آموزشی. هیچ درخواست واقعی در نمونه شبیه‌سازی موفق و ثبت عملیاتی اعلام نشود.

جزئیات فقط whitelist metadata است؛ Authorization، Cookie، request/response body، رمز، JWT و client_secret حتی برای مدیر نمایش داده نشوند. client لغوشده همچنان سابقه قابل گزارش دارد. خلاصه و جدول با همان scope و snapshot_as_of به‌روز شوند؛ درخواست فیلتر قبلی cancel/ignore شود تا نتیجه دیررس جای فیلتر تازه ننشیند.

### ۱۰. داشبورد و چاپ

نمای کلی دارای Hero نام پروژه، فیلتر انتشار، شش KPI با مخرج، پیشرفت اقلام، پوشش تأییدشده، خطاهای بحرانی و انتشار آماده؛ صف اقدام برای بازبینی، اثر تغییر، نتیجه قدیمی، بسته اجباری و نبود مسئول فعال؛ روند شش اجرای واقعی اخیر، کارهای من، بار تیم، آمادگی انتشار و آخرین رویدادها. صفر داده، empty state روشن باشد. تعداد تست یکتا با اقلام چندپیکربندی فرق دارد. Pass قدیمی روی نسخه تازه نیازمندی، پوشش معتبر نسازد. Drill-down کارت باید همان فهرست شمارش‌شده را باز کند.

چاپ از dataset مجاز گزارش و DOM مستقل ساخته شود، نه screenshot یا window.print روی صفحه پر از منو. پیش‌نمایش شامل عنوان قابل تنظیم، portrait/landscape A4، جزئیات و نمودار اختیاری، نام پروژه و فیلترها، زمان شمسی/تهران، تهیه‌کننده، نسخه، منبع و reference باشد. برای PDF، مقصد Save as PDF مرورگر توضیح داده شود. font loading پیش از چاپ کامل شود و تغییر تم داده/متن گزارش را خراب نکند.

Print stylesheet تمام app/overlay/منو/toast را مخفی و کاغذ سفید با متن تیره، حاشیه مناسب، لوگو، header و footer، شمارنده صفحه در مرورگر پشتیبان، سرستون تکراری و جلوگیری از شکستن ردیف بدهد. متن بلند و شناسه wrap شوند؛ ارتفاع ثابت یا overflow:hidden سطرها را قطع نکند. ماتریس عریض به چند بلوک ستون با تکرار ستون نیازمندی تقسیم شود، نه حذف ستون یا scale غیرقابل خواندن. خروجی Activity/Usage تمام سطرهای فیلترشده و اجرای انتخاب‌شده Snapshot واقعی را نشان دهد. Force close به‌عنوان تأیید رفع چاپ نشود.

نمونه PDF تحویلی از همان report markup با PDF renderer مستقل تولید شده؛ آزمون مرورگر واقعی/همه چاپگرها محسوب نمی‌شود. Page margin counters وابسته به پشتیبانی مرورگرند؛ preview و نام/زمان/دامنه داخل محتوای گزارش همیشه باقی بمانند. پیش‌نمایش در دارک عمداً کاغذ روشن است، نه اینکه زمینه کل سامانه دوباره روشن شود.

آزمون اضافه: آیکون تاریخ و متن در RTL/LTR و320px، یک منوی آواتار و مسیرهای متفاوت، عکس/دکمه wrap، Switch در برابر multi-select Checkbox، Radio keyboard/search حفظ مقدار، فیلتر تاریخ نامعتبر و timezone، sample/actual و PII leakage، p95/میانه، scope-before-page، baseline مخرج، stale Pass، چاپ تمام48 سطر نمونه و جدول چندصفحه با header/page numbers.


## جدول کامل مسیرهای مشترک


| گروه | مسیر و عملیات |
|---|---|
| حساب | GET/PATCH /api/v1/me/profile، PUT/DELETE /me/avatar، GET /me/sessions، DELETE /me/sessions/{id} |
| کاربران سامانه | GET/POST /api/v1/system/users، GET/PATCH /users/{id}، POST /users/{id}/reset-password، POST /users/{id}/revoke-sessions |
| پروژه و عضو | GET/POST /projects، GET/PATCH /projects/{id}، GET/POST/PATCH/DELETE /projects/{id}/members/{user?} |
| ساختار | GET/POST/PATCH/DELETE /projects/{p}/sections/{id?}؛ انتقال با بررسی چرخه و تعلق پروژه |
| تست | GET/POST /projects/{p}/test-cases، GET/PATCH/DELETE /test-cases/{id}، POST /{id}/assign، GET /{id}/revisions، POST /{id}/review-requests، POST /{id}/review-decisions |
| نیازمندی | CRUD /projects/{p}/requirements، GET /{id}/revisions، GET /{id}/impacts، POST /compatibility-confirmations |
| قالب/بلوک/داده | CRUD /templates، /shared-blocks، /datasets؛ نسخه و بازبینی مشترک مسیر مستقل داشته باشد |
| برنامه | CRUD /test-plans و /configuration-profiles، POST /execution-previews، POST /executions با preview_id و signature |
| اجرا | GET /executions، GET /executions/{id}، POST /execution-items/{id}/results، PUT/DELETE /execution-items/{id}/draft، POST /executions/{id}/pause، /resume، /retests |
| خطا | CRUD مجاز /defects، POST /{id}/transitions، POST /{id}/retest، POST /{id}/force-close، GET /{id}/history |
| دسترسی | GET catalog /permissions، CRUD /projects/{p}/roles و /access-groups، PUT /groups/{g}/members، PUT /members/{u}/grants، CRUD /section-access-rules |
| کیفیت | CRUD /releases، /environments، /builds؛ GET /reports/progress، /traceability، /configuration-coverage، /release-readiness |
| فایل/همکاری | POST/GET/DELETE /attachments، GET/POST /comments، POST /follows، GET/PATCH /notifications |
| انتقال | POST /imports/preview، POST /imports/{id}/commit؛ GET /exports/{kind}؛ مجوز خروجی جداگانه |
| سرویس | CRUD /system/service-clients، POST /{id}/rotate-secret و /revoke؛ GET/PATCH /system/service-catalog/{key}؛ GET/POST /system/delegations و POST /{id}/revoke |

همه مسیرهای محصول زیر `/api/v1` و تا جای ممکن project-scoped باشند. مسیر منبع بدون project هم با Policy از همان تعلق استفاده کند؛ وجود شناسه صحیح کافی نیست. جداول مشترک و ترتیب والدهای route در OpenAPI دقیقاً ثبت شوند؛ aliasهای ناسازگار در فرانت ساخته نشوند.


# شرح کامل دامنه محصول — جزء مستقل همین سند

تمام ماژول‌ها، داده نمونه و قواعد پذیرش در ادامه همین فایل آمده‌اند. قرارداد فنی بالا و بخش‌های نسخه۴ در تعارض مقدم‌اند. برای فهم دامنه نیازی به سابقه گفتگو نیست.


# افرا — شرح جامع سیستم و مرجع بازسازی طراحی

نسخه سند: **4.1**  
تاریخ: **۱۴۰۵/۰۸/۲۸**  
نوع محصول: سامانه مدیریت کیفیت و تست **دستی** نرم‌افزار  
نام فارسی: **افرا** — نام انگلیسی: **Afra**  
فایل مرجع همراه: `afra-ui-v4.html`

> این سند مستقل از گفتگو است. برای تحلیل، طراحی یا پیاده‌سازی بعدی، کل سند را به‌عنوان مرجع نیازمندی‌ها در نظر بگیرید. موارد «الزامی»، قواعد دامنه و استثناها را تغییر ندهید. این متن رفتار و ظاهر هدف را مشخص می‌کند؛ تولید بایت‌به‌بایت یا پیکسل‌به‌پیکسل یکسان توسط مدل‌های مختلف تضمین نمی‌شود. برای بازتولید دقیق نسخه تحویل‌شده، خود فایل HTML همراه، مرجع اجرایی است.

## 1. دستور کار برای AI یا تیم توسعه

سامانه‌ای به نام **افرا** بسازید که تمام دامنه این سند را پوشش دهد. هویت نام‌گذاری مجموعه سامانه‌ها از درختان گرفته شده است؛ سامانه دیگر این مجموعه «توسکا» نام دارد. نام این محصول فقط «افرا» است؛ از نام‌های موقت Testflow یا سرو در محصول استفاده نکنید.

دو خروجی متمایز وجود دارد:

1. **نمونه طراحی UI:** یک فایل HTML مستقل، قابل باز شدن مستقیم در مرورگر، دارای CSS، JavaScript، فونت و آیکون داخلی، داده نمونه، صفحات و تعامل‌های کاربردی. به نصب، اینترنت یا بک‌اند نیاز نداشته باشد. رفتارهای ورود، مجوز و ذخیره محلی صریحاً نمایشی باشند.
2. **محصول عملیاتی آینده:** بک‌اند Laravel 13 با REST داخلی و فرانت‌اند جداگانه React + TypeScript + Ant Design با JWT. اعتبارسنجی و کنترل دسترسی واقعی در سرور انجام شود. نمونه HTML جایگزین پیاده‌سازی محصول عملیاتی نیست.

از ساخت صرفاً یک داشبورد، دکمه‌های بدون عملکرد، صفحات خالی با عنوان «به‌زودی» و نمودار با داده بی‌منبع خودداری کنید. CRUDهای اصلی، ارتباط اشیا، گردش وضعیت‌ها، ثبت نتیجه، حفظ تاریخچه، فرم‌ها، جستجو و خروجی فایل باید در نمونه طراحی قابل تجربه باشند. داده‌های نمایشی را مشخص کنید.

### خارج از دامنه، به درخواست صریح مالک

- اتصال به Jira، GitLab یا سایر سامانه‌های مدیریت کار؛
- ورود سازمانی، SSO، LDAP، SAML یا OIDC؛
- Webhook و اتصال آماده به ابزارهای بیرونی؛ API بیرونی محدود و مدیریت‌شده ثبت تست و گزارش هر پروژه با درخواست جدید داخل دامنه است؛
- دریافت نتایج ابزارهای تست خودکار؛
- CI/CD، اجرای اسکریپت تست، زمان‌بندی تست خودکار، عامل اجرایی یا هر قابلیت مربوط به اتوماسیون تست.

**تمایز معماری نسخه ۴:** REST داخلی Laravel با JWT برای React لازم است. به درخواست جدید، ارائه سرویس بیرونی ثبت تست و گزارش پیشرفت هر پروژه با مدیریت مصرف‌کننده، مجوز و تفویض نیز داخل دامنه است. Webhook، SSO و تست خودکار همچنان خارج دامنه‌اند. آزمون فنی خود کد توسعه، قابلیت محصول برای اجرای تست خودکار محسوب نمی‌شود.

## 2. هدف و زنجیره اصلی محصول

زنجیره محصول چنین است:

**پروژه ← نیازمندی ← تست‌کیس و چک‌لیست ← بازبینی/تأیید نسخه ← پلن ← اجرای دستی ← نتیجه و شواهد ← خطا ← رفع ← تست مجدد ← تأیید رفع ← ارزیابی انتشار**

کاربر باید بتواند پاسخ دهد:

- چه چیزی باید تست شود؟ نیازمندی‌ها و تست‌های مرتبط.
- تست در کدام بخش محصول قرار دارد؟ درختواره بخش‌ها.
- چه کسی مسئول است و جانشین او کیست؟ تخصیص تست.
- چه نسخه‌ای از سناریو و کدام Build بررسی شده؟ نسخه تست و زمینه اجرا.
- دقیقاً کدام مورد چک‌لیست Pass یا Fail شده؟ نتایج جزئی.
- چرا تست شکست خورده و مدرک آن چیست؟ یادداشت، فایل و خطای متصل.
- آیا رفع خطا واقعاً بررسی شده؟ تست مجدد و تاریخچه.
- آیا محصول آماده انتشار است؟ شروط و گزارش آمادگی انتشار.

## 3. پروژه و درختواره بخش‌ها

### پروژه

فیلدها: شناسه داخلی، نام، کلید کوتاه یکتا مانند `SHOP`، توضیح، وضعیت Active/Archived، اعضا و نقش هر عضو، زمان ایجاد و تغییر.

- مدیر سامانه پروژه ایجاد می‌کند و ادمین اولیه را تعیین می‌کند.
- نقش یک کاربر در پروژه‌های مختلف مستقل است.
- پروژه بایگانی‌شده باید از پروژه فعال قابل تفکیک باشد. در محصول واقعی بایگانی، نوشتن و شروع اجرای جدید را متوقف می‌کند؛ خواندن تاریخچه حفظ می‌شود.
- کلیدهای نمایشی مثل `SHOP-001`، `PLAN-001`، `RUN-001`، `REQ-001` و `BUG-001` پایدار باشند.
- اشیای مرتبط، باید متعلق به یک پروژه باشند؛ اتصال تست یک پروژه به بخش، پلن، نیازمندی یا Build پروژه دیگر مجاز نیست.

### بخش‌ها

- بخش، فیلد `parent_id` اختیاری دارد؛ درخت باید عمق‌پذیر باشد.
- ایجاد ریشه، ایجاد زیربخش، تغییر نام و انتخاب والد جدید لازم است.
- انتخاب خود بخش یا یکی از فرزندان آن به‌عنوان والد ممنوع است.
- حذف بخش دارای تست یا زیربخش ممنوع است؛ ابتدا انتقال یا بایگانی/حذف مجاز محتوا انجام شود.
- شمارنده هر گره شامل تست‌های خود بخش و همه زیرشاخه‌ها است.
- انتخاب گره، فهرست تست‌های آن گره و زیرشاخه‌ها را فیلتر می‌کند.
- جابه‌جایی گروهی تست‌ها بین بخش‌های همان پروژه لازم است. تاریخچه اجراهای قبلی تغییر نکند.
- برای ورود گروهی، `SectionPath` مثل `فرآیند خرید / پرداخت` مسیر بخش را مشخص می‌کند. ایجاد بخش‌های ناموجود فقط پس از تأیید نهایی ورود و با مجوز مدیریت مجاز است.

## 4. کاربران، نقش‌ها و مجوزها

### حساب کاربری

نام، ایمیل یکتا، وضعیت حساب، نقش مدیر سامانه یا کاربر عادی. حساب کاربری مستقل از عضویت پروژه است. محصول واقعی رمز عبور را هش می‌کند؛ نسخه طراحی علاوه بر انتخاب کاربر نمونه، گردش رمز اولیه/تغییر/بازنشانی را با Hash محلی نمایشی نشان می‌دهد و نباید به‌عنوان احراز هویت امن استفاده شود.

### نقش‌های پیش‌فرض پروژه

| نقش | مشاهده | تعریف محتوا | ثبت نتیجه | اعضا و دسترسی | بازبینی |
|---|---|---|---|---|---|
| ادمین پروژه | همه بخش‌های پروژه | بخش، تست، نیازمندی، پلن، خطا، انتشار و محیط | همه تست‌های پروژه | بله | با رعایت استقلال نویسنده و بازبین |
| تستر | اطلاعات مجاز پروژه | خطای دستی و شواهد/گفتگو؛ نه تعریف تست یا بخش | فقط تست تخصیص‌یافته یا با جانشینی او | خیر | فقط اگر مجوز جداگانه داده شود |
| بازبین | اطلاعات مجاز پروژه | نظر بازبینی؛ بدون تغییر سناریو مگر مجوز جداگانه | خیر، مگر مجوز اجرا و تخصیص داشته باشد | خیر | نسخه‌های ارجاع‌شده به خودش |
| مشاهده‌گر | اطلاعات و گزارش‌های مجاز | خیر | خیر | خیر | خیر |

مدیر سامانه همه پروژه‌ها را مدیریت می‌کند. او نیز نباید نسخه‌ای را که خودش نویسنده آخرین تغییر آن بوده است، شخصاً تأیید کند.

### نقش سفارشی

مجوزهای قابل انتخاب:

`view`, `manageCases`, `managePlans`, `manageRequirements`, `manageDefects`, `manageReleases`, `manageMembers`, `review`, `execute`, `viewReports`.

- نام نقش کاربرساخته و محلی به پروژه است.
- حذف نقش دارای عضو ممنوع است.
- مجوز مدیریت محتوا به‌تنهایی مجوز مدیریت اعضا نیست.
- مشاهده صفحه با مجوز ذخیره یا انجام عمل تفاوت دارد؛ هم دکمه‌ها و هم عملیات سرور کنترل شوند.

### دسترسی در سطح بخش

قاعده برای زوج «کاربر + بخش» شامل مشاهده، ویرایش تست و اجرا است. هر مقدار یکی از Allow / Deny / Inherit است.

1. از مجوز نقش پروژه شروع کنید.
2. قواعد از ریشه تا بخش هدف اعمال شوند.
3. نزدیک‌ترین قاعده صریح بر والد غلبه کند؛ Inherit مقدار قبلی را حفظ کند.
4. مدیر سامانه و ادمین پروژه از محدودیت بخش مستثنا هستند.
5. مجوز Execute بدون تخصیص یا جانشینی، برای تستر کافی نیست.
6. داده بخش غیرمجاز در فهرست، جستجو، خروجی، گزارش، پیوست و جزئیات نیز فاش نشود.

### تخصیص و جانشین

- هر تست‌کیس یک مسئول اصلی و یک جانشین اختیاری دارد؛ انتخاب فقط از اعضای واجد مجوز اجرای همان پروژه.
- در ایجاد اجرا، می‌توان مسئول هر تست را حفظ کرد یا یک مسئول پیش‌فرض برای همه اقلام انتخاب کرد.
- تخصیص ثبت‌شده روی اقلام اجرا مستقل از تغییر بعدی مسئول تست‌کیس است.
- جانشین در دامنه همان تست اجازه ثبت نتیجه دارد؛ رویداد ثبت‌کننده واقعی نتیجه باید نام جانشین را نشان دهد.
- تخصیص گروهی تست‌ها لازم است.
- آخرین ادمین پروژه قابل حذف یا تنزل نیست؛ مدیر سامانه نقش مدیریت خودش را از همین نشست حذف نکند.

## 5. Test Case و چک‌لیست

### فیلدهای تست‌کیس

- عنوان، پروژه، بخش؛
- توضیح/پیش‌نیاز، نتیجه مورد انتظار؛
- اولویت: Critical / High / Medium / Low؛
- نوع: Functional / Regression / Smoke / Security / Performance؛ همه **دستی** هستند؛
- مسئول اصلی، جانشین؛
- برچسب‌ها، زمان تخمینی به دقیقه؛
- داده/متغیرهای آزمایشی به‌صورت متن و اتصال به مجموعه داده؛
- ارتباط چندبه‌چند با نیازمندی‌ها؛
- چک‌لیست مرتب؛
- وضعیت بازبینی، شماره نسخه، نویسنده آخرین تغییر، بازبین، زمان تأیید؛
- پیوست‌ها، گفتگو، دنبال‌کننده‌ها و تاریخچه تغییر.

### هر مورد چک‌لیست

شناسه پایدار در نسخه عملیاتی، ترتیب، متن بررسی، نتیجه مورد انتظار همان مورد، Required یا Optional.

در اجرا علاوه بر وضعیت مورد، یادداشت و شواهد مستقل آن مورد ثبت می‌شود. موارد جدید، حذف‌شده و جابه‌جاشده فقط نسخه‌های جدید تست را تغییر دهند؛ اجرای قبلی همچنان چک‌لیست قبلی را نمایش دهد.

### عملیات

ایجاد، مشاهده، ویرایش، تکثیر، استفاده از قالب، ذخیره به‌عنوان قالب، جابه‌جایی بخش، تخصیص تکی و گروهی، جستجو، فیلتر بخش/اولویت/مسئول، نسخه‌ها، مقایسه، بازبینی، پیوست، گفتگو، دنبال کردن و بایگانی.

در نسخه عملیاتی، تست استفاده‌شده در اجرا بهتر است soft-delete یا بایگانی شود. حذف تست نباید نتیجه یا Snapshot اجرای تاریخی را حذف کند.

## 6. نسخه‌بندی و بازبینی تست

### نسخه محتوا

هر ذخیره محتوایی یک شماره نسخه افزایشی می‌سازد. Snapshot نسخه شامل عنوان، پیش‌نیاز، چک‌لیست و مشخصات Required/Optional/Expected، نتیجه کلی مورد انتظار، برچسب، داده تست، زمان تخمینی، بخش و تخصیص است.

- Snapshot نسخه نباید تاریخچه نسخه‌های دیگر را به‌صورت بازگشتی در خود داشته باشد.
- فیلدهای تاریخچه: شماره نسخه، نویسنده، زمان، علت تغییر، Snapshot.
- مقایسه دو نسخه انتخابی، مقادیر قدیم و جدید را کنار هم و موارد متفاوت را با زمینه نارنجی ملایم نشان دهد.
- ویرایش محتوای نسخه تأییدشده، وضعیت را به Draft برمی‌گرداند؛ تأیید نسخه قدیم به نسخه جدید منتقل نشود.

### گردش بازبینی

`Draft → In Review → Approved`  
`In Review → Changes Requested → Draft/In Review`  
`Draft/Approved → Archived` با مجوز مدیریت.

قواعد:

- درخواست‌کننده، بازبین واجد مجوز را انتخاب می‌کند.
- نویسنده آخرین تغییر محتوا نمی‌تواند همان نسخه را تأیید کند.
- فقط بازبین انتخاب‌شده با مجوز Review می‌تواند تصمیم بگیرد.
- برگشت برای اصلاح، توضیح اجباری دارد.
- تأیید شامل نام، زمان و شماره دقیق نسخه است.
- هر تصمیم در تاریخچه می‌ماند و به نویسنده اعلان می‌شود.
- اجرای جدید از تست‌های تأییدنشده شروع نشود. پلن می‌تواند تست پیش‌نویس داشته باشد، ولی شروع اجرا با خطای روشن مسدود شود؛ تست تأییدنشده بی‌صدا حذف نشود.

## 7. قالب‌ها و داده‌های تست دستی

### قالب تست

نام، نوع، پیش‌نیاز، چک‌لیست و نتیجه مورد انتظار؛ اختصاصی پروژه. عملیات ایجاد، ویرایش، مشاهده، استفاده برای تست جدید و ساخت قالب از تست موجود.

استفاده از قالب، محتوای آن را در فرم کپی می‌کند؛ تست‌های قبلاً ساخته‌شده با تغییر قالب تغییر نمی‌کنند.

### مجموعه داده

نام، نام ستون‌ها، رکوردهای مقدار و توضیح. برای سناریوهای معتبر/نامعتبر، حساب‌ها و داده‌های ساختگی استفاده شود. تست‌کیس می‌تواند به یک مجموعه داده متصل شود. قابلیت اجرای خودکار یا تکرار خودکار بر اساس داده اضافه نشود.

نمونه:

| scenario | mobile | code |
|---|---|---|
| valid | 09120000001 | 123456 |
| invalid | 09120000002 | 000000 |

داده واقعی حساس، رمز تولید یا اطلاعات شخصی مشتری در نمونه قرار ندهید.

## 8. Requirements

فیلدها: شناسه، عنوان، توضیح روشن، اولویت، وضعیت Draft/Approved/Archived، نسخه هدف، پیوست و گفتگو.

- ارتباط چندبه‌چند با تست‌ها از هر دو سمت قابل مدیریت باشد.
- تغییر ارتباط از فرم نیازمندی باید در تست‌کیس هم منعکس شود و بالعکس.
- نیازمندی بدون تست، دارای تست تأییدنشده و دارای تست تأییدشده اما بدون Pass، سه وضعیت متفاوت‌اند.
- اتصال نیازمندی‌ها به انتشار لازم است.
- حذف/بایگانی نیازمندی، تاریخچه اجرای تست را تغییر ندهد.

## 9. Test Plan

فیلدها: نام، توضیح، نسخه هدف، انتشار مرتبط، مالک، موعد، وضعیت Draft/Ready/Archived، محدوده، شرط شروع، شرط پایان و فهرست تست‌کیس‌ها.

- انتخاب چند تست از بخش‌ها؛ حداقل یک تست برای پلن قابل اجرا.
- مشاهده تست‌های پلن و اجراهای مرتبط.
- کپی پلن برای انتشار بعدی؛ کپی در وضعیت Draft ساخته شود و ارجاعات تست به‌صورت قابل ویرایش کپی شوند.
- شروط شروع/پایان متن قابل بازبینی انسانی هستند؛ نباید وانمود شود موتور هوشمند آن‌ها را خودکار فهمیده است.
- حذف پلن، اجرا و نتایج قبلی را حذف نکند؛ نام پلن در Snapshot زمینه اجرا حفظ شود.

## 10. انتشار، محیط، Build و زمینه اجرا

### Release

نام، شماره نسخه یکتا در پروژه، توضیح، موعد، مالک، وضعیت Draft/Planned/Released/Archived، نیازمندی‌های هدف و پلن‌های مرتبط.

### Environment

نام، نوع Development/Staging/Production، URL، مرورگر پیش‌فرض، سیستم‌عامل، دستگاه و توضیح. URL فقط برای شناسایی محیط است؛ اتصال بیرونی، بررسی سلامت خودکار یا اجرای تست از روی URL وجود ندارد.

### Build

شناسه نسخه/Build، محیط مرتبط، تاریخ و یادداشت. شناسه Build در یک محیط یکتا باشد.

### زمینه اجرای ثبت‌شده

در ایجاد اجرا این موارد ذخیره شوند:

- انتشار و نام/شماره آن؛
- محیط و نام/نوع آن؛
- Build و شناسه نسخه آن؛
- مرورگر و نسخه، سیستم‌عامل، دستگاه؛
- موعد اجرا، ایجادکننده و زمان؛
- پلن و نام آن؛
- Snapshot نسخه هر تست و مسئول/جانشین همان اجرا.

این اطلاعات **کپی مستقل** هستند؛ ویرایش محیط، Build، پلن، تست یا مسئول اصلی در آینده، زمینه تاریخی اجرا را عوض نکند.

## 11. Test Execution و ثبت نتیجه

### ایجاد اجرا

از پلن انتخابی، همه تست‌های معتبر و تأییدشده را Snapshot کنید. کنترل تعلق به پروژه و دسترسی بخش الزامی است. حداقل یک تست لازم است. انتخاب محیط و Build هم‌پروژه، نام اجرا و مسئول پیش‌فرض/مسئول خود تست لازم است.

### صفحه اجرا

- بالای صفحه: نام اجرا، پلن، وضعیت، پیشرفت و شمارنده‌ها؛
- نوار زمینه: محیط، Build، مرورگر، OS، دستگاه، موعد و انتشار؛
- فهرست تست‌های اجرا و نتیجه فعلی در ستون سمت راست؛
- جزئیات نسخه ذخیره‌شده تست، پیش‌نیاز، چک‌لیست و نتیجه مورد انتظار در فضای اصلی؛
- دکمه مستقل Pass/Fail برای هر مورد؛ Skip فقط برای مورد Optional؛
- یادداشت و شواهد هر مورد؛
- نتیجه کلی، توضیحات، زمان واقعی، ثبت نتیجه، تست بعدی و تاریخچه؛
- پیوست/گفتگو، ساخت خطا از Fail، توقف/ادامه و تست مجدد موارد ناموفق.

### وضعیت‌ها

| وضعیت | معنی |
|---|---|
| Not Run | هنوز هیچ نتیجه‌ای برای این مورد/تست ثبت نشده |
| Pass | بررسی انجام شده و نتیجه مورد انتظار محقق است |
| Fail | بررسی انجام شده و رفتار مورد انتظار محقق نیست |
| Blocked | مانعی مثل آماده نبودن محیط، اجازه ارزیابی نداده |
| Skipped | تست با تصمیم آگاهانه و دلیل مشخص اجرا نشده |

### قواعد نتیجه نهایی

1. نتیجه کلی باید صریحاً انتخاب یا از چک‌لیست پیشنهاد و سپس با «ثبت نتیجه» تأیید شود.
2. برای **Pass** همه موارد Required باید Pass باشند؛ Optional فقط Pass یا Skipped باشد. هیچ مورد Fail مجاز نیست.
3. برای **Fail** وضعیت همه موارد باید مشخص باشد؛ Optional می‌تواند Skipped باشد. اگر هیچ مورد Fail ندارد ولی نتیجه کلی Fail انتخاب می‌شود، توضیح الزامی است.
4. برای Blocked و Skipped توضیح الزامی است؛ موارد بررسی‌نشده می‌توانند Not Run بمانند.
5. انتخاب Pass روی چک‌لیست دارای Fail با پیام روشن مسدود شود.
6. دکمه «همه موارد موفق» فقط پیش‌نویس را تغییر می‌دهد؛ تا زدن ثبت نتیجه، Attempt جدید ساخته نمی‌شود.
7. زمان واقعی غیرمنفی و معقول، به دقیقه باشد؛ نمونه سقف 1440 دقیقه دارد.
8. هر بار ثبت، **Attempt جدید غیرقابل بازنویسی** می‌سازد. نتیجه قبلی حذف یا ویرایش نشود.
9. نتیجه فعلی هر تست در یک اجرا، آخرین Attempt همان قلم است.
10. تاریخچه باید نسخه تست، زمان، ثبت‌کننده، نتیجه کلی و جزئی، یادداشت، شواهد و زمان صرف‌شده را نشان دهد.

### توقف، ادامه و پیش‌نویس

- توقف توسط دارنده مجوز مدیریت اجرا انجام می‌شود؛ ثبت نتیجه در حالت Paused ممنوع است.
- پیش‌نویس هر کاربر برای زوج اجرا/قلم مستقل است. جابه‌جایی تست، صفحه یا کاربر نباید آن را از بین ببرد.
- پیش‌نویس با نتیجه ثبت‌شده تفاوت ظاهری دارد. حذف پیش‌نویس، آخرین نتیجه ثبت‌شده را حذف نمی‌کند.
- «تست بعدی» نباید پیش‌نویس تست فعلی را از بین ببرد.

### تست مجدد

- اجرای مجدد Failed/Blocked یک **اجرای جدید** با `parent_run_id` می‌سازد.
- Snapshot تست و زمینه اجرا برای بازتولید، از اجرای مبنا کپی می‌شود؛ نتایج قبلی کپی نمی‌شوند.
- تست مجدد خطا، شناسه خطای مبنا را هم حفظ می‌کند.
- تغییر به Build جدید در محصول واقعی باید با انتخاب صریح زمینه جدید و ثبت آن انجام شود؛ نمونه طراحی تست مجدد را با زمینه اجرای مبنا ایجاد می‌کند.

## 12. Defects

فیلدها: کلید، عنوان، شرح، شدت، اولویت، مسئول رفع، انتشار هدف، مراحل بازتولید، نتیجه مورد انتظار، نتیجه واقعی، تست/اجرا/Attempt منشأ، ایجادکننده، زمان‌ها، پیوست، گفتگو، دنبال‌کننده و تاریخچه.

شدت و اولویت دو فیلد مستقل‌اند. نمونه شدت Critical/High/Medium/Low و اولویت همان مقادیر است.

### گردش وضعیت

- Open → In Fix / Deferred / Rejected
- In Fix → Resolved / Deferred
- Resolved → Closed / Reopened
- Closed → Reopened
- Reopened → In Fix / Deferred
- Deferred → Open
- Rejected → Reopened

هر تغییر وضعیت یادداشت، ثبت‌کننده و زمان دارد. مسئول رفع یا گزارش‌کننده با مجوز مدیریت خطا و ادمین می‌توانند عملیات مجاز را انجام دهند.

### ساخت خطا از Fail

فقط از Attempt واقعاً ثبت‌شده با نتیجه Fail انجام شود؛ انتخاب موقت Fail در پیش‌نویس کافی نیست. عنوان تست، مراحل، نتیجه مورد انتظار، یادداشت واقعی و شناسه دقیق اجرا/قلم/Attempt به فرم منتقل شوند. کاربر می‌تواند متن را تکمیل کند.

### تأیید رفع

Resolved به معنی Closed نیست. برای Closed باید در تست مجددِ مرتبط با همان خطا و همان تست، یک Pass پس از `resolved_at` ثبت شده باشد. وجود Pass قدیمی، Pass تست دیگر یا تغییر دستی شمارنده کافی نیست. ثبت‌کننده تأیید و زمان تأیید حفظ شوند. در صورت شکست مجدد، Reopened قابل انتخاب باشد.

## 13. شواهد و پیوست‌ها

- پیوست روی تست‌کیس، نیازمندی، پلن، انتشار، خطا و قلم اجرا قابل ثبت باشد.
- روی قلم اجرا، پیوست می‌تواند به یک مورد چک‌لیست محدود شود.
- هنگام ثبت نتیجه، شواهد هنوز متصل‌نشده قلم، به Attempt جدید متصل شوند.
- شواهد متصل به Attempt تاریخی نباید با ویرایش نتیجه حذف یا جابه‌جا شوند.
- نام فایل، MIME معتبر، اندازه، ثبت‌کننده، زمان، پروژه، موجودیت، شماره مورد چک‌لیست و Attempt مشخص باشند.
- تصویر، ویدئو، PDF، متن/لاگ و CSV پشتیبانی شوند.
- پیش‌نمایش تصویر، ویدئو و متن؛ دانلود همه انواع مجاز. PDF در نمونه با دانلود باز می‌شود.
- HTML، SVG اجرایی و فایل اجرایی آپلود نشوند. نام فایل و متن هرگز به‌صورت HTML خام اجرا نشوند.

محدودیت **نمونه HTML**: 512 KiB برای هر فایل و 2 MiB مجموع فایل‌ها به‌علت ذخیره در مرورگر. در محصول عملیاتی، فایل‌ها در storage خصوصی با اندازه قابل تنظیم ذخیره و دانلود با کنترل مجوز انجام شود؛ سقف نمونه را به‌عنوان نیازمندی تولید اشتباه نگیرید.

## 14. گفتگو، پاسخ، اشاره و دنبال کردن

- نظرها به موجودیت و پروژه متصل‌اند؛ نام/زمان ثبت‌کننده مشخص باشد.
- پاسخ با `reply_to` و نقل‌قول کوتاه نظر مادر نمایش داده شود.
- اشاره به عضو با `@نام` و انتخاب عضو از دکمه‌های ذکر نام؛ اعلان برای فرد ساخته شود.
- کاربر می‌تواند تست، نیازمندی، پلن، خطا یا انتشار را دنبال کند و اعلان تغییر دریافت کند.
- هر دنبال‌کننده فقط در صورت داشتن دسترسی به پروژه/موجودیت اعلان دریافت کند.
- متن نظر، پیوست و اشاره، محدودیت دسترسی پروژه را دور نزند.
- مشاهده‌گر فقط می‌خواند؛ نظر و پیوست جدید ثبت نمی‌کند.

## 15. اعلان‌ها، موعدها و لاگ فعالیت

### اعلان داخلی

رویدادهای تخصیص تست، درخواست/نتیجه بازبینی، نتیجه Fail، اشاره/پاسخ، تغییر مورد دنبال‌شده و یادآوری موعد.

مرکز اعلان: خوانده/خوانده‌نشده، خواندن همه، تاریخ، نوع و لینک به مورد مرتبط. منوی زنگ، چند اعلان اخیر و ورود به همه اعلان‌ها را نشان دهد.

ترجیحات هر کاربر برای انواع اعلان‌ها مستقل است؛ خاموش‌کردن، رویدادهای آینده را محدود می‌کند، نه تاریخچه قبلی را. در نمونه مستقل، یادآوری‌های موعد هنگام باز بودن صفحه و از داده‌های موجود محاسبه می‌شوند؛ هیچ ارسال زمان‌بندی‌شده، ایمیل یا Push واقعی وجود ندارد. موعد پلن/انتشار در هفت روز آینده یا گذشته، تا بسته‌شدن/بایگانی، قابل مشاهده است.

### Audit log

ایجاد، ویرایش، حذف مجاز، انتقال، تخصیص، درخواست/تصمیم بازبینی، ثبت نتیجه، توقف/ادامه، تغییر خطا، ورود داده و افزودن فایل لاگ شوند. فیلدها: پروژه، کاربر، عمل، نوع و شناسه موجودیت، زمان، و در محصول واقعی خلاصه قبل/بعد. ثبت رویدادهای حساس همراه تغییر دامنه در تراکنش انجام شود.

## 16. ماتریس پوشش و گزارش‌ها

### ماتریس

سطرها نیازمندی‌ها و ستون‌ها تست‌کیس‌ها هستند. خانه بدون ارتباط «—» است. خانه مرتبط نتیجه فعلی آخرین اجرای مربوط به تست در محدوده انتخاب‌شده را نشان می‌دهد: Pass/Fail/Blocked/Skipped/Not Run.

- فیلتر انتشار داشته باشد.
- نتیجه قدیمی موفق نباید نتیجه جدیدتر Not Run را پنهان کند.
- نام و شناسه سطر و ستون خوانا، ستون نیازمندی در جدول عریض چسبان، اسکرول افقی داخل جدول.
- کلیک روی تست/نیازمندی جزئیات آن را باز کند.
- خروجی Excel ماتریس لازم است.

### تعریف «آخرین»

برای هر ترکیب اجرای دستی، آخرین Attempt قلم متناظر ملاک است یا Not Run. جدیدترین اجرای کامل، دامنه مرجع ترکیب‌های تست را تعیین می‌کند؛ Retest فقط ترکیب‌های متناظر خود را در همان زنجیره مرجع به‌روز می‌کند و سایر ترکیب‌ها را حذف نمی‌کند. جمع‌بندی تست با اولویت Fail، سپس Blocked، سپس Not Run، سپس Skipped انجام شود؛ تنها Pass همه ترکیب‌های منتخب، Pass کلی است. ترکیب‌ها و تعداد تست‌های یکتا جداگانه شمارش شوند. علاوه بر نتیجه، تازگی نسخه تست و نیازمندی بررسی شود؛ نتیجه قدیمی با برچسب نیازمند اجرا/بازبینی تازه باقی می‌ماند. جزئیات دقیق در بخش 32. گزارش تاریخچه تلاش‌ها مستقل و صریحاً با عنوان تاریخچه باشد.

### شاخص‌ها و گزارش‌ها

- مجموع تست‌ها و اجراها، Passed/Failed/Blocked/Skipped/Not Run؛
- نرخ موفقیت آخرین اجرا = Passed ÷ کل اقلام همان اجرا × 100؛ موارد اجرا نشده از مخرج حذف نشوند؛
- پیشرفت اجرا = اقلام دارای نتیجه ÷ کل اقلام؛
- پوشش طراحی = نیازمندی‌های دارای حداقل یک تست تأییدشده ÷ نیازمندی‌های فعال محدوده؛
- پوشش تأییدشده = نیازمندی‌های دارای حداقل یک تست تأییدشده و سازگار با نسخه جاری نیازمندی، با آخرین نتیجه Pass در همه ترکیب‌ها و Snapshot منطبق با نسخه جاری تست و نیازمندی ÷ نیازمندی‌های فعال؛
- نیازمندی بدون تست و بدون Pass؛
- توزیع تست‌ها در بخش‌ها؛
- روند نتیجه در اجراهای واقعی، بدون ساخت داده زمانی خیالی؛
- شکست تکراری: حداقل دو اجرا که نتیجه نهایی آن تست در آن‌ها Fail بوده است؛
- تست‌های هرگز اجرا نشده یا با بازبینی قدیمی‌تر از 30 روز؛
- حجم کار هر تستر و آخرین وضعیت تست‌های تخصیص‌یافته؛
- خطاهای باز/بحرانی انتشار و پیشرفت پلن‌ها؛
- فیلتر انتشار و زمینه گزارش به‌صورت واضح.

در مخرج صفر، 0 و حالت بدون داده نمایش داده شود؛ NaN، Infinity یا نرخ ساختگی ممنوع است. هر نمودار متن، مقدار یا راهنما داشته باشد؛ صرف رنگ کافی نیست.

## 17. دروازه آمادگی انتشار

گزارش فقط پلن‌ها، تست‌ها، نیازمندی‌ها، اجراها و خطاهای **همان انتشار** را بسنجد. برای Ready بودن:

1. حداقل یک تست و یک نیازمندی هدف وجود داشته باشد.
2. همه تست‌های دامنه تأییدشده باشند.
3. هر نیازمندی فعال هدف، حداقل یک تست تأییدشده مرتبط در دامنه داشته باشد.
4. هر نیازمندی، حداقل یک نتیجه فعلی Pass از تست مرتبط تأییدشده داشته باشد.
5. هیچ تست دامنه بدون نتیجه نباشد.
6. هیچ نتیجه فعلی Fail، Blocked یا Skipped در تست‌های دامنه باقی نمانده باشد.
7. هیچ خطای Critical با وضعیت Open/In Fix/Resolved/Reopened باقی نمانده باشد؛ Resolved هنوز رفعِ تأییدنشده است.

در نمونه، Deferred و Rejected و Closed مانع Critical محسوب نمی‌شوند؛ در محصول واقعی پذیرش ریسک Deferred باید ثبت‌کننده، علت و زمان تأیید ادمین داشته باشد.

تغییر وضعیت به Released در صورت برقرار نبودن شروط ممنوع باشد. هنگام ذخیره، شروط را بر اساس **مقادیر جدید فرم انتشار**، نه مقادیر قدیمی آن، محاسبه کنید. معیارهای متنی پلن همچنان نیازمند تصمیم انسانی هستند؛ نمایش Ready به معنی تضمین بی‌نقص بودن محصول نیست.

## 18. ورود و خروج داده

### ورود Test Case از CSV و Excel

- فایل `.csv`، `.xlsx` و در نمونه `.xls` قابل خواندن باشد.
- سطر اول نام ستون‌ها؛ پیش‌نمایش قبل از ایجاد هر داده الزامی است.
- خطاهای هر سطر با شماره سطر و فیلد نشان داده شود.
- ثبت فقط پس از تأیید و فقط برای سطرهای معتبر؛ کاربر تعداد معتبر/نامعتبر را می‌بیند.
- تست‌های واردشده Draft هستند و باید بازبینی شوند.
- CSV/Excel برای **سناریوی دستی** است، نه نتایج تست خودکار.
- نمونه سقف 500 سطر و فایل 2 MiB دارد. محصول واقعی ورود بزرگ را با پردازش دسته‌ای و گزارش نتیجه پیاده کند.

ستون‌های دقیق قالب:

```text
Title, SectionPath, Priority, Type, AssigneeEmail, Preconditions,
ExpectedResult, ChecklistJSON, RequirementKeys, Tags, EstimateMinutes, TestData
```

نمونه `ChecklistJSON`:

```json
[
  {"text":"شماره همراه معتبر را وارد کنید.","expected":"کد ارسال شود.","required":true},
  {"text":"پیام راهنما را بررسی کنید.","expected":"متن راهنما خوانا باشد.","required":false}
]
```

`RequirementKeys` مثل `REQ-1,REQ-2` و `Tags` با کاما جدا شوند. ایمیل مسئول باید عضو واجد شرایط پروژه باشد؛ شماره همراه و شناسه‌ها متن باقی بمانند و صفر ابتدایی حذف نشود. مسیر نامعتبر، اولویت/نوع ناشناخته، چک‌لیست خالی، نیازمندی پروژه دیگر یا زمان نامعتبر باعث خطای همان سطر شوند.

### خروجی

- Test Case به CSV و Excel؛
- ماتریس پوشش به Excel؛
- نتایج اجرا، خطاها و پوشش در Workbook چندشیتی؛
- گزارش قابل چاپ و ذخیره PDF از پنجره چاپ مرورگر؛
- پشتیبان JSON داده‌های نمونه.

برای خروجی CSV از Formula Injection جلوگیری کنید؛ مقادیری که با `=`, `+`, `-`, `@`, Tab یا CR شروع می‌شوند به‌عنوان متن بی‌خطر خروجی شوند. در Excel سلول‌های کاربرساخته متن باشند؛ فرمول واردشده اجرا نشود. ورود/خروج بدون مجوز، داده بخش غیرمجاز را نمایش ندهد.

## 19. فهرست صفحات و جزئیات UI

### پوسته عمومی

سایدبار در راست فارسی و چپ انگلیسی، لوگوی برگ افرا، انتخاب پروژه، منو، پروفایل و خروج. هدر شامل Breadcrumb، جستجوی تست‌های پروژه، تغییر زبان، تم، زنگ اعلان و آواتار. نوار نمونه به‌وضوح «نسخه طراحی تعاملی» را اعلام می‌کند و امکان مشاهده با کاربر دیگر دارد.

### ناوبری اصلی: هفت فضای کاری

منوی اصلی دقیقاً هفت قلم دارد؛ قابلیت‌ها حذف نشوند، داخل فضای کاری مربوطه با تب و پیوند زمینه‌ای در دسترس باشند. آخرین تب هر گروه در نشست حفظ شود. انتخاب پروژه در پوسته باقی بماند. اعلان از زنگ هدر و تنظیمات از چرخ‌دنده پروفایل باز شود؛ این دو، قلم مستقل منوی اصلی نیستند.

| قلم اصلی | تب‌ها و قابلیت‌ها |
|---|---|
| نمای کلی | داشبورد پروژه |
| تست و نیازمندی | تست‌کیس‌ها، نیازمندی‌ها، مراحل مشترک، بازبینی، قالب و داده تست، نماهای ذخیره‌شده |
| برنامه‌ریزی | پلن‌ها، پروفایل‌های اجرا، محیط‌ها و Buildها |
| اجرا | اجرای تست و تاریخچه |
| خطاها | مدیریت نقص و تست مجدد |
| کیفیت و انتشار | انتشارها، ماتریس نیازمندی، پوشش پیکربندی، گزارش‌ها، شواهد |
| پروژه و افراد | کاربران و دسترسی، پروژه‌ها، رویدادها، ورود و خروج داده، فیلدهای سفارشی |

تب‌ها در موبایل در یک ردیف با اسکرول افقی مستقل و نام کامل نمایش داده شوند؛ جداول عریض اسکرول داخلی داشته باشند. ورود از لینک یک خطا، نیازمندی، بازبینی یا اجرای مشخص باید تب و گروه درست را فعال کند. کاهش تعداد منو نباید عملیات روزمره ثبت نتیجه را پشت چند پنجره پنهان کند. در نسخه۴، صفحه مدیریت سرویس و تفویض مدیر سامانه نیز اضافه است؛ هفت فضای اصلی حفظ و قابلیت‌های کاربران در تب‌ها و پروفایل ادغام می‌شوند.

### صفحات

| صفحه | محتوای اصلی و عملیات |
|---|---|
| ورود | برند افرا، ایمیل و رمز نمایشی، انتخاب حساب نمونه، ورود، تغییر زبان/تم؛ رمز واقعی ارسال نشود |
| نمای کلی | کارت‌های تعداد تست/نرخ موفقیت/Fail/اجرا؛ نمودار آخرین اجرا؛ اجراهای اخیر؛ تست‌های اخیر؛ میان‌بر سلامت انتشار و ماتریس؛ شمارنده خطا و بازبینی |
| پروژه‌ها | کارت نام/کلید/توضیح/وضعیت/اعضا/شمارنده‌ها؛ ایجاد، ویرایش و ورود به پروژه |
| نیازمندی‌ها | جدول عنوان/اولویت/نسخه/پوشش/وضعیت؛ فرم ایجاد/ویرایش و انتخاب تست‌ها؛ جزئیات با تست‌های مرتبط |
| تست‌کیس‌ها | درختواره در راست، جدول در فضای اصلی، فیلتر/انتخاب گروهی/تخصیص/انتقال/CSV؛ فرم کامل چک‌لیست و داده؛ Drawer جزئیات، نسخه و تاریخچه |
| پلن‌ها | جدول نام/نسخه/تعداد/موعد/وضعیت؛ انتخاب تست‌ها، مالک/انتشار/محدوده/معیارها؛ کپی و شروع اجرا |
| اجرا و تاریخچه | جدول اجراها؛ صفحه اجرای دو ستون؛ زمینه، نتیجه جزئی و کلی، یادداشت، زمان، شواهد، پیش‌نویس، Pause/Resume، History و Retest |
| خطاها | کارت‌های Open/Resolved/Closed؛ جدول شدت/اولویت/مسئول/انتشار/وضعیت؛ فرم دستی یا از Fail؛ جزئیات، گفتگو، شواهد و تغییر وضعیت |
| نسخه‌های انتشار | کارت نسخه و آمادگی؛ جزئیات دروازه‌ها، نیازمندی‌ها، پلن‌ها و خطاها؛ فرم مالک/موعد/وضعیت |
| ماتریس پوشش | جدول ارتباط نیازمندی×تست، نتیجه فعلی، فیلتر انتشار، Excel |
| بازبینی تست‌ها | جدول نسخه/بازبین/وضعیت/تاریخ؛ درخواست بازبینی، تأیید، برگشت با توضیح، مقایسه نسخه‌ها |
| محیط‌ها و Buildها | تب محیط و Build؛ فرم کامل و جدول؛ بدون اتصال شبکه به محیط |
| قالب و داده تست | تب قالب‌ها/مجموعه داده؛ کارت‌ها؛ فرم ویرایش؛ استفاده از قالب و نمایش رکوردهای داده |
| شواهد و پیوست‌ها | مرکز فایل‌های پروژه با مجوز؛ نام/اندازه/ثبت‌کننده/زمان/ارتباط؛ پیش‌نمایش و دریافت |
| گزارش‌ها | فیلتر انتشار، KPI، روند واقعی نتایج، پوشش طراحی/تأییدشده، شکست تکراری، تست قدیمی، حجم کار؛ Excel/PDF |
| ورود و خروج داده | دریافت قالب، انتخاب CSV/XLSX، پیش‌نمایش و خطای سطر، تأیید؛ خروجی تست/ماتریس/گزارش |
| اعلان‌ها | رویدادهای داخلی و یادآوری، خواندن همه، ورود به مورد مرتبط |
| رویدادهای پروژه | Timeline تغییرات با نام کاربر، تاریخ و پیوند موجودیت |
| کاربران و دسترسی | تب اعضا، کاربران سامانه، راهنمای نقش‌ها، نقش سفارشی، قواعد دسترسی بخش |
| مراحل مشترک | بلوک‌های نسخه‌دار، گردش بازبینی مستقل، مصرف در تست‌ها، پیش‌نمایش اعمال نسخه جدید و بایگانی |
| نماهای ذخیره‌شده | شرط‌ساز خوانا، شخصی/تیمی، مرتب‌سازی، ستون‌ها، اعمال روی تست‌ها و ساخت پلن پویا |
| پروفایل‌های اجرا | چند ردیف محیط/Build/مرورگر/سیستم‌عامل/دستگاه، انتخاب داده، الزامی/اختیاری و پیش‌نمایش ترکیب‌ها |
| پوشش پیکربندی | هر ترکیب یک سطر با نتیجه مستقل، داده ورودی و لینک اجرای دقیق؛ فیلتر انتشار و Excel |
| فیلدهای سفارشی | تعریف نوع، کلید ثابت، گزینه‌ها، مقدار پیش‌فرض، الزامی بودن و غیرفعال‌سازی با حفظ سابقه |
| تنظیمات | ظاهر/زبان، ترجیحات اعلان، حساب و دسترسی پروژه‌ها، توضیح نمونه و پشتیبان/بازنشانی |

فرم‌ها داخل Drawer جانبی و عملیات کوتاه داخل Modal باشند. انتخاب چند تست با Checkbox، نمایش تعداد انتخاب، شمارنده و پیام اعتبارسنجی لازم است.

## 20. سیستم بصری دقیق

### لحن و هویت

رابط اداری مدرن، خلوت و خوانا؛ رنگ اصلی نارنجی و لوگوی برگ افرا. رنگ‌ها نسبت به وایرفریم اولیه زنده‌تر باشند؛ اما رنگ وضعیت فقط نقش معنایی داشته باشد، نه تزئین تصادفی. متن محصول فارسی طبیعی، کوتاه و روشن باشد. شعار نمونه: **«کیفیت، ریشه در دقت دارد.»**

### پالت روشن و تاریک

| Token CSS | روشن | تاریک |
|---|---|---|
| `brand` | `#f47712` | `#ff8d32` |
| `brand-hover` | `#d95300` | `#ffad62` |
| `primary-fill` | `#c94c00` | `#ff8d32` |
| `brand-ink` | `#ac3f00` | `#ffb677` |
| `brand-soft` | `#fff0df` | `#412916` |
| `brand-line` | `#ffc38f` | `#79502e` |
| `bg` | `#fff7ef` | `#11151c` |
| `panel` | `#fffdfb` | `#1b2029` |
| `ink` | `#322820` | `#f3eae1` |
| `secondary` | `#6d6056` | `#bdafa2` |
| `muted` | `#88796d` | `#a18f80` |
| `line` | `#efdfd2` | `#37312d` |
| `soft` | `#fcf1e8` | `#252831` |
| `green` | `#139d79` | `#50d8ad` |
| `green-soft` | `#e1f7ed` | `#173b2f` |
| `green-ink` | `#087151` | `#71e3ba` |
| `red` | `#e84b69` | `#ff7d99` |
| `red-soft` | `#ffe8ed` | `#442630` |
| `red-ink` | `#a62946` | `#ffa6bb` |
| `blue` | `#3284d9` | `#80b6fa` |
| `blue-soft` | `#e6f1ff` | `#22354e` |
| `blue-ink` | `#205d9a` | `#accfff` |
| `amber` | `#b67b08` | `#ebbf59` |
| `amber-soft` | `#fff0c7` | `#3d3320` |
| `amber-ink` | `#765000` | `#f4d38c` |
| `violet` | `#8762d6` | `#b59af1` |
| `violet-soft` | `#f2eaff` | `#352b48` |
| `violet-ink` | `#6041a1` | `#d1b9ff` |

زمینه روشن گرم و متمایل به نارنجی است. دکمه اصلی روشن متن سفید و در تاریک متن بسیار تیره دارد. نارنجی برای هویت و انتخاب است؛ سبز/قرمز معنای Pass/Fail را حفظ کنند. کارت، سایدبار، هدر، جدول، Drawer، Modal، ورودی، نمودار و صفحه ورود همگی از توکن تم پیروی کنند.

مالک توکن‌های تم فقط ریشه `html[data-theme="light|dark"]` است. تعریف عمومی `:root` یا دکمه‌های دارای `data-theme` نباید این مقادیر را بازنویسی کنند. در دارک، زمینه واقعی صفحه `#11151c` و کارت‌ها `#1b2029` باشد؛ فقط کوچک بودن پیش‌نمایش تم روشن یا کاغذ چاپ مجوز زمینه سفید دارد. حالت System با `prefers-color-scheme` و تغییر زنده آن هماهنگ باشد؛ انتخاب کاربر ذخیره شود.

### هندسه و تایپوگرافی

- فونت Vazirmatn داخلی؛ جایگزین Tahoma/Arial؛ وزن‌های 400 تا 800.
- متن اصلی 15px؛ عنوان صفحه 26px، عنوان پنل 16px، متن جدول 13px، کد و توضیح کوتاه 12px. اندازه‌های کوچک CSS نسبت به نسخه 3.0 دو پیکسل و عنوان‌های بزرگ یک پیکسل افزایش یافته‌اند. در موبایل ورودی‌ها حداقل 16px باشند تا خوانا بمانند و بزرگ‌نمایی ناخواسته iOS رخ ندهد. ابعاد ثابت، فاصله‌ها و Padding اجزا حفظ شده؛ ارتفاع خط متن عمومی 1.6 و کنترل‌ها 1.45 باشد. در عرض کم، شکستن طبیعی متن بر بریدن آن مقدم است. در محصول نهایی، خوانایی و بزرگ‌نمایی مرورگر بر تراکم مقدم است.
- سایدبار دسکتاپ 232px؛ هدر 75px؛ محتوای صفحه Padding افقی 32px؛ حداکثر عرض 1620px.
- کارت با Radius حدود 12px، مرز 1px و سایه بسیار لطیف؛ دکمه 8px، کنترل فرم 7px.
- فاصله پنل‌ها 22px؛ کارت‌های KPI در چهار ستون و کارت‌های موجودیت در سه ستون.
- درخت تست 290px و جدول تست `minmax(0,1fr)`.
- Drawer عادی تا 650px، عریض تا 810px؛ Modal عادی 610px و عریض 850px؛ ارتفاع حداکثر 90vh و اسکرول داخل محتوا.
- نوار رنگی بالای کارت‌های KPI یا انتشار، نارنجی/سبز/آبی/قرمز محدود و معنادار باشد.

### واکنش‌گرایی

- در 1200px فاصله‌ها و سایدبار فشرده‌تر شوند.
- در 1000px پنل‌های دوبخشی و درخت/فهرست به زیر هم بروند؛ KPI دو ستون؛ فهرست تست‌های اجرا به ردیف افقی قابل اسکرول تبدیل شود.
- در 760px سایدبار مخفی و با دکمه منو و Backdrop باز شود؛ محتوا Padding 16px؛ کارت‌ها یک ستون؛ فرم‌ها یک ستون.
- جدول عریض داخل خود جدول اسکرول شود و کل صفحه عرض اضافی پیدا نکند.
- پشتیبانی از عرض 320px تا دسکتاپ عریض لازم است.

## 21. زبان، جهت، تم و دسترس‌پذیری

- فارسی پیش‌فرض، انگلیسی قابل انتخاب؛ ساختار ترجمه قابل افزودن زبان‌های بعدی.
- فارسی RTL و انگلیسی LTR؛ سایدبار، Breadcrumb، آیکون‌های جهت‌دار و فاصله‌ها از CSS logical properties پیروی کنند.
- محتوای کاربر مثل نام پروژه فارسی، با تغییر زبان ترجمه خودکار نشود.
- شناسه، ایمیل، نسخه، URL و نام Build در هر دو جهت خوانا و با `dir=ltr` یا isolation نمایش داده شوند.
- Light/Dark/System با ذخیره انتخاب؛ System به تغییر `prefers-color-scheme` واکنش نشان دهد.
- انتخاب زبان، تم، کاربر نمونه و داده محلی حفظ شود.
- دکمه آیکونی برچسب دسترس‌پذیر داشته باشد؛ Focus قابل مشاهده؛ ترتیب Tab طبیعی؛ Escape بستن پنجره؛ Focus trap برای Modal/Drawer و بازگشت Focus به محرک.
- خطاها با متن و نقش Alert؛ موفقیت و تغییرات با Toast/Status؛ وضعیت فقط با رنگ بیان نشود.
- کاهش حرکت سیستم رعایت شود.
- حالت Empty، No Access، Validation Error، تأیید حذف، محدودیت ذخیره و خطای فایل طراحی شوند؛ حالت بدون داده با داده جعلی پر نشود.

## 22. مدل داده پیشنهادی محصول عملیاتی

نام‌ها پیشنهاد طراحی پایدار هستند؛ می‌توان Convention لاراول را رعایت کرد اما روابط و قواعد حذف تغییر نکنند.

| موجودیت | فیلدهای کلیدی و ارتباط |
|---|---|
| users | id, name, email, password_hash, status, is_system_admin |
| projects | id, name, key, description, status |
| project_memberships | project_id, user_id, role_id؛ کلید یکتای زوج |
| roles / role_permissions | project_id، نام نقش و مجوزها؛ نقش‌های پیش‌فرض و سفارشی |
| section_access_rules | project_id, section_id, user_id, view/edit/execute nullable |
| sections | project_id, parent_id nullable, name, sort_order |
| requirements | project_id, title, description, priority, status, target_version |
| test_cases | project_id, section_id، تخصیص‌ها، current_revision_id، وضعیت بازبینی |
| test_case_revisions | case_id, revision_number, author_id, reason, immutable_content_json |
| checklist_items | revision_id, stable_item_key, position, text, expected, required |
| requirement_test_case | requirement_id, test_case_id |
| review_requests / review_decisions | revision_id, requester, reviewer, status, comment, decided_at |
| tags / test_case_tags | برچسب پروژه و ارتباط با تست |
| test_templates | project_id, name, content_json |
| test_datasets / dataset_rows | project_id، ستون‌ها و مقادیر دستی |
| releases / release_requirements | project_id، نسخه، موعد، مالک، وضعیت، نیازمندی‌های هدف |
| environments | project_id، نوع، URL و زمینه پیش‌فرض |
| builds | project_id, environment_id, identifier, built_at, notes |
| test_plans / test_plan_cases | project_id, release_id, owner, due_at, scope, entry/exit criteria، تست‌ها |
| test_runs | project_id, plan_id nullable, release_id, parent_run_id, defect_id nullable، context snapshot، paused |
| run_items | run_id, source_case_id nullable، case_revision_id nullable، snapshot_json، assignee، substitute |
| test_attempts | run_item_id, actor_id, outcome, comment, minutes, recorded_at؛ append-only |
| checklist_results | attempt_id, snapshot_item_key, outcome, note |
| defects / defect_transitions | project_id، شدت/اولویت، مسئول، انتشار، منبع Attempt و تاریخچه وضعیت |
| attachments | project_id، entity_type/id، checklist_key، attempt_id nullable، storage_path، metadata |
| comments | project_id، entity_type/id، author، reply_to، text |
| follows | project_id، entity_type/id، user_id |
| notifications / notification_preferences | recipient, event_type, entity, read_at، انتخاب نوع اعلان |
| audit_events | project_id، actor، action، entity، before/after، timestamp |
| execution_drafts | user_id, run_item_id, content، updated_at؛ مستقل از Attempt |

### قواعد یکپارچگی

- همه روابط چندبه‌چند یکتا و هم‌پروژه باشند.
- جلوگیری از چرخه بخش در سرویس دامنه و تراکنش.
- Snapshot و Attempt تاریخی با cascade حذف روی تست/پلن از بین نروند.
- تخصیص از اعضای مجاز پروژه؛ حذف عضویت، دسترسی جدید را قطع کند ولی نام ثبت‌کننده نتایج قدیم حفظ شود.
- آخرین ادمین با قفل/تراکنش در برابر حذف هم‌زمان محافظت شود.
- برای درخواست‌های هم‌زمان نتیجه، ترتیب پایدار سروری و شناسه یکتا وجود داشته باشد.
- خروجی، پیوست و گزارش همان Policy دسترسی خواندن داده مبنا را اجرا کنند.

## 23. معماری محصول عملیاتی و حدود نمونه طراحی

### محصول عملیاتی

Laravel 13 / PHP نسخه سازگار، REST JSON داخلی، احراز هویت JWT مطابق قرارداد مستقل BackEnd و FrontEnd، Policy/Gate، Form Request، Pagination، کنترل پروژه در Queryها و Serviceهای دامنه تراکنشی. فرانت‌اند React + TypeScript + Ant Design با ConfigProvider برای Theme/Direction/Locale و منابع ترجمه مجزا.

در پیاده‌سازی مرجع نسخه۴ از PostgreSQL 16+ و Redis استفاده شود؛ تست‌های یکپارچگی قیود و تراکنش روی PostgreSQL نیز اجرا شوند. فایل خصوصی، ثبت رویداد قابل اتکا، تاریخ سروری، اعتبارسنجی هم در کلاینت و هم سرور و کنترل خطای فارسی/انگلیسی لازم است. رمز، توکن و داده محرمانه در HTML طراحی یا پشتیبان نمونه قرار نگیرند.

### نمونه HTML تحویل‌شده

- تمام UI در `afra-ui-v4.html`؛ CSS، JS، SVG، فونت و کتابخانه Excel در همان فایل قرار دارد.
- JavaScript بومی برای مستقل بودن فایل استفاده شده است؛ این خروجی، سورس نهایی React/Ant Design یا Laravel نیست.
- کتابخانه SheetJS برای خواندن/نوشتن فایل‌های Excel، داخل فایل Bundle شده؛ در زمان باز شدن دانلود نمی‌شود.
- داده در `localStorage` و حافظه مرورگر نگهداری می‌شود. تغییرات مرورگرها/دستگاه‌ها با هم همگام نیست.
- مجوزها شبیه‌سازی UI هستند؛ با DevTools قابل تغییر و جایگزین امنیت سرور نیستند.
- محدودیت حجم پیوست و ورود داده برای نمونه است؛ بارگذاری بزرگ، نگهداری ویدئوی بزرگ و چندکاربره واقعی نیازمند بک‌اند است.
- اعلان‌ها داخلی و یادآوری هنگام استفاده از فایل هستند؛ پیام بیرونی یا ارسال پس‌زمینه وجود ندارد.
- خروجی PDF از قابلیت Print مرورگر است، نه تولید خودکار سند در سرور.
- صحت بارگذاری صفحات و گردش‌های اصلی با محیط DOM بررسی شده؛ این کار جای آزمون بصری واقعی در همه مرورگرها را نمی‌گیرد.

## 24. داده نمونه برای بازسازی

### کاربران

| شناسه | نام | ایمیل نمایشی | نقش در فروشگاه |
|---|---|---|---|
| 1 | آرمان رضایی | arman@afra.demo | مدیر سامانه و ادمین پروژه |
| 2 | سارا احمدی | sara@afra.demo | تستر |
| 3 | رضا محمدی | reza@afra.demo | مشاهده‌گر |
| 4 | نیلوفر کریمی | niloufar@afra.demo | تستر و جانشین برخی تست‌ها |
| 5 | مریم نادری | maryam@afra.demo | بازبین مستقل |

در پروژه موبایل، سارا ادمین است؛ در سرویس احراز هویت، رضا تستر است. این تفاوت برای نمایش استقلال نقش پروژه‌ها ضروری است.

### پروژه‌ها

1. فروشگاه اینترنتی — SHOP؛ پیش‌فرض.
2. اپلیکیشن موبایل — APP.
3. سرویس احراز هویت — AUTH.

### درخت فروشگاه

```text
ورود و ثبت‌نام
├── ورود با شماره همراه
└── بازیابی رمز عبور
فرآیند خرید
├── سبد خرید
└── پرداخت
حساب کاربری
```

### تست‌ها و نیازمندی‌ها

- ورود با شماره همراه معتبر؛ نمایش خطا برای کد نامعتبر؛ بازیابی رمز؛ مربوط به نیازمندی ورود امن.
- افزودن کالا به سبد و محاسبه مبلغ پس از تغییر تعداد؛ مربوط به مدیریت سبد.
- پرداخت موفق و ایجاد سفارش؛ بازگشت از پرداخت ناموفق؛ مربوط به پرداخت امن.
- ویرایش اطلاعات پروفایل؛ فعلاً بدون نیازمندی متصل.
- نیازمندی مدیریت آدرس‌ها برای انتشار 2.5، بدون تست مرتبط تا حالت بدون پوشش نمایش داده شود.
- در موبایل: تست مجوز اعلان؛ در سرویس هویت: تست انقضای توکن، هر دو سناریوی دستی.

### اجراهای نمونه

- رگرسیون 2.3: هشت تست، هفت Pass و یک Fail در بازگشت از پرداخت.
- رگرسیون 2.4: هشت تست؛ سه Pass، یک Fail، یک Blocked و سه Not Run.
- هر تست 3 یا 4 مورد چک‌لیست، نتیجه مورد انتظار، مسئول و داده ساختگی داشته باشد.
- Plan رگرسیون 2.4 و پلن دود پرداخت با موعد مشخص وجود داشته باشند.
- انتشار 2.4 با نیازمندی‌های ورود/سبد/پرداخت و انتشار 2.5 با نیازمندی آدرس‌ها وجود داشته باشد.
- محیط Staging، Build `2.4.0-rc.2`، Chrome 130، Windows 11 و Desktop.
- خطای محاسبه تخفیف در In Fix و خطای پیام لغو پرداخت در Resolved منتظر تست مجدد.
- دو قالب «مسیر موفق فرم» و «ورودی نامعتبر» و یک Dataset حساب‌های ساختگی.

آمار داشبورد از همین داده محاسبه شود، نه از اعداد ثابت مستقل از فهرست‌ها. پس از CRUD و ثبت نتیجه، همه شمارنده‌ها باید تغییر کنند.

## 25. معیارهای پذیرش و سناریوهای آزمون

1. هر 24 صفحه داخل پوسته و صفحه ورود بدون خطای اسکریپت باز شوند.
2. ساخت ریشه/زیربخش، انتخاب درخت، شمارش زیرشاخه و جلوگیری از چرخه درست باشد.
3. تست با دو مورد Required/Optional، Expected مستقل، داده، Dataset، برچسب، زمان و جانشین ایجاد شود.
4. تست تازه Draft است؛ نسخه 1 و نویسنده درست ثبت شود.
5. درخواست بازبینی برای نویسنده خود نسخه مجاز نباشد؛ بازبین مستقل درخواست را ببیند و تأیید/برگشت کند.
6. پلن شامل تست تأییدنشده نتواند اجرا شود؛ پیام واضح نمایش داده شود.
7. اجرای تست تأییدشده، نسخه محتوا و زمینه Build را مستقل ذخیره کند.
8. تغییر عنوان، چک‌لیست، مسئول یا محیط، Snapshot اجرای قدیمی را تغییر ندهد.
9. تستر فقط تست اصلی یا جانشینی خودش را اجرا کند؛ مشاهده‌گر نتواند نتیجه ثبت کند.
10. Pause ثبت را متوقف و Resume فعال کند؛ پیش‌نویس در تغییر تست و نشست نمونه باقی بماند.
11. Pass با Fail چک‌لیست مسدود شود؛ Optional با Skip در Pass مجاز باشد.
12. Blocked/Skipped بدون توضیح رد شوند؛ Required بدون نتیجه در Pass/Fail رد شود.
13. ثبت دوم نتیجه، Attempt اول را نگه دارد.
14. خطا از Fail ثبت‌شده با شناسه دقیق منشأ ساخته شود.
15. Closed قبل از تست مجدد Pass بعد از Resolved ممنوع و پس از آن مجاز باشد.
16. فایل متن/تصویر مجاز بارگذاری و پیش‌نمایش شود؛ سقف حجم و نوع نامجاز کنترل شود؛ شواهد Attempt تاریخی بی‌صدا حذف نشوند.
17. نظر، پاسخ، اشاره و دنبال کردن اعلان متناسب تولید کنند.
18. نقش سفارشی قابل تعریف و قاعده منع مشاهده بخش روی فهرست/جزئیات/خروجی اعمال شود.
19. آخرین ادمین پروژه حذف یا تنزل داده نشود.
20. Ready انتشار با داده واقعی محاسبه و Released در حالت ناآماده رد شود.
21. ماتریس رابطه صحیح و آخرین نتیجه همان محدوده را نشان دهد.
22. نسخه جدید تست، نسخه پیشین و تأیید آن را حفظ کند؛ مقایسه اختلاف واقعی را برجسته کند.
23. فایل ورود دارای سطر معتبر و نامعتبر، قبل از تأیید هیچ داده‌ای ایجاد نکند؛ پس از تأیید فقط معتبرها Draft شوند.
24. خروجی Excel با داده فارسی، چندشیت و شناسه متنی درست باز شود؛ CSV امن باشد.
25. فارسی/انگلیسی جهت را تغییر دهد؛ تم روشن/تاریک/سیستم و ترجیحات اعلان حفظ شوند.
26. سناریوهای بدون داده و خطای ذخیره مرورگر پیام صادقانه داشته باشند.
27. هیچ صفحه مربوط به تست خودکار، CI/CD، Webhook، SSO یا اتصال آماده ابزارهای بیرونی وجود نداشته باشد؛ تب مدیریت سرویس‌ها و تفویض نسخه۴ مجاز و لازم است.

## 26. ترتیب پیاده‌سازی برای ادامه محصول

1. اسکلت مستقل Laravel و React، احراز هویت و پروژه/عضویت/Policy.
2. درخت بخش، تست و نسخه محتوا، چک‌لیست، تخصیص و نیازمندی.
3. بازبینی مستقل و جلوگیری از اجرای نسخه تأییدنشده.
4. پلن، انتشار، محیط، Build و Snapshot اجرا.
5. Attempt و نتیجه جزئی، پیش‌نویس، توقف، شواهد و تاریخچه.
6. خطا، گردش رفع، Retest و Verify.
7. گفتگو، دنبال‌کننده، اعلان و Audit.
8. نقش سفارشی و محدودیت بخش در تمام Queryها/خروجی‌ها.
9. ماتریس، گزارش‌ها، دروازه انتشار و ورود/خروج فایل.
10. آزمون دامنه، یکپارچگی، دسترسی، واکنش‌گرایی، RTL/LTR و دسترس‌پذیری.

این ترتیب وابستگی‌ها را مشخص می‌کند و مجوز حذف امکانات این سند از دامنه نهایی نیست.

## 27. دستور بازسازی آماده برای استفاده مجدد

> با استفاده از تمام بخش‌های همین سند، UI کامل «افرا» را بازسازی کن. خروجی یک HTML مستقل و آفلاین با داده نمونه و تعامل واقعی فرم‌ها باشد. فارسی پیش‌فرض، انگلیسی و RTL/LTR، سه حالت تم، نارنجی زنده و رنگ‌های معنایی مکمل را دقیقاً مطابق Tokenها و هندسه بخش 20 پیاده کن. تمام صفحات بخش 19 و سناریوهای بخش 25 و 34 را پوشش بده. هفت منوی اصلی و تب‌های وابسته را حفظ کن. مراحل مشترک نسخه‌دار، فیلد سفارشی تایپ‌دار، نماهای شخصی و تیمی، نسخه نیازمندی و اثر تغییر، ماتریس اجرای دستی با داده و زمینه منجمد را طبق بخش‌های 28 تا 33 بساز. Pass قدیمی را تأیید نسخه تازه معرفی نکن. ریشه دارک واقعاً تاریک باشد. نسخه محتوا و Attempt را مستقل و تغییرناپذیر نگه دار، بازبین از نویسنده مستقل باشد، تست تأییدنشده اجرا نشود و بستن عادی خطا پس از Retest Pass تازه و بستن اجباری فقط با مجوز مستقل و دلیل ثبت‌شده باشد. UI نقش‌ها و داده‌ها نمونه است و این محدودیت را اعلام کن. هیچ قابلیت تست خودکار اضافه نکن؛ سرویس‌های بیرونی فقط در کاتالوگ مدیریت‌شده نسخه۴ و با JWT و Delegation محدود مجازند. ابتدا همه داده نمونه را بساز، سپس صفحه‌ها و عملیات را پیاده کن، اعتبارسنجی و سناریوهای پذیرش را بررسی و فایل نهایی HTML را تحویل بده. برای محصول عملیاتی آینده، معماری Laravel 13 + REST داخلی و React/TypeScript/Ant Design جداگانه را حفظ کن؛ HTML نمونه را به‌عنوان بک‌اند یا محصول عملیاتی معرفی نکن.

---

مرجع نهایی دامنه: همین سند، نسخه 4.1.  
مرجع بصری و تعاملی تحویل جاری: `afra-ui-v4.html`.  
تغییرات آینده باید با نسخه جدید سند، توضیح تغییر و معیار پذیرش جدید ثبت شوند.


## 28. مراحل مشترک نسخه‌دار

هدف، نگهداری یک مسیر تکراری مثل ورود و مصرف آن در چند تست است. بلوک شامل پروژه، نام، نویسنده، بازبین و نسخه‌های مستقل است. هر نسخه دارای وضعیت Draft/In Review/Approved، تاریخ، دلیل و فهرست مرتب مراحل با کلید، متن، نتیجه مورد انتظار و الزامی بودن است.

- فقط نسخه تأییدشده برای ارجاع تازه قابل انتخاب باشد. درخواست بازبینی و تأیید با دلیل ثبت شود؛ نویسنده تأییدکننده همان نسخه نباشد.
- ارجاع تست شامل شناسه بلوک، نسخه ثابت، token گروه، شروع/تعداد مراحل و کلید مراحل است. متن ارجاعی در فرم فقط خواندنی است. «تبدیل به مراحل محلی» اتصال را قطع و متن را قابل ویرایش کند.
- ویرایش بلوک، نسخه Draft جدید می‌سازد؛ تست‌های مصرف‌کننده بدون اقدام صریح تغییر نکنند. نسخه تأییدشده قبلی قابل مصرف بماند.
- اعمال نسخه تازه با پیش‌نمایش تست‌های تحت تأثیر و تأیید صریح انجام شود؛ هر تست یک نسخه محتوای تازه و Draft می‌گیرد و دوباره بازبینی لازم دارد. مراحل محلی و بلوک‌های دیگر حفظ شوند.
- بایگانی، ارجاع تازه را ببندد؛ ارجاعات و اجراهای تاریخی باقی بمانند. نسخه اجرایی کپی کامل مراحل منجمدشده را نگه دارد.
- متغیر `{{mobile}}` و نام‌های لاتین/عدد/زیرخط پشتیبانی شود. جایگزینی فقط متن است و هیچ کدی اجرا نمی‌شود. نبود مقدار در پیش‌نمایش اجرا خطای روشن بدهد.

## 29. فیلدهای سفارشی

مدیر مجاز پروژه برای Test Case، Requirement، Test Plan، Test Execution و Defect فیلد تعریف کند. تعریف شامل پروژه، موجودیت، کلید فنی یکتا، عنوان، نوع، گزینه‌ها، مقدار پیش‌فرض، الزامی بودن، فعال بودن و نسخه تعریف است. این گزینه‌ها توسط کاربر نهایی با عنوان خوانا دیده شوند.

انواع: متن کوتاه، متن بلند، عدد، تاریخ، بله/خیر، انتخاب تکی و چندانتخابی. نوع، موجودیت و کلید پس از ساخت ثابت بمانند. تغییر آن‌ها نیازمند فیلد جدید است. گزینه مصرف‌شده در داده ذخیره‌شده قابل حذف نباشد. غیرفعال‌سازی به‌جای حذف مخرب انجام شود؛ مقدار و عنوان تاریخی حفظ شوند.

اعتبارسنجی نوع و الزامی بودن در UI و در محصول عملیاتی سمت سرور تکرار شود؛ `false` و عدد صفر مقادیر معتبرند. هر رکورد مقادیر را همراه Snapshot تعریف/گزینه‌ها ذخیره کند. نسخه محتوای تست و قلم اجرای تاریخی، تعریف و مقدار همان زمان را نگه دارند. ویرایش عنوان فعلی نباید عنوان تاریخی را عوض کند. فیلدهای تست در شرط‌ساز نماها و خروجی CSV/Excel با ستون `CF:key` استفاده شوند؛ ورود داده نیز اعتبارسنجی و مقدار پیش‌فرض را اعمال کند.

## 30. نماهای ذخیره‌شده و پلن پویا

نما شامل نام، مالک، پروژه، دسترسی شخصی/تیمی، تطبیق همه/هرکدام، شروط، ترتیب و ستون‌ها است. نمای شخصی فقط برای مالک در فهرست و انتخاب قابل مشاهده باشد؛ نماهای تیمی در پروژه با رعایت مجوز داده قابل استفاده‌اند. ساخت تیمی به مجوز مدیریت تست/پروژه نیاز دارد. ویرایش و حذف برای مالک یا مدیر مجاز انجام شود؛ دسترسی به داده تست از خود نما به دست نمی‌آید.

شرط‌ها روی عنوان، اولویت، نوع، مسئول، بخش و زیربخش‌هایش، برچسب، وضعیت بازبینی، نیازمندی و فیلدهای سفارشی تست باشند. فیلدها و عملگرها از فهرست مجاز انتخاب شوند؛ عبارت دلخواه و eval ممنوع است. مساوی/شامل و برای فیلد عددی حداقل/حداکثر پشتیبانی شود. بخش، مسئول، نیازمندی، بله/خیر و گزینه‌ها با نام خوانا انتخاب شوند، نه ورود ID خام. مرتب‌سازی: به‌روزرسانی، عنوان، اولویت. ستون عنوان همیشه بماند. انتخاب نما برای هر کاربر/پروژه ذخیره و پاک کردن آن آسان باشد.

پلن دو روش انتخاب دارد: انتخاب صریح تست‌ها یا نمای پویا. پلن پویا می‌تواند در Draft بدون تطبیق ذخیره شود؛ شروع اجرا به حداقل یک تست مجاز و تأییدشده نیاز دارد. تغییر داده‌ها، خروجی آینده نما را تغییر می‌دهد، نه اجرای قبلی را.

پیش از ایجاد اجرا، شروط نما، تست‌های واقعی، نسخه‌ها، نیازمندی‌ها و ترکیب‌ها پیش‌نمایش شوند. هنگام تأیید دوباره با داده فعلی مقایسه شوند؛ تغییر دامنه/محتوا/زمینه باعث توقف و درخواست «به‌روزرسانی پیش‌نمایش» شود. پس از تأیید، دامنه و شروط منجمد ذخیره شوند؛ اجرا هرگز در میانه کار تست جدیدی از نما دریافت نکند.

## 31. نسخه نیازمندی و اثر تغییر

`revisionNo` شماره بازبینی محتواست و با نسخه هدف محصول مانند 2.4 تفاوت دارد. تغییر محتوا/فیلد سفارشی نیازمندی، Snapshot نسخه قبل را حفظ و شماره جدید با نویسنده/زمان/دلیل ایجاد کند. مقایسه دو نسخه و نمایش تغییر فیلدها فراهم باشد. تغییر صرف وضعیت بدون تغییر محتوا نسخه محتوای بی‌دلیل نسازد.

هر رابطه سازگاری دقیقاً به این چهار مقدار وابسته است: `caseId + caseRevision + requirementId + requirementRevision`. پس از تغییر نیازمندی، تمام تست‌های مرتبط فاقد سازگاری نسخه جاری، «نیازمند بررسی اثر تغییر» شوند. این هشدار از نیازمندی، تست، ماتریس و پیش‌نمایش اجرا قابل پیگیری باشد.

دو مسیر حل وجود دارد: بازبین مستقل با دلیل، سازگاری همان رابطه را بدون تغییر سناریو تأیید کند؛ یا تست اصلاح شود، نسخه Draft تازه بسازد و دوباره تأیید مستقل بگیرد. تأیید یک رابطه نباید سایر نیازمندی‌های تست یا سایر تست‌ها را خودبه‌خود تأیید کند. نویسنده تست حق خودتأییدی ندارد. درخواست بازبینی، نسخه نیازمندی‌ها را ثبت کند؛ اگر نیازمندی حین بازبینی تغییر کرد، تأیید درخواست قدیمی متوقف شود.

اجرای جدید تست ناسازگار یا تأییدنشده مسدود شود. قلم اجرا، شناسه و نسخه نیازمندی‌ها و تأیید سازگاری همان زمان را ثبت کند. حتی پس از تأیید سازگاری، Pass اجرای قبلی برای نسخه قدیمی نیازمندی یا تست، اثبات Pass نسخه جدید نیست. آن نتیجه با برچسب قدیمی باقی بماند؛ پوشش تأییدشده و آمادگی انتشار نیازمند اجرای منطبق با نسخه‌های جاری هستند. تاریخچه هرگز بازنویسی نشود.

## 32. ماتریس پیکربندی برای اجرای دستی

پروفایل اجرا مجموعه‌ای از ردیف‌های نام‌دار است. هر ردیف دارای شناسه پایدار، محیط، Build متعلق به همان محیط، مرورگر، سیستم‌عامل، دستگاه، منبع داده، ردیف‌های داده منتخب و الزامی/اختیاری است. منبع داده: بدون داده، مجموعه متصل به خود تست یا مجموعه مشخص پروژه. ردیف خالی یعنی همه داده‌ها؛ شماره‌های نمایشی از ۱ شروع می‌شوند. تنظیمات متعلق به پروژه دیگر پذیرفته نشوند.

تعداد اقلام = مجموع ترکیب تست‌های انتخاب‌شده × ردیف‌های پیکربندی × داده‌های همان ردیف. برای مثال ۲ تست × ۲ مرورگر × ۲ ردیف داده = ۸ قلم، ولی تعداد تست یکتا ۲ است. این صرفاً ساخت دامنه اجرای دستی است؛ مرورگر یا تست خودکار اجرا نمی‌شود.

پیش‌نمایش قبل از ثبت، تست/پیکربندی/داده/وضعیت تأیید را نشان دهد. اقلام الزامی غیرقابل حذف، اقلام اختیاری قابل انتخاب باشند؛ شمارنده منتخب/کل به‌روز شود. ورودی هر قلم از `key=value` تست و سپس داده ردیف ساخته شود؛ داده ردیف بر مقدار پیش‌فرض تقدم دارد. متغیر در عنوان، پیش‌شرط، مراحل و خروجی مورد انتظار جایگزین شود و متن منبع نیز نگهداری شود. نبود متغیر، Build نامعتبر یا شماره داده خارج از محدوده ثبت اجرا را متوقف کند.

در نسخه HTML سقف ۲۰ ردیف پیکربندی و ۱۰۰۰ قلم پیش‌نمایش وجود دارد و عبور از سقف پیام روشن بدهد. این سقف به معنای ظرفیت نهایی محصول نیست؛ در محصول عملیاتی محدودیت ظرفیت و پردازش دسته‌ای باید پیکربندی شود.

هر قلم، نتیجه و Attempt مستقل، Snapshot تست، متن جایگزین‌شده، ارجاعات مشترک، مقادیر سفارشی و تعریفشان، زمینه، داده ورودی، نسخه نیازمندی و کلید ترکیب دارد. زمینه سطح اجرا فقط خلاصه است؛ نتیجه ترکیب‌ها با زمینه همان قلم تفسیر شود. ویرایش آینده محیط/Build/داده/پروفایل/تست هیچ یک از آن‌ها را تغییر ندهد.

پوشش پیکربندی یک سطر برای هر ترکیب با آخرین نتیجه و لینک مستقیم قلم اجرا دارد. برای نتیجه کلی تست، تمام ترکیب‌های منتخب مرجع بررسی شوند. یک Pass کنار Fail یا Not Run، Pass کلی نسازد. Retest جزئی فقط جای ترکیب متناظر در زنجیره مرجع را می‌گیرد؛ ترکیب موفق دیگر یا اجرا نشده از مخرج حذف نشود. تاریخچه تلاش‌ها، تعداد اقلام، تعداد تست یکتا و نرخ موفقیت به‌وضوح از هم جدا باشند.

## 33. تکمیل مدل داده و پیاده‌سازی

علاوه بر بخش 22، مدل عملیاتی این موجودیت‌ها را داشته باشد:

| موجودیت | داده و قید اصلی |
|---|---|
| SharedBlock / SharedBlockVersion / SharedStep | پروژه، نسخه، نویسنده/بازبین مستقل، وضعیت و مراحل مرتب |
| CaseSharedReference | نسخه تست، نسخه بلوک، موقعیت/کلید مراحل و ارجاع ثابت |
| CustomFieldDefinition / DefinitionVersion | کلید یکتا در پروژه/موجودیت، نوع ثابت، گزینه و نسخه تعریف |
| CustomFieldValue / HistoricalSchema | مقدار تایپ‌دار و تعریف ثبت‌شده در Snapshot |
| SavedView / UserProjectViewPreference | مالک، دسترسی، شروط تایپ‌دار، ترتیب، ستون و انتخاب کاربر |
| RequirementRevision | شماره محتوا، Snapshot، دلیل، نویسنده و زمان |
| RequirementCompatibility / ChangeImpact | کلید چهارگانه نسخه‌ها، بازبین، دلیل، وضعیت اثر تغییر |
| ConfigurationProfile / ConfigurationRow | زمینه و داده، شناسه پایدار ردیف، الزامی بودن |
| ExecutionScopeSnapshot / ExecutionVariant | دامنه تأییدشده، شروط نما، کلید ترکیب، داده و زمینه منجمد |

ایجاد اجرا و بررسی امضای دامنه در تراکنش سمت سرور انجام شود. Policyها، مجوز بخش و تعلق پروژه برای تمام شناسه‌ها دوباره بررسی شوند. مجوز صرفاً با مخفی کردن دکمه تأمین نمی‌شود. تعریف فیلد و شرط نما باید Schema اعتبارسنجی‌شده داشته باشد. JSON برای Snapshotهای تاریخی مجاز است، ولی روابط اصلی قابل جستجو و دارای قید یکپارچگی باشند. نسخه‌های قبلی و نتایج فقط خواندنی بمانند؛ حذف مخرب با داده مصرف‌شده مجاز نباشد.

نمونه تحویلی HTML مستقل است؛ ورود، مجوز و داده‌ها برای نمایش تجربه کاربری در مرورگر شبیه‌سازی می‌شوند و امنیت واقعی سرور محسوب نمی‌شوند. پروژه تولیدی همچنان Laravel 13 با REST داخلی و فرانت جداگانه React/TypeScript/Ant Design است. سرویس بیرونی ثبت تست و گزارش پروژه با JWT و مدیریت دسترسی نسخه۴ داخل دامنه است؛ اتصال آماده بیرونی، Webhook، ورود سازمانی و قابلیت تست خودکار اضافه نشوند.

## 34. پذیرش تکمیلی و وضعیت تحویل

- تمام مسیرها از هفت گروه و لینک‌های زمینه‌ای قابل دسترس باشند؛ تنظیمات و اعلان از پوسته باز شوند. در فارسی راست‌چین و در انگلیسی چپ‌چین باشند.
- دارک روی ریشه، زمینه و تمام سطوح اعمال شود؛ Light/Dark/System و انتخاب ذخیره‌شده آزموده شوند. کنترل دارای data-theme نباید مالک تم شود.
- بلوک مشترک بدون تأیید قابل ارجاع تازه نباشد؛ به‌روزرسانی مصرف‌کننده Draft جدید بسازد و اجرای گذشته حفظ شود.
- بله/خیر الزامی با false معتبر باشد؛ عدد نامعتبر و گزینه خارج از تعریف رد شود؛ تعریف تاریخی تغییر نکند.
- نمای شخصی کاربر دیگر دیده نشود؛ نمای تیمی هرگز مجوز بخش را دور نزند. پلن پویا در زمان شروع اجرا دامنه منجمد بگیرد.
- تغییر نیازمندی، تأیید سازگاری نسخه جاری را لازم کند؛ Pass قدیمی پوشش نسخه تازه ایجاد نکند.
- نمونه ۲×۲×۲ هشت قلم یکتا بسازد؛ پارامتر مفقود خطا دهد؛ ویرایش منبع داده Snapshot اقلام را عوض نکند؛ جمع‌بندی چند ترکیب بدون Pass کاذب باشد.
- آزمون‌های ثبت نتیجه، Retest، تاریخچه تغییرناپذیر، نقش، دسترسی بخش، شواهد، ورود/خروج Excel و گردش نقص از بخش 25 همچنان برقرار باشند.

داده نمونه جدید: بلوک ورود با شماره همراه نسخه ۱ تأییدشده؛ تست اول فروشگاه نسخه ۲ با ارجاع به بلوک، ولی اجرای تاریخی آن نسخه ۱ باقی می‌ماند؛ دو فیلد حوزه کسب‌وکار و گروه مشتری؛ نمای تیمی پرداخت و نمای شخصی تستر؛ پروفایل Chrome/Firefox دسکتاپ. همه نیازمندی‌های اولیه بازبینی محتوای ۱ دارند. این اختلاف نسخه عمدی است و برای نشان دادن حفظ تاریخچه استفاده می‌شود.

### کنترل نسخه و شواهد بررسی

- فایل مرجع: `afra-ui-v4.html`
- اندازه: 1608135 بایت
- SHA-256: `5552368f5d52ed34e87519a617226b053059b2c50daf6c3b37cdcff091075f8e`
- بررسی اجراشده: ۲۰ سناریوی تعاملی قبلی و ۷ گروه بررسی تکمیلی در jsdom؛ بارگذاری مسیرهای همه گروه‌ها در فارسی/انگلیسی و روشن/تاریک، توکن زمینه، مراحل مشترک، حریم نمای شخصی، انواع فیلد، ترکیب داده و نسخه نیازمندی.
- این بررسی‌ها DOM و منطق و توکن CSS را پوشش می‌دهند؛ بازبینی تصویری در مرورگر واقعی، اندازه‌گیری پیکسل و آزمون بک‌اند انجام نشده و نباید انجام‌شده معرفی شوند.
- وابستگی‌های فونت و ابزار Excel داخل HTML هستند؛ برای مشاهده فایل، نصب Node/PHP یا دسترسی شبکه لازم نیست.

## 35. اصلاحات الزامی نسخه 3.2

این بخش مشخصات رابط در بخش‌های قبلی را برای تاریخ، قالب، اندازه متن و اعتبارسنجی تکمیل می‌کند و در تعارض ظاهری مقدم است.

### تاریخ هجری شمسی در همه زبان‌ها

تمام تاریخ‌های قابل نمایش به کاربر، از تاریخچه اجرا، خطا، نسخه‌ها، رویداد، پیوست، موعد پلن و انتشار تا فیلد سفارشی، باید بر مبنای تقویم هجری شمسی باشند؛ انتخاب انگلیسی فقط زبان و جهت را تغییر دهد و تقویم میلادی نشود. برای زمان رویداد از Asia/Tehran استفاده شود؛ تاریخ بدون ساعت مانند موعد، تاریخ خالص است و با منطقه زمانی جابه‌جا نشود. داده زمان‌دار در UTC/ISO و تاریخ خالص در YYYY-MM-DD استاندارد ذخیره شود؛ این قالب داخلی نباید به‌جای تاریخ شمسی در UI نمایش داده شود.

انتخابگر بومی date مرورگر که تقویم میلادی نشان می‌دهد با انتخابگر داخلی شمسی جایگزین شود. ورودی خوانای YYYY/MM/DD، پذیرش ارقام فارسی و عربی و لاتین، انتخاب روز، ماه و سال، ماه قبل/بعد، امروز، پاک کردن، بستن و کنترل صفحه‌کلید Escape و ArrowDown لازم است. شروع هفته شنبه باشد. روز ناموجود و اسفند غیرکبیسه رد شوند. نمونه مرجع: ۱۴۰۵/۰۸/۲۶ برابر با 2026-11-17، ۱۳۹۹/۱۲/۳۰ معتبر و ۱۴۰۰/۱۲/۳۰ نامعتبر است. محدودیت min/max در صورت تعریف رعایت شود. انتخابگر روی موعد پلن/اجرا، تاریخ انتشار و فیلد سفارشی تاریخی اعمال شود؛ مقادیر قبلی بدون تغییر داده به شمسی نمایش داده شوند.

### قالب تست با خروجی هر مورد

قالب به‌جای یک Textarea چندخطی برای مراحل، ویرایشگر موردبه‌مورد داشته باشد. هر مورد شامل متن مرحله، نتیجه مورد انتظار همان مرحله و الزامی/اختیاری بودن است. برای ذخیره قالب، متن و خروجی هر مورد الزامی‌اند و خطای مورد مشخص شود. نتیجه کلی تست نیز مستقل باقی بماند. ساخت قالب از تست موجود و استفاده از قالب در تست جدید، checkMeta شامل expected و required را حفظ کند. ویرایش/بازکردن دوباره قالب نباید خروجی مراحل را از دست بدهد. قالب قدیمی فاقد checkMeta قابل بازکردن باشد؛ کاربر هنگام ویرایش خروجی موارد را تکمیل کند و داده ساختگی به سابقه اضافه نشود.

### محل دکمه و خطاهای ساخت تست

دکمه «افزودن مورد» بعد از آخرین مورد چک‌لیست قرار گیرد، هم در تست جدید/ویرایش و هم در قالب. افزودن، فوکوس را به مورد تازه ببرد؛ نتیجه مورد انتظار مراحل قبلی حفظ شود. از تغییر اندازه فرم برای این جابه‌جایی پرهیز شود.

قبل از ذخیره فرم، خطاهای تمام فیلدهای نامعتبر در کادر ماندگار بالای عملیات فرم با role=alert نمایش داده شوند. نام فیلد، شماره مورد چک‌لیست و دلیل الزامی بودن یا مقدار نامعتبر مشخص باشد؛ فوکوس به اولین فیلد مشکل‌دار برود، aria-invalid و رنگ مناسب داشته باشد. وابستگی انحصاری به پیام موقت بومی مرورگر مجاز نیست. خطای ذخیره نباید بی‌صدا رخ دهد؛ فرم و مقادیر آن حفظ شوند و پیام قابل فهم داده شود. ثبت تست معتبر همچنان نسخه Draft با چک‌لیست و داده کامل بسازد.

### تاریخچه خطا و خوانایی

در هر رویداد تاریخچه نقص، نام فرد در خط مستقل و شرح/یادداشت رویداد دقیقاً زیر آن نمایش داده شود؛ متن چندخطی حفظ و متن بلند شکسته شود. وضعیت و تاریخ همچنان در سر رویداد بمانند. رشد فونت مطابق بخش 20 محدود است؛ ابعاد سایدبار، کارت، دکمه و عرض Drawer تغییر اجباری نکند. دارک و نارنجی نسخه پیشین حفظ شوند.

### شواهد تکمیلی

۵ گروه آزمون تازه شامل تبدیل و کبیسه شمسی، اعتبارسنجی/ساخت تست، چرخه ساخت/مصرف/ویرایش قالب با خروجی هر مرحله، انتخابگر تاریخ و نمایش تاریخچه نقص عبور کردند. ۲۰ سناریوی رگرسیون پیشین و ۷ گروه قابلیت‌های نسخه سوم نیز برقرارند. این‌ها آزمون DOM و منطق هستند؛ ارزیابی تصویری در مرورگر واقعی هنوز انجام نشده است.

## 36. اصلاح خطای مرورگر و تجربه موبایل نسخه 3.2

این بخش برای جزئیات مربوط به خطای ثبت، فونت و تجربه موبایل بر نسخه‌های پیشین مقدم است.

### علت خطای startsWith و اصلاح پایدار

فیلد مجموعه داده در فرم تست‌کیس name=dataset دارد. در مرورگر، Named Property Access فرم ممکن است این کنترل را به‌جای ویژگی استاندارد form.dataset برگرداند. بنابراین form.dataset.kind مقدار undefined می‌گیرد و startsWith هنگام ثبت خطا می‌دهد. این رفتار در شبیه‌ساز DOM قبلی به‌شکل طبیعی رخ نداده بود.

تمام خواندن‌های نوع فرم از getAttribute('data-kind') و شناسه فرم از getAttribute('data-id') انجام شوند؛ دسترسی form.dataset برای مسیریابی ذخیره ممنوع است. نام فیلد مجموعه داده و داده کاربران حفظ شوند. اصلاح صرفاً پنهان کردن پیام یا تبدیل undefined به رشته نیست؛ نوع واقعی فرم باید درست خوانده و مسیر ثبت درست اجرا شود. ساخت و ویرایش تست همراه با مجموعه داده، مراحل، خروجی مورد انتظار و فیلد سفارشی باید ذخیره شوند. اعتبارسنجی نامعتبر نیز نام فیلد و مقدار فرم را حفظ کند.

معیار پذیرش ویژه مرورگر: به‌صورت صریح dataset فرم را با کنترل select مجموعه داده shadow کنید، نبود kind روی کنترل را تأیید و ثبت موفق را بررسی کنید. آزمون جداگانه ویرایش نسخه تست و خطای عنوان خالی را با همین تداخل اجرا کند. شبیه‌ساز به‌تنهایی نباید به‌عنوان تأیید همه رفتارهای مرورگر معرفی شود.

### باتم‌شیت در موبایل

در عرض حداکثر 760px، Drawerها و Modalها به شیت پایین صفحه تبدیل شوند: گوشه‌های بالایی گرد، دستگیره، عنوان و بستن در سر ثابت، محتوای دارای اسکرول مستقل و عملیات ذخیره/لغو در پایین ثابت. فرم بلند حدود 90 درصد فضای قابل مشاهده را بگیرد؛ عملیات کوتاه ارتفاع متناسب محتوا تا سقف فضای قابل مشاهده داشته باشند. دستگیره با لمس ارتفاع را تغییر دهد و با کلیک وضعیت عادی/گسترده را عوض کند. کشیدن شیت نباید بدون تأیید داده فرم را حذف کند؛ در این نمونه فقط ارتفاع عوض می‌شود و بستن از دکمه انجام می‌شود.

ارتفاع شیت با visualViewport و صفحه‌کلید هماهنگ شود؛ فوتر از فضای قابل مشاهده بیرون نرود. Safe Area پایین گوشی رعایت شود. اسکرول محتوا مستقل و overscroll محدود باشد. هنگام باز شدن فرم موبایل، فوکوس اولیه روی دستگیره قرار گیرد تا صفحه‌کلید بی‌درخواست باز نشود. پس‌زمینه در حالت dialog با inert از تعامل و فوکوس خارج و هنگام بستن دوباره فعال شود؛ فوکوس به کنترل آغازکننده برگردد. ورودی hidden نباید جزو چرخه Tab باشد. بستن با Escape و دکمه قابل دسترس باقی بماند.

### ناوبری و کنترل‌های لمسی

نوار ثابت پایین با چهار ورودی نمای کلی، تست‌کیس‌ها، اجرا و بیشتر اضافه شود. «بیشتر» تمام هفت فضای کاری، تعویض پروژه و تنظیمات را در یک شیت نشان دهد؛ هفت گروه اصلی حذف نشوند. همبرگر موبایل نیز همین مسیر را باز کند. محتوای صفحه زیر نوار پایین پنهان نشود. تب‌ها نام کامل و اسکرول افقی مستقل داشته باشند.

کنترل‌های اصلی و دکمه‌های عملیات حداقل 44px ارتفاع داشته باشند؛ ورودی موبایل 16px، عنوان شیت 18px و دکمه‌های آیکن فضای لمس کافی داشته باشند. این افزایش حداقلی موبایل برای لمس مجاز است؛ ابعاد ثابت و فاصله‌های دسکتاپ بی‌دلیل رشد نکنند. تقویم شمسی در موبایل به‌شکل شیت پایین با روزهای قابل لمس و Safe Area نمایش داده شود. دارک و RTL/LTR برای تمام حالت‌ها برقرار باشند. حرکت شیت در prefers-reduced-motion حذف شود.

### شواهد بررسی

سه گروه آزمون جدید برای ساخت با dataset سایه‌انداخته، ویرایش/اعتبارسنجی با همین تداخل و مسیر شیت/ناوبری موبایل عبور کردند. ۲۰ آزمون رگرسیون، ۷ گروه قابلیت‌های نسخه سوم و ۵ گروه اصلاحات قبلی نیز عبور کردند. بررسی تصویری در دستگاه یا مرورگر واقعی انجام نشده؛ پوشش کنونی شامل DOM، منطق و قراردادهای CSS/ساختار واکنش‌گرا است.

## 37. اصلاح چک‌لیست اجرا، دسترسی تست مجدد و راهنمای کاربران — نسخه 3.3

### چیدمان پایدار چک‌لیست

چک‌لیست اجرا نباید تمام فرزندان خود را در یک خط Flex جا دهد؛ عرض کامل ردیف یادداشت باعث فشرده شدن متن مرحله به یک حرف می‌شد. برای هر مورد از Grid با سه مسیر شماره، minmax(0,1fr) برای متن و دکمه‌ها استفاده شود. متن مرحله و خروجی همان مرحله شکستن خط طبیعی و حداقل عرض صفر داشته باشند. یادداشت در ردیف مستقل و عرض مناسب قرار گیرد؛ شاهد و کنارگذاشتن مورد اختیاری حفظ شوند. در عرض متوسط/موبایل، دکمه‌ها زیر متن و یادداشت زیر آن قرار گیرند. متن موفق/ناموفق کنار نماد نمایش داده شود؛ عنوان قابل دسترس و aria-pressed دکمه حفظ شود. افزایش طول متن، یادداشت، نتیجه مورد انتظار یا تغییر زبان نباید متن را زیر دکمه‌ها ببرد. تغییر نمای ظاهری نباید نتیجه، پیش‌نویس یا سابقه را عوض کند.

### بستن خطا و تست مجدد قابل پیدا کردن

در جزئیات خطای رفع‌شده، تفاوت رفع‌شده و بسته توضیح داده شود. پیام رد بستن، مسیر اقدام را بگوید: از همان خطا تست مجدد بسازید، پس از زمان رفع نتیجه موفق را ثبت کنید و سپس برگردید و خطا را ببندید. تیک‌های پیش‌نویس، نتیجه قبل از رفع یا اجرای بدون defectId همان خطا کافی نیست. نتیجه موفقِ واجد شرایط با پیام آمادگی بستن نشان داده شود؛ محدودیت قبلی بستن حفظ می‌شود.

دکمه «تست مجدد» در پایین جزئیات خطا قابل مشاهده باشد. علاوه بر مجوز مدیریت برنامه، آزمایشگر یا جانشین تخصیص‌یافته به تست/قلم مبدأ، با مجوز مشاهده و اجرای همان بخش و پروژه فعال، بتواند تست مجددِ مرتبط ایجاد کند. این استثنا فقط برای تست مجدد همین خطاست؛ به معنی اعطای مدیریت عمومی برنامه نیست. عمل ایجاد نیز مستقل از ظاهر دکمه مجوز را بررسی کند. مشاهده‌گر و آزمایشگر غیرمسئول نباید از فراخوانی مستقیم تابع، اجرا بسازند. اگر دکمه غیرمجاز است، غیرفعال و علت روشن باشد؛ نبود تست مرتبط، بایگانی پروژه یا نبود مجوز بدون توضیح پنهان نشود.

اگر خطا به قلم مشخصی از اجرای چندپیکربندی مربوط است، همان itemId مبدأ برای تست مجدد انتخاب شود، نه اولین قلم دارای caseId یکسان. تصویر ثابت متن و زمینه اجرای مبدأ در این نمونه حفظ می‌شود؛ کاربر باید آن را با محیط واقعی تطبیق دهد. برای خطای دستی بدون قلم مبدأ، تست مرتبط تأییدشده و محیط/ساخت معتبر لازم است. اجرا با defectId و parentRunId مرتبط ثبت و نتیجه‌های قبلی حفظ شوند.

### راهنمای Word فارسی

فایل afra-user-guide-fa.docx همراه محصول تحویل شود. دو فصل اصلی داشته باشد: اصول تضمین کیفیت، مفاهیم، تشریح کامل تست‌کیس با مثال و سپس راهنمای کامل و مرحله‌به‌مرحله سامانه. فارسی ساده مقدم باشد؛ اصطلاح خارجی فقط برای رفع ابهام یا پیدا کردن کنترل واقعی استفاده شود. مسیر نقش‌ها، قالب/مراحل مشترک، اثر تغییر نیازمندی، مجموعه داده، نما، پیکربندی، نتیجه/پیش‌نویس، خطا/تست مجدد، گزارش، شواهد، ورود داده، تاریخ شمسی، موبایل و رفع مشکلات پوشش داده شوند.

راهنمای تحویل‌شده در ویرایش طراحی دوم، در تبدیل مرجع به PDF، ۳۲ صفحه با ۸ تصویر شماتیک آموزشی و جلد اختصاصی است. تصویرها صریحاً آموزشی معرفی شده‌اند، نه عکس مرورگر. سند Word دارای عنوان‌های قابل پیمایش، جدول‌ها، متن راست‌چین، شماره صفحه و واژه‌نامه است. صفحه‌بندی Word در دستگاه دیگر ممکن است با توجه به فونت تغییر کند. فایل Word از نظر ساختار بسته و تصاویر و نسخه PDF از نظر خوانایی/جهت بررسی شده‌اند.

### معیارهای پذیرش تکمیلی

- هر مورد اجرای تست متن و خروجی خوانا، دکمه نام‌دار و ردیف یادداشت مستقل داشته باشد؛ حالت موفق/ناموفق پس از کلیک حفظ شود.
- آزمایشگر تخصیص‌یافته بدون managePlans بتواند از جزئیات خطا تست مجددِ مرتبط بسازد و نتیجه ثبت کند؛ مشاهده‌گر و آزمایشگر نامرتبط مسدود باشند.
- انتخاب تیک به‌تنهایی شرط بستن نباشد؛ ثبت موفق بعد از resolvedAt روی اجرای همان defectId شرط را فراهم کند.
- آزمون‌های نسخه‌های قبلی شامل تداخل dataset، تقویم شمسی، نسخه‌بندی، شواهد و تاریخچه همچنان عبور کنند. ارزیابی بصری همه عرض‌ها در مرورگر واقعی انجام نشده و نباید انجام‌شده معرفی شود.

## 38. هویت بصری و قالب Word راهنما — ویرایش طراحی دوم

نسخه راهنمای سامانه ۳.۳ باقی می‌ماند؛ این ویرایش مربوط به طراحی سند است. محتوای آموزشی دو فصل و هشت تصویر حفظ شود.

- فونت فارسی دقیقاً Vazir (وزیر، نه Vazirmatn) باشد. نسخه معمولی و ضخیمِ قابل تعبیه داخل DOCX قرار گیرند. فونت غیرفارسی Cambria تنظیم شود: rFonts.ascii/hAnsi روی Cambria و rFonts.cs روی Vazir؛ متن لاتین در متن ترکیبی با جهت و فونت مناسب پردازش شود. Cambria در این فایل تعبیه نشده و نمایش آن به دسترسی دستگاه به این فونت بستگی دارد.
- جلد مستقل تمام‌صفحه با زمینه کرم، نارنجی زنده، پنل پایین زغالی، نشان برگ سازگار با لوگوی افرا، عنوان اصلی و توضیح دو فصل ساخته شود. جلد هدر و شماره صفحه نداشته باشد؛ نام و تاریخ ویرایش روی آن درج شود.
- صفحات داخلی هدر دارای لوگو و خط نارنجی و فوتر دارای خط لطیف، نام راهنما، نسخه و فیلدهای PAGE و NUMPAGES داشته باشند.
- فهرست مطالب یک فیلد واقعی Word با دستور TOC \o "1-2" \h \z \u باشد، نه جدول دستی. چهل عنوان فصل/بخش به Heading 1/2، Bookmark و پیوندهای داخلی مرتبط شوند. محتوای کش‌شده فهرست و شماره صفحه‌های آن پیش از تحویل با خروجی صفحه‌بندی کنترل شوند. به‌روزرسانی با کلیک راست روی فهرست و Update Field / Update entire table در Word ممکن باشد؛ updateFields فعال شود.
- جدول‌ها سرستون تکرارشونده، نوارهای متناوب ملایم، متن راست‌چین و سطر غیرقابل شکستن داشته باشند. عنوان‌ها با متن پس از خود بمانند و تصویر با شرحش از هم جدا نشوند. نکته‌های مهم با زمینه نارنجی لطیف متمایز شوند. متن فارسی خوانا و نمودارها نیز با وزیر بازتولید شوند.
- در تبدیل مرجع ویرایش دوم، راهنما ۳۲ صفحه است؛ صفحه‌بندی نهایی می‌تواند با نرم‌افزار یا فونت دستگاه تغییر کند. تعبیه دو فونت، نگاشت فونت‌های لاتین/فارسی، ساختار فیلد TOC، چهل مقصد پیوند، شماره صفحات کش‌شده و تمامیت بسته DOCX بررسی شده‌اند. فایل PDF صرفاً برای بازبینی محلی تولید شده و تحویل اصلی Word است.

## 39. دامنه افزوده نسخه ۴ — کاربران، گروه‌ها، JWT و سرویس بیرونی

این بخش بر محدودیت‌ها و شرح نسخه‌های قبلی در موارد کاربران، بستن اجباری و سرویس بیرونی مقدم است. شماره نسخه محصول/راهنمای قدیم را با نسخه این نیازمندی اشتباه نگیرید. راهنمای Word فعلی مربوط به گردش‌های نسخه۳٫۳ است؛ مرجع قابلیت‌های جدید، همین سند و READMEهای مستقل BackEnd و FrontEnd است.

### 39.1. حساب و پروفایل

عکس پروفایل، active و تاریخ پایان دسترسی، تولد، کد ملی، موبایل، واحد، سمت، کد پرسنلی و درباره من اضافه شوند. کاربر بتواند نام، موبایل، تولد، عکس و توضیح خود را تکمیل کند؛ کد ملی فقط تکمیل اولیه و اصلاح بعدی با مدیر باشد. نقش، ایمیل سازمانی، انقضا و اطلاعات سازمانی توسط خود کاربر تغییر نکند. تصویر جایگزین حروف اول و پیش‌نمایش/حذف داشته باشد؛ حداکثر512KiB، PNG/JPEG/WebP و بازکدگذاری مربعی. داده واقعی شخصی در نمونه آفلاین وارد نشود.

فعال/غیرفعال کردن و انقضا مستقل از عضویت پروژه‌اند. پایان دسترسی آخرین روز مجاز شمسی است؛ کاربر از ابتدای روز بعد در تهران منقضی می‌شود. انقضا/غیرفعالی، ادامه نشست و التفویض را نیز متوقف کند، نه فقط ورود تازه. آخرین مدیر فعال دائمی حفظ شود. کاربر غیرفعال از تاریخچه حذف نشود و برای مسئول اجرای جدید قابل انتخاب نباشد.

### 39.2. رمز و JWT

کاربر رمز خود را با رمز فعلی و جدید/تأیید تغییر دهد. هنگام ایجاد، مدیر رمز اولیه تعیین و تغییر آن در اولین ورود را با پیش‌فرض روشن اجبار کند. مدیر سامانه بازنشانی با رمز موقت تازه و الزام تغییر دارد؛ رمز قبلی نمایش داده نشود. تغییر/ریست/غیرفعالی نشست‌های قبل را باطل کند.

محصول عملیاتی: JWT دسترسی RS256 با عمر۱۵ دقیقه در حافظه React؛ Refresh مات چرخشی و HttpOnly/Secure با خانواده۷ روز؛ بررسی iss/aud/exp/jti/sid/auth_version و وضعیت زنده حساب/ACL؛ حفاظت CORS/CSRF؛ بدون token در localStorage. ورود اجباری challenge پنج‌دقیقه‌ای فقط برای تغییر رمز دارد، نه توکن مجاز محصول. سمت Laravel رمز Argon2id است. نمونه HTML سرور JWT یا احراز هویت سازمانی نیست؛ صرفاً گردش نمایشی با Web Crypto/PBKDF2 و داده محلی دارد.

### 39.3. مجوزهای ریز، نقش و گروه

قلم اصلی منو افزایش نیابد. در کاربران و دسترسی، نقش سفارشی، گروه پروژه و grant مستقیم کاربر همگی permission عملیاتی داشته باشند. گروه می‌تواند چندعضوی و کاربر عضو چند گروه باشد. هر entry دارای inherit/allow/deny است؛ منع صریح از هر منبع بر اجازه مقدم، و محدودیت مؤثر بخش و مسئولیت نیز لازم‌اند. مدیر سامانه استثنای مدیریتی دارد؛ گروه پروژه نمی‌تواند مجوز سامانه‌ای اعطا کند. مدیر محدود فقط زیرمجموعه اختیار خودش را اعطا کند و نتواند خود را admin کند. مدیریت اعضا، مدیریت دسترسی و مدیریت کاربران سامانه مجوزهای متفاوت‌اند.

مجوز ساخت با ویرایش/حذف/تخصیص یکسان نیست. در ویرایش تست، تغییر مسئول یا جانشین cases.assign لازم دارد؛ در ساخت اولیه، خودتخصیصی مجاز سیاست پیش‌فرض است ولی تخصیص دیگران بدون حق ممنوع است. رابط دکمه غیرمجاز را غیرفعال و علت را نشان دهد؛ درخواست و Policy سرور مستقل آن را بررسی کنند. Endpoint مستقیم هم باید همان deny را رعایت کند.

### 39.4. بستن اجباری

مسیر عادی Pass تازه مرتبط همچنان برقرار است. مجوز مستقل defects.force_close به مدیر سامانه، مدیر پروژه و کاربران صریحاً مجاز اجازه بستن بدون تست مجدد می‌دهد. دکمه مستقل، هشدار «تأیید رفع نیست»، دلیل۱۰..۱۰۰۰ نویسه و تأیید لازم است. history و audit، شخص/زمان/دلیل و closure_type=forced را حفظ کنند؛ verified_at=null و هیچ نتیجه یا Pass جدیدی ساخته نشود. نقص بسته اجباری در گزارش از بسته تأییدشده جدا شود. بازگشایی نشان جاری را تغییر می‌دهد اما تاریخچه پاک نمی‌شود.

### 39.5. سرویس‌ها و تفویض

به درخواست جدید مالک، کاتالوگ محدود سرویس بیرونی اکنون داخل دامنه است. مدیر سامانه سرویس، مصرف‌کننده، scopes، پروژه‌ها، انقضا، نرخ درخواست، کلید و تفویض را از سامانه مدیریت کند. کاتالوگ اولیه: POST ثبت تست‌کیس و GET گزارش پیشرفت تست‌ها «بر اساس هر پروژه». project_id برای گزارش الزامی است؛ فیلترهای برنامه/انتشار/تستر/بازه زمانی فرعی‌اند، نه مبنای تجمیع بی‌اجازه همه پروژه‌ها.

هر دو سرویس پیش‌فرض خاموش‌اند؛ ساخت endpoint اجرایی با کد/URL دلخواه مجاز نیست. سرویس ثبت فقط نسخه Draft با بازبینی انسانی می‌سازد و حق Pass/Approve ندارد. Idempotency-Key اجباری، شناسه‌های هم‌پروژه و مجوز تخصیص کنترل شوند. گزارش تعداد تست یکتا، اقلام پیکربندی، Pass/Fail/Blocked/Skipped/NotRun، مخرج درست پیشرفت و بسته اجباری تأییدنشده را جدا برگرداند.

مصرف‌کننده دارای scopes و پروژه‌های محدود، expiry، rate و کلید یک‌بارنمایش است؛ راز در SPA نگهداری نشود. JWT سرویس aud جدا و پنج‌دقیقه‌ای بدون Refresh است؛ توکن داخلی/خارجی به جای هم پذیرفته نشوند. تفویض شامل دو کاربر متفاوت، client، scope/project محدود، انقضا و دلیل؛ یک سطح و بدون بازتفویض ضمنی. اختیار مؤثر اشتراک مجوز زنده تفویض‌کننده، گیرنده، client و سرویس است. غیرفعالی، انقضا، لغو یا از دست رفتن حق هر طرف فوراً تفویض را غیرمؤثر کند. Token exchange با actor/subject مجزا انجام و هر دو Audit شوند. جزئیات اجرایی در دو README مستقل همین تحویل موجود است.

### 39.6. مرز نمونه و محصول

HTML نسخه۴ فرم‌ها و ثبت metadata حساب/گروه/سرویس را به‌صورت محلی نشان می‌دهد. هیچ JWT معتبر، HTTP endpoint بیرونی، کلید تولیدی قابل مصرف یا سرور Laravel از این فایل اجرا نمی‌شود. تولید واقعی کاربران چندنفره، JWT و سرویس وظیفه کد آینده مطابق اسناد مستقل است. مدیر سرویس نمونه صرفاً تنظیمات و وضعیت مؤثر تفویض را تجربه می‌کند. داده محلی و Hash نمونه را امنیت عملیاتی معرفی نکنید.

### 39.7. معیار پذیرش تازه

- ساخت کاربر با داده کامل و Hash نمونه، ورود با رمز صحیح، توقف اولین ورود تا تغییر، تغییر رمز خود و reset مدیر بررسی شوند؛ رمز خام ذخیره نشود.
- حساب غیرفعال/منقضی، نشست، ثبت نتیجه و delegation را متوقف کند؛ آخرین مدیر قفل نشود. ملی/موبایل/تولد معتبر و عکس با محدودیت روشن باشد.
- گروه بتواند create بدهد و update را deny کند؛ ویرایش مستقیم هم متوقف شود؛ نوع نقش و دسترسی بخش حفظ شود.
- مجوز Force close مستقل با دلیل عمل کند، Pass نسازد، verified_at خالی و تاریخچه روشن بماند؛ حذف مجوز فوراً اثر کند.
- سرویس/مصرف‌کننده scopes و پروژه‌ها، حد زمان و لغو داشته باشند؛ درخواست تفویض فراتر از اختیار رد شود؛ گزارش فقط پروژه مجاز را پوشش دهد.
- درختواره دسکتاپ تقریباً290px و موبایل جمع‌پذیر باشد؛ هفت منوی اصلی، دارک واقعی، شمسی، باتم‌شیت و اصلاح چک‌لیست حفظ شوند.

### 39.8. کاتالوگ ۷۷ کلید ثابت عملیات

کلیدهای دسته مثل sections.manage مجوز کلی می‌دهند و مجوز عملیاتی مثل sections.delete مستقل قابل منع است؛ deny دسته و فرزند بر allow مقدم‌اند. جستجوی عملیات در فرم مجوز وجود داشته باشد.

| کلید | عنوان فارسی |
|---|---|
| `project.view` | مشاهده پروژه |
| `project.update` | ویرایش پروژه |
| `cases.create` | ساخت تست |
| `cases.update` | ویرایش تست |
| `cases.delete` | حذف/بایگانی تست |
| `cases.assign` | تخصیص تست |
| `cases.export` | خروجی تست |
| `cases.import` | ورود گروهی تست |
| `sections.manage` | مدیریت بخش‌ها |
| `cases.review` | بازبینی تست |
| `shared.manage` | مراحل مشترک |
| `templates.manage` | قالب و داده |
| `views.manage` | نماهای تیمی |
| `requirements.create` | ساخت نیازمندی |
| `requirements.update` | ویرایش نیازمندی |
| `requirements.delete` | حذف نیازمندی |
| `plans.create` | ساخت برنامه |
| `plans.update` | ویرایش برنامه |
| `plans.delete` | حذف برنامه |
| `runs.create` | شروع اجرا |
| `runs.execute` | ثبت نتیجه تخصیص‌یافته |
| `runs.pause` | توقف/ادامه اجرا |
| `runs.retest` | تست مجدد تخصیص‌یافته |
| `defects.create` | ساخت خطا |
| `defects.update` | ویرایش خطا |
| `defects.transition` | تغییر وضعیت خطا |
| `defects.close` | بستن با تأیید رفع |
| `defects.force_close` | بستن اجباری بدون تست |
| `releases.manage` | مدیریت انتشار |
| `environments.manage` | محیط و ساخت |
| `reports.read` | گزارش و پوشش |
| `reports.export` | خروجی گزارش |
| `attachments.read` | مشاهده و دریافت شواهد |
| `attachments.upload` | بارگذاری شاهد |
| `attachments.delete` | حذف پیوست مجاز |
| `comments.write` | نوشتن گفتگو |
| `members.manage` | اعضای پروژه |
| `access.manage` | گروه و مجوز پروژه |
| `fields.manage` | فیلد سفارشی |
| `system.users.manage` | مدیریت کاربران سامانه |
| `system.services.manage` | مدیریت سرویس و تفویض |
| `sections.create` | ساخت بخش |
| `sections.update` | ویرایش بخش |
| `sections.delete` | حذف بخش |
| `shared.create` | ساخت بلوک مشترک |
| `shared.update` | ویرایش بلوک مشترک |
| `shared.archive` | بایگانی بلوک مشترک |
| `shared.request_review` | درخواست بازبینی بلوک |
| `shared.update_cases` | اعمال نسخه بلوک روی تست‌ها |
| `templates.create` | ساخت قالب |
| `templates.update` | ویرایش قالب |
| `datasets.create` | ساخت داده تست |
| `datasets.update` | ویرایش داده تست |
| `profiles.create` | ساخت پروفایل اجرا |
| `profiles.update` | ویرایش پروفایل اجرا |
| `releases.create` | ساخت انتشار |
| `releases.update` | ویرایش انتشار |
| `environments.create` | ساخت محیط |
| `environments.update` | ویرایش محیط |
| `builds.create` | ساخت Build |
| `builds.update` | ویرایش Build |
| `members.add` | افزودن عضو پروژه |
| `members.update` | ویرایش عضویت |
| `members.remove` | حذف عضویت |
| `roles.create` | ساخت نقش سفارشی |
| `roles.update` | ویرایش نقش سفارشی |
| `roles.delete` | حذف نقش سفارشی |
| `groups.create` | ساخت گروه دسترسی |
| `groups.update` | ویرایش گروه و اعضا |
| `grants.update` | تغییر مجوز مستقیم |
| `section_access.manage` | تغییر قواعد دسترسی بخش |
| `fields.create` | ساخت فیلد سفارشی |
| `fields.update` | ویرایش تعریف فیلد |
| `fields.archive` | غیرفعال کردن فیلد |
| `cases.request_review` | درخواست بازبینی تست |
| `shared.approve` | تأیید نسخه بلوک مشترک |
| `requirements.confirm_compatibility` | تأیید سازگاری نیازمندی |

### 39.9. بررسی تحویل نسخه۴

۷ گروه اصلی و ۴ گروه تکمیلی آزمون تازه برای صفحه‌های کاربر/سرویس، ساخت حساب با Hash و اجبار تغییر، ورود/تغییر/پروفایل، غیرفعالی و انقضا، گروه create/deny update، Force close بدون Pass و تفویض محدود/لغوشونده اجرا شده‌اند. ۲۰ سناریوی رگرسیون نسخه پیشین نیز عبور کرده‌اند. ۴ گروه تکمیلی آخرین مدیر، بازنشانی/نسخه اعتبارنامه، اعتبار هویت/تاریخ و تقدم منع/هویت ناموجود نیز بررسی شده‌اند. بررسی DOM و منطق نمونه جای آزمون امنیت Laravel یا ارزیابی بصری مرورگر واقعی نیست.

## 40. اصلاحات تجربه کاربری، گزارش و چاپ — نسخه ۴٫۱

این بخش ده نکته ثبت‌شده مالک را اجرایی می‌کند و در موارد ظاهر، ناوبری حساب، مجوز و چاپ بر شکل‌های نسخه‌های پیشین مقدم است. قواعد کیفیت، JWT، انقضای حساب و تفویض نسخه۴ حفظ می‌شوند.

### 40.1. تاریخ و آیکون مستقل

متن تاریخ عددی در هر دو زبان LTR و آیکون تقویم در ستون جدا باشد. کنترل در Grid با minmax(0,1fr) و ستون آیکون42px (موبایل44px) چیده شود؛ استفاده از آیکون absolute روی متن با padding مبتنی بر جهت متفاوت ممنوع است. تاریخ کامل، انتخاب شمسی، خطا و فوکوس در RTL/LTR و عرض320px باقی بمانند. فیلتر تاریخ ناموجود یا بازه معکوس، پیام روشن بدهد و خروجی را مسدود کند؛ متن نامعتبر کاربر پاک و با فیلتر قدیمی بی‌صدا جایگزین نشود.

### 40.2. حساب از آواتار هدر

بخش نام/عکس/پروفایل/تنظیمات/خروج پایین سایدبار حذف شود و فقط یک آواتار تعاملی در هدر بماند. Popup شامل هویت خلاصه و چهار مقصد روشن است: پروفایل من، تغییر رمز، ظاهر و زبان، خروج. دو دکمه هم‌عملکرد پروفایل/تنظیمات ادغام شوند. ترجیحات ظاهری از پروفایل اطلاعاتی تفکیک شوند. در موبایل آواتار دیده شود و میان‌برهای تکراری تم/زبان هدر از طریق همان منو در دسترس بمانند. در پروفایل نیز سربرگ هویتی تکراری حذف شود.

Menu دارای role/aria، Arrow/Home/End، Escape و click-away باشد؛ فوکوس به آغازکننده برگردد و روی resize سرریز نکند. باز شدن منوی حساب، پنجره اعلان هم‌زمان باقی نگذارد. الزام تغییر رمز، گزینه‌های دیگر را مجاز نمی‌کند.

### 40.3. ابزار عکس

پیش‌نمایش عکس و ستون ابزار از هم جدا شوند؛ انتخاب/حذف در ردیف Flex wrap، راهنما در خط زیر و نام دکمه‌ها بدون روی‌هم‌افتادگی. حذف در نبود عکس غیرفعال باشد. در گوشی باریک، چیدمان می‌تواند تک‌ستونی شود. قواعد حجم/نوع/بازکدگذاری قبلی و حفظ داده خصوصی تغییر نکنند.

### 40.4. سوییچ در برابر انتخاب فهرستی

حالت‌های دوتایی مانند active حساب/گروه/فیلد، system admin، must change password، required مرحله/پیکربندی، اعلان، داده آموزشی و جزئیات/نمودار چاپ، با Switch و checked boolean واقعی نمایش داده شوند. role=switch، aria-checked، Space و focus واضح لازم‌اند. Checkbox برای انتخاب چند کاربر/تست/پروژه/Scope/ستون باقی بماند و Radio سه‌حالته مجوز، Switch نیست.

### 40.5. حذف متن تزئینی زائد

متن AFRA / TEAM و برچسب‌های هم‌خانواده بالای عنوان صفحات حذف شوند. شعار انگلیسی تزئینی تکراری صفحه ورود نیز لازم نیست. عنوان، نام پروژه، مسیر ناوبری و کد عملکردی تست/خطا/اجرا حذف نشوند.

### 40.6. فیلتر رویداد پروژه

بازه تاریخ شمسی دو سر شامل، کاربر انجام‌دهنده، نوع عملیات، نوع موجودیت و جستجو با هم کار کنند؛ پاک کردن، شمارنده و صفحه‌بندی20تایی وجود داشته باشد. تغییر فیلتر صفحه را به1 ببرد؛ خروجی تمام filtered rows است. محدوده همان پروژه و بخش‌های مجاز است. Audit تاریخی با حذف منبع پاک نشود. در محصول سروری operation code مستقل از زبان و section snapshot ثبت شود؛ نمونه قدیمی برای سازگاری می‌تواند نوع را از متن عنوان استنباط کند و این را داده ساختاری اصیل معرفی نکند.

### 40.7. مجوزهای دسته‌بندی‌شده و آیکون‌دار

کاتالوگ77کلیدی در دسته‌های منابع محصول، با عنوان، تعداد و باز/جمع شدن باشد. مقابل هر عملیات سه دکمه جداگانه ارث‌بری/اجازه/منع با آیکون پیوند/تیک/ضربدر، حالت انتخاب واضح، Legend و Tooltip قرار گیرند. نام و معنی حق از رنگ تنها فهمیده نشود. پیاده‌سازی native Radio/AntD Radio یا Segmented با دسترسی صفحه‌کلید و screen reader است، نه dropdown مبهم.

جستجو و جمع شدن گروه نباید مقدار فیلد را حذف کنند؛ مجوزهای مخفی همچنان در FormData/state باشند. payload و قواعد inherit/allow/deny نسخه۴ تغییر نکند. عنوان عملیات انعطاف‌پذیر و نوار انتخاب ثابت است تا در موبایل هم متن تک‌حرفی نشود.

### 40.8. گزارش مصرف سرویس‌های بیرونی

فقط مدیر سامانه در تب «گزارش مصرف سرویس» زیر فضای پروژه و افراد؛ از کاتالوگ نیز پیوند داشته باشد. فیلتر زمان، سرویس، مصرف‌کننده، پروژه، outcome و request_id/error_code؛ جدول، جزئیات، CSV و PDF. آمار کل، درصد2xx، میانه و p95 از تمام filtered dataset، پیش از صفحه‌بندی محاسبه شود. 4xx،5xx و سایر پاسخ‌ها جدا و مجموعشان با total سازگار باشد. p95 nearest-rank و میانه زوج میانگین دو مقدار وسط است. داده شخصی، رمز، token، client_secret، headers یا body خام به گزارش وارد نشود.

نمونه HTML هیچ درخواست واقعی سرویس ثبت نمی‌کند. Actual ledger ابتدا خالی است؛ Switch «نمایش داده آموزشی» یک مجموعه48درخواستیِ مشخص با Banner آشکار نشان می‌دهد. این داده‌ها ذخیره عملیاتی یا با actual ادغام نشوند؛ نوع منبع در CSV/PDF هم روشن باشد. لغو مصرف‌کننده سابقه گزارش را پاک نمی‌کند.

در محصول واقعی، middleware سرور metadata مجاز را ثبت، Queue تکرار را با request_id یکتا حذف و retention/ایندکس مناسب اعمال کند. گزارش مدیریت مصرف می‌تواند همه پروژه‌های مجاز را ببیند؛ این با سرویس بیرونی پیشرفت که project_id الزامی دارد تفاوت دارد.

### 40.9. نمای کلی پربار و قابل اقدام

Hero نام پروژه و فیلتر انتشار، شش کارت KPI: تعداد تست یکتا، پیشرفت اقلام، نرخ موفقیت با مخرج کل، پوشش تأییدشده نیازمندی، خطای بحرانی باز و انتشار آماده. نوار وضعیت/Legend، روند حداکثر شش اجرای واقعی اخیر، آمادگی انتشارها، کارهای خود کاربر، جدول بار کاری تیم و رویدادهای اخیر وجود داشته باشند. هیچ نمودار زمانی خیالی برای تزئین تولید نشود.

صف اقدام برای بازبینی منتظر، اثر تغییر نیازمندی، نتیجه قدیمی، بسته اجباری تأییدنشده و نبود مسئول فعال باشد. کلیک هر کارت فهرست همان موارد شمارش‌شده در همان scope را باز کند. Pass قدیمی و سازگاری متنی جای اجرای تازه برای نسخه نیازمندی را نمی‌گیرند. صفر داده با0 و Empty state، نه درصد ساختگی/NaN، نمایش داده شود. منوی اصلی همان هفت گروه می‌ماند.

### 40.10. گزارش مستقل برای PDF/چاپ

چاپ از data model مجاز و DOM کاغذی مستقل ساخته شود، نه عکس صفحه یا چاپ منوها. Preview شامل عنوان اختیاری، A4 portrait/landscape، Switch جزئیات و نمودار، دامنه فیلتر، پروژه، تاریخ شمسی/ساعت تهران، تهیه‌کننده، نسخه و reference باشد. مقصد ذخیره PDF مرورگر توضیح داده شود. نام پیش‌فرض فایل از عنوان گزارش گرفته و پس از چاپ، عنوان صفحه برنامه برگردانده شود. فونت پیش از چاپ بارگذاری شود.

کاغذ سفید با متن تیره خوانا، لوگوی داخلی، header نارنجی، footer و شماره صفحه در موتور پشتیبان، جدول با سرستون تکراری و ردیف غیرقابل شکستن، wrap متن و بدون overflow مخفی. چاپ تمام سطرهای فیلترشده است، نه صفحه جاری. جدول ماتریس عریض به بلوک چندستونی با تکرار نیازمندی تقسیم شود و ستون حذف نشود. خروجی اجرای انتخابی نسخه تست، Build، وضعیت و زمان/انجام‌دهنده نتیجه و چک‌لیست Snapshot را نشان دهد. در گزارش کیفیت، بسته اجباری جدا و بدون Pass/verified_at باشد.

Activity، Usage، Cases، Execution، ماتریس نیازمندی، پوشش پیکربندی و گزارش کیفیت از همین زیرساخت استفاده کنند. Export بدون مجوز یا با فیلتر نامعتبر متوقف شود. اطلاعات فرمِ باز، رمز یا کلید یک‌بارمصرف به Print DOM منتقل نشوند. بعد از چاپ/لغو، DOM موقت پاک شود و کاربر به همان زمینه قبلی برگردد. دارک برنامه باقی بماند؛ روشن بودن کاغذ preview عمداً مربوط به سند است.

### 40.11. شواهد بررسی و فایل نمونه

۸ گروه آزمون تازه: منوی حساب/حذف تکرار، چیدمان مستقل تاریخ و عکس، سوییچ و Radioهای دسته‌بندی‌شده با حفظ مقدار جستجو، فیلتر و تاریخ نامعتبر و مرز تهران، منبع آموزشی/واقعی و عدم افشای راز، مخرج داشبورد و تازگی نتیجه، چاپ همه سطرها و پاک‌سازی، و منع دسترسی غیرمجاز به مصرف سرویس. ۲۰ سناریوی رگرسیون و۱۱گروه حساب/دسترسی نسخه۴ نیز عبور کرده‌اند؛ جمع39گروه منطقی/تعاملی.

نمونه PDF در docs/samples/afra-service-usage.pdf از همان report markup و با PDF renderer مستقل ساخته شده است:4صفحه، هر48شناسه درخواست و لوگوی تعبیه‌شده کنترل شده و نمونه صفحات از نظر خوانایی/سرستون/فوتر بازبینی شده‌اند. داده آن صریحاً آموزشی است. این بررسی جای تست بصری همه مرورگرها یا چاپگر واقعی نیست. jsdom به‌تنهایی margin box استاندارد @page را نمی‌فهمد؛ آن بخش با موتور PDF مستقل بررسی شده است.


## الحاقیه نهایی استقرار — نسخه ۵

تعداد ۱۰٬۰۰۰ تا ۱۰۰٬۰۰۰، کل حساب‌های ثبت‌شده است نه کاربران هم‌زمان. یک سازمان با تیم‌های متعدد داریم. ظرفیت هم‌زمان باید اندازه‌گیری شود. سورس متعارف Laravel 13 با PHP 8.4 و فرانت مستقل React/TypeScript/Ant Design ارائه شود. چند نود پشت لودبالانسر با PostgreSQL و Redis مشترک و کلیدهای یکسان، نشست/ابطال/صف/محدودیت نرخ مشترک لازم است. هر پیوست حداکثر ۲۰ MiB و ترجیحاً در قطعه‌های رمز‌شده دیتابیس؛ تاریخچه و پیوست‌ها دائمی. عملیات مسئول پشتیبان است ولی کلیدها و بازیابی باید مستند شود. اتصال به سرور سازمان لازم نیست. فایل‌های اجرای عادی و Docker، ساخت دیتابیس و داده پیش‌فرض ارائه شود؛ مدیر اولیه با فرمان امن بدون رمز ثابت در Git ساخته شود. همه قابلیت‌های پذیرفته‌شده باقی است. ورودی قدیمی برای مهاجرت نداریم ولی قابلیت ورود فایل حذف نشود. گزارش سرویس اکسپوزی بر اساس هر پروژه است و شناسه پروژه الزامی است. مصرف سرویس فعلاً درون سازمان است و همچنان مجوز کامل لازم دارد.

این سند، نیازمندی محصول نهایی است؛ برای وضعیت واقعی کد فایل docs/implementation-status.fa.md را ببینید. پیش‌نمایش HTML برنامه عملیاتی نیست.


## فهرست کامل مجوزهای هدف

| Key | عنوان |
|---|---|
| `project.view` | مشاهده پروژه |
| `project.update` | ویرایش پروژه |
| `cases.create` | ساخت تست |
| `cases.update` | ویرایش تست |
| `cases.delete` | حذف/بایگانی تست |
| `cases.assign` | تخصیص تست |
| `cases.export` | خروجی تست |
| `cases.import` | ورود گروهی تست |
| `sections.manage` | مدیریت بخش‌ها |
| `cases.review` | بازبینی تست |
| `shared.manage` | مراحل مشترک |
| `templates.manage` | قالب و داده |
| `views.manage` | نماهای تیمی |
| `requirements.create` | ساخت نیازمندی |
| `requirements.update` | ویرایش نیازمندی |
| `requirements.delete` | حذف نیازمندی |
| `plans.create` | ساخت برنامه |
| `plans.update` | ویرایش برنامه |
| `plans.delete` | حذف برنامه |
| `runs.create` | شروع اجرا |
| `runs.execute` | ثبت نتیجه تخصیص‌یافته |
| `runs.pause` | توقف/ادامه اجرا |
| `runs.retest` | تست مجدد تخصیص‌یافته |
| `defects.create` | ساخت خطا |
| `defects.update` | ویرایش خطا |
| `defects.transition` | تغییر وضعیت خطا |
| `defects.close` | بستن با تأیید رفع |
| `defects.force_close` | بستن اجباری بدون تست |
| `releases.manage` | مدیریت انتشار |
| `environments.manage` | محیط و ساخت |
| `reports.read` | گزارش و پوشش |
| `reports.export` | خروجی گزارش |
| `attachments.upload` | بارگذاری شاهد |
| `attachments.delete` | حذف پیوست مجاز |
| `comments.write` | نوشتن گفتگو |
| `members.manage` | اعضای پروژه |
| `access.manage` | گروه و مجوز پروژه |
| `fields.manage` | فیلد سفارشی |
| `system.users.manage` | مدیریت کاربران سامانه |
| `system.services.manage` | مدیریت سرویس و تفویض |
| `sections.create` | ساخت بخش |
| `sections.update` | ویرایش بخش |
| `sections.delete` | حذف بخش |
| `shared.create` | ساخت بلوک مشترک |
| `shared.update` | ویرایش بلوک مشترک |
| `shared.archive` | بایگانی بلوک مشترک |
| `shared.request_review` | درخواست بازبینی بلوک |
| `shared.update_cases` | اعمال نسخه بلوک روی تست‌ها |
| `templates.create` | ساخت قالب |
| `templates.update` | ویرایش قالب |
| `datasets.create` | ساخت داده تست |
| `datasets.update` | ویرایش داده تست |
| `profiles.create` | ساخت پروفایل اجرا |
| `profiles.update` | ویرایش پروفایل اجرا |
| `releases.create` | ساخت انتشار |
| `releases.update` | ویرایش انتشار |
| `environments.create` | ساخت محیط |
| `environments.update` | ویرایش محیط |
| `builds.create` | ساخت Build |
| `builds.update` | ویرایش Build |
| `members.add` | افزودن عضو پروژه |
| `members.update` | ویرایش عضویت |
| `members.remove` | حذف عضویت |
| `roles.create` | ساخت نقش سفارشی |
| `roles.update` | ویرایش نقش سفارشی |
| `roles.delete` | حذف نقش سفارشی |
| `groups.create` | ساخت گروه دسترسی |
| `groups.update` | ویرایش گروه و اعضا |
| `grants.update` | تغییر مجوز مستقیم |
| `section_access.manage` | تغییر قواعد دسترسی بخش |
| `fields.create` | ساخت فیلد سفارشی |
| `fields.update` | ویرایش تعریف فیلد |
| `fields.archive` | غیرفعال کردن فیلد |
| `cases.request_review` | درخواست بازبینی تست |
| `shared.approve` | تأیید نسخه بلوک مشترک |
| `requirements.confirm_compatibility` | تأیید سازگاری نیازمندی |

سیاست نگهداری مصوب: تاریخچه، شواهد، پیوست‌ها و گزارش مصرف سرویس به‌صورت پیش‌فرض دائمی نگهداری می‌شوند. پاک‌سازی خودکار فعال نیست؛ سیاست کوتاه‌تر برای مصرف سرویس فقط با تصمیم صریح بعدی سازمان مجاز است.
