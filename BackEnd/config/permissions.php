<?php

return json_decode(<<<'CATALOG'
[
  {
    "key": "project.view",
    "category": "view",
    "fa": "مشاهده پروژه",
    "en": "View project"
  },
  {
    "key": "project.update",
    "category": "manageMembers",
    "fa": "ویرایش پروژه",
    "en": "Edit project"
  },
  {
    "key": "cases.create",
    "category": "manageCases",
    "fa": "ساخت تست",
    "en": "Create case"
  },
  {
    "key": "cases.update",
    "category": "manageCases",
    "fa": "ویرایش تست",
    "en": "Edit case"
  },
  {
    "key": "cases.delete",
    "category": "manageCases",
    "fa": "حذف/بایگانی تست",
    "en": "Delete/archive case"
  },
  {
    "key": "cases.assign",
    "category": "manageCases",
    "fa": "تخصیص تست",
    "en": "Assign case"
  },
  {
    "key": "cases.export",
    "category": "viewReports",
    "fa": "خروجی تست",
    "en": "Export cases"
  },
  {
    "key": "cases.import",
    "category": "manageCases",
    "fa": "ورود گروهی تست",
    "en": "Import cases"
  },
  {
    "key": "sections.manage",
    "category": "manageCases",
    "fa": "مدیریت بخش‌ها",
    "en": "Manage sections"
  },
  {
    "key": "cases.review",
    "category": "review",
    "fa": "بازبینی تست",
    "en": "Review cases"
  },
  {
    "key": "shared.manage",
    "category": "manageCases",
    "fa": "مراحل مشترک",
    "en": "Manage shared steps"
  },
  {
    "key": "templates.manage",
    "category": "manageCases",
    "fa": "قالب و داده",
    "en": "Manage templates and data"
  },
  {
    "key": "views.manage",
    "category": "manageCases",
    "fa": "نماهای تیمی",
    "en": "Manage team views"
  },
  {
    "key": "requirements.create",
    "category": "manageRequirements",
    "fa": "ساخت نیازمندی",
    "en": "Create requirement"
  },
  {
    "key": "requirements.update",
    "category": "manageRequirements",
    "fa": "ویرایش نیازمندی",
    "en": "Edit requirement"
  },
  {
    "key": "requirements.delete",
    "category": "manageRequirements",
    "fa": "حذف نیازمندی",
    "en": "Delete requirement"
  },
  {
    "key": "plans.create",
    "category": "managePlans",
    "fa": "ساخت برنامه",
    "en": "Create plan"
  },
  {
    "key": "plans.update",
    "category": "managePlans",
    "fa": "ویرایش برنامه",
    "en": "Edit plan"
  },
  {
    "key": "plans.delete",
    "category": "managePlans",
    "fa": "حذف برنامه",
    "en": "Delete plan"
  },
  {
    "key": "runs.create",
    "category": "managePlans",
    "fa": "شروع اجرا",
    "en": "Start execution"
  },
  {
    "key": "runs.execute",
    "category": "execute",
    "fa": "ثبت نتیجه تخصیص‌یافته",
    "en": "Record assigned results"
  },
  {
    "key": "runs.pause",
    "category": "managePlans",
    "fa": "توقف/ادامه اجرا",
    "en": "Pause/resume execution"
  },
  {
    "key": "runs.retest",
    "category": "execute",
    "fa": "تست مجدد تخصیص‌یافته",
    "en": "Retest assigned cases"
  },
  {
    "key": "defects.create",
    "category": "manageDefects",
    "fa": "ساخت خطا",
    "en": "Create defect"
  },
  {
    "key": "defects.update",
    "category": "manageDefects",
    "fa": "ویرایش خطا",
    "en": "Edit defect"
  },
  {
    "key": "defects.transition",
    "category": "manageDefects",
    "fa": "تغییر وضعیت خطا",
    "en": "Transition defect"
  },
  {
    "key": "defects.close",
    "category": "manageDefects",
    "fa": "بستن با تأیید رفع",
    "en": "Close verified defect"
  },
  {
    "key": "defects.force_close",
    "category": null,
    "fa": "بستن اجباری بدون تست",
    "en": "Force close without retest"
  },
  {
    "key": "releases.manage",
    "category": "manageReleases",
    "fa": "مدیریت انتشار",
    "en": "Manage releases"
  },
  {
    "key": "environments.manage",
    "category": "manageReleases",
    "fa": "محیط و ساخت",
    "en": "Manage environments and builds"
  },
  {
    "key": "reports.read",
    "category": "viewReports",
    "fa": "گزارش و پوشش",
    "en": "View reports and coverage"
  },
  {
    "key": "reports.export",
    "category": "viewReports",
    "fa": "خروجی گزارش",
    "en": "Export reports"
  },
  {
    "key": "attachments.read",
    "category": "viewReports",
    "fa": "مشاهده و دریافت شواهد",
    "en": "View and download evidence"
  },
  {
    "key": "attachments.upload",
    "category": "execute",
    "fa": "بارگذاری شاهد",
    "en": "Upload evidence"
  },
  {
    "key": "attachments.delete",
    "category": "manageCases",
    "fa": "حذف پیوست مجاز",
    "en": "Delete permitted attachments"
  },
  {
    "key": "comments.write",
    "category": "execute",
    "fa": "نوشتن گفتگو",
    "en": "Write comments"
  },
  {
    "key": "members.manage",
    "category": "manageMembers",
    "fa": "اعضای پروژه",
    "en": "Manage project members"
  },
  {
    "key": "access.manage",
    "category": "manageMembers",
    "fa": "گروه و مجوز پروژه",
    "en": "Manage project access"
  },
  {
    "key": "fields.manage",
    "category": "manageMembers",
    "fa": "فیلد سفارشی",
    "en": "Manage custom fields"
  },
  {
    "key": "system.users.manage",
    "category": null,
    "fa": "مدیریت کاربران سامانه",
    "en": "Manage system users"
  },
  {
    "key": "system.services.manage",
    "category": null,
    "fa": "مدیریت سرویس و تفویض",
    "en": "Manage services and delegation"
  },
  {
    "key": "sections.create",
    "category": "manageCases",
    "fa": "ساخت بخش",
    "en": "Create section"
  },
  {
    "key": "sections.update",
    "category": "manageCases",
    "fa": "ویرایش بخش",
    "en": "Edit section"
  },
  {
    "key": "sections.delete",
    "category": "manageCases",
    "fa": "حذف بخش",
    "en": "Delete section"
  },
  {
    "key": "shared.create",
    "category": "manageCases",
    "fa": "ساخت بلوک مشترک",
    "en": "Create shared block"
  },
  {
    "key": "shared.update",
    "category": "manageCases",
    "fa": "ویرایش بلوک مشترک",
    "en": "Edit shared block"
  },
  {
    "key": "shared.archive",
    "category": "manageCases",
    "fa": "بایگانی بلوک مشترک",
    "en": "Archive shared block"
  },
  {
    "key": "shared.request_review",
    "category": "manageCases",
    "fa": "درخواست بازبینی بلوک",
    "en": "Request block review"
  },
  {
    "key": "shared.update_cases",
    "category": "manageCases",
    "fa": "اعمال نسخه بلوک روی تست‌ها",
    "en": "Update cases from shared block"
  },
  {
    "key": "templates.create",
    "category": "manageCases",
    "fa": "ساخت قالب",
    "en": "Create template"
  },
  {
    "key": "templates.update",
    "category": "manageCases",
    "fa": "ویرایش قالب",
    "en": "Edit template"
  },
  {
    "key": "datasets.create",
    "category": "manageCases",
    "fa": "ساخت داده تست",
    "en": "Create dataset"
  },
  {
    "key": "datasets.update",
    "category": "manageCases",
    "fa": "ویرایش داده تست",
    "en": "Edit dataset"
  },
  {
    "key": "profiles.create",
    "category": "managePlans",
    "fa": "ساخت پروفایل اجرا",
    "en": "Create execution profile"
  },
  {
    "key": "profiles.update",
    "category": "managePlans",
    "fa": "ویرایش پروفایل اجرا",
    "en": "Edit execution profile"
  },
  {
    "key": "releases.create",
    "category": "manageReleases",
    "fa": "ساخت انتشار",
    "en": "Create release"
  },
  {
    "key": "releases.update",
    "category": "manageReleases",
    "fa": "ویرایش انتشار",
    "en": "Edit release"
  },
  {
    "key": "environments.create",
    "category": "manageReleases",
    "fa": "ساخت محیط",
    "en": "Create environment"
  },
  {
    "key": "environments.update",
    "category": "manageReleases",
    "fa": "ویرایش محیط",
    "en": "Edit environment"
  },
  {
    "key": "builds.create",
    "category": "manageReleases",
    "fa": "ساخت Build",
    "en": "Create build"
  },
  {
    "key": "builds.update",
    "category": "manageReleases",
    "fa": "ویرایش Build",
    "en": "Edit build"
  },
  {
    "key": "members.add",
    "category": "manageMembers",
    "fa": "افزودن عضو پروژه",
    "en": "Add project member"
  },
  {
    "key": "members.update",
    "category": "manageMembers",
    "fa": "ویرایش عضویت",
    "en": "Edit membership"
  },
  {
    "key": "members.remove",
    "category": "manageMembers",
    "fa": "حذف عضویت",
    "en": "Remove membership"
  },
  {
    "key": "roles.create",
    "category": "manageMembers",
    "fa": "ساخت نقش سفارشی",
    "en": "Create custom role"
  },
  {
    "key": "roles.update",
    "category": "manageMembers",
    "fa": "ویرایش نقش سفارشی",
    "en": "Edit custom role"
  },
  {
    "key": "roles.delete",
    "category": "manageMembers",
    "fa": "حذف نقش سفارشی",
    "en": "Delete custom role"
  },
  {
    "key": "groups.create",
    "category": "manageMembers",
    "fa": "ساخت گروه دسترسی",
    "en": "Create access group"
  },
  {
    "key": "groups.update",
    "category": "manageMembers",
    "fa": "ویرایش گروه و اعضا",
    "en": "Edit group and members"
  },
  {
    "key": "grants.update",
    "category": "manageMembers",
    "fa": "تغییر مجوز مستقیم",
    "en": "Edit direct grants"
  },
  {
    "key": "section_access.manage",
    "category": "manageMembers",
    "fa": "تغییر قواعد دسترسی بخش",
    "en": "Edit section access"
  },
  {
    "key": "fields.create",
    "category": "manageMembers",
    "fa": "ساخت فیلد سفارشی",
    "en": "Create custom field"
  },
  {
    "key": "fields.update",
    "category": "manageMembers",
    "fa": "ویرایش تعریف فیلد",
    "en": "Edit custom field"
  },
  {
    "key": "fields.archive",
    "category": "manageMembers",
    "fa": "غیرفعال کردن فیلد",
    "en": "Disable custom field"
  },
  {
    "key": "cases.request_review",
    "category": "manageCases",
    "fa": "درخواست بازبینی تست",
    "en": "Request case review"
  },
  {
    "key": "shared.approve",
    "category": "review",
    "fa": "تأیید نسخه بلوک مشترک",
    "en": "Approve shared block"
  },
  {
    "key": "requirements.confirm_compatibility",
    "category": "review",
    "fa": "تأیید سازگاری نیازمندی",
    "en": "Confirm requirement compatibility"
  }
]
CATALOG, true, 512, JSON_THROW_ON_ERROR);
