export type Locale = "fa" | "en";
export type ReportItem = {
  id: string;
  case_revision: number;
  title: string;
  required: boolean;
  configuration?: { name?: string };
  data?: { row?: number };
  outcome: string;
  actor_name?: string;
  recorded_at?: string;
  is_retest: boolean;
};
export type Summary = {
  total_items: number;
  unique_cases: number;
  required_items: number;
  optional_items: number;
  required_pass_rate: number;
  counts: Record<string, number>;
  required_counts: Record<string, number>;
  items: ReportItem[];
  cases: {
    case_id: string;
    title: string;
    variants: number;
    required_variants: number;
    outcome: string;
  }[];
};
export type Report = {
  title: string;
  prepared_at: string;
  summary: Summary;
  ready?: boolean;
  checks?: Record<string, boolean>;
  open_critical?: number;
  forced_critical?: number;
};
export function qualityCheckLabels(language: Locale): Record<string, string> {
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  return {
    complete_scope: l(
      "دامنه کامل بدون بخش پنهان",
      "Complete scope without hidden sections",
    ),
    fresh_scope: l("منابع و دامنه جاری", "Current source revisions and scope"),
    has_required_items: l("وجود اقلام الزامی", "Required items exist"),
    required_execution_complete: l(
      "اقلام الزامی انجام شده‌اند",
      "Required items have been executed",
    ),
    pass_rate: l("حداقل درصد موفقیت", "Minimum pass rate"),
    open_critical: l("خطاهای بحرانی باز", "Open critical defects"),
    forced_critical: l(
      "بستن اجباری بحرانی بدون تأیید رفع",
      "Force-closed unverified critical defects",
    ),
    blocked: l("سیاست موارد مسدود", "Blocked-item policy"),
  };
}
const escape = (value: unknown) =>
  String(value ?? "").replace(
    /[&<>"']/g,
    (char) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        char
      ]!,
  );
export function buildQualityPaper(
  report: Report,
  language: Locale,
  origin: string,
) {
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  const date = new Intl.DateTimeFormat(
    language === "fa" ? "fa-IR-u-ca-persian" : "en-US-u-ca-persian",
    { timeZone: "Asia/Tehran", dateStyle: "medium", timeStyle: "short" },
  ).format(new Date(report.prepared_at));
  const outcomes: Record<string, string> = {
    pass: l("موفق", "Passed"),
    fail: l("ناموفق", "Failed"),
    blocked: l("مسدود", "Blocked"),
    skipped: l("اجرا نشده با دلیل", "Skipped"),
    not_run: l("اجرا نشده", "Not run"),
  };
  const rows = report.summary.items
    .map(
      (item, index) =>
        `<tr><td>${index + 1}</td><td>${escape(item.title)}</td><td>${item.case_revision}</td><td>${escape(item.configuration?.name || "—")}</td><td>${item.data?.row === undefined || item.data.row === null ? "—" : item.data.row + 1}</td><td>${escape(item.required ? l("الزامی", "Required") : l("اختیاری", "Optional"))}</td><td>${escape(outcomes[item.outcome] || item.outcome)}${item.is_retest ? " · " + escape(l("تست مجدد", "Retest")) : ""}</td><td>${escape(item.actor_name || "—")}</td></tr>`,
    )
    .join("");
  return `<!doctype html><html lang="${language}" dir="${language === "fa" ? "rtl" : "ltr"}"><head><meta charset="utf-8"><title>${escape(report.title)}</title><style>@font-face{font-family:Afra;src:url('${origin}/Vazirmatn.ttf')}*{box-sizing:border-box}body{font-family:Afra,Arial,sans-serif;color:#172331;background:white;margin:0;font-size:10pt}header{border-bottom:3px solid #f47714;padding-bottom:7mm;margin-bottom:6mm;display:flex;justify-content:space-between;gap:8mm}h1{font-size:18pt;margin:0 0 3mm}h2{font-size:12pt;color:#c75000;margin:0}small{color:#57606a}.metrics{display:flex;gap:4mm;margin:5mm 0;flex-wrap:wrap}.metric{border:1px solid #d4dae0;border-top:3px solid #f47714;padding:3mm 5mm;min-width:37mm}.metric strong{font-size:16pt;display:block}table{border-collapse:collapse;width:100%;margin-top:5mm}thead{display:table-header-group}th{background:#fff0e2;font-weight:bold}th,td{border:1px solid #cdd2d8;padding:2.5mm;text-align:start;vertical-align:top;overflow-wrap:anywhere}tr{break-inside:avoid}footer{margin-top:5mm;color:#57606a;font-size:9pt}.notice{padding:3mm;border:1px solid #e99c4e;background:#fff8ec}@page{size:A4 landscape;margin:12mm;@bottom-center{direction:ltr;unicode-bidi:isolate;content:counter(page) ' / ' counter(pages);font-size:9pt}}@media screen{body{padding:12mm;max-width:297mm;margin:auto;background:white}html{background:#dbe0e6}}@media print{a{color:inherit;text-decoration:none}}</style></head><body><header><div><h2>${l("افرا · گزارش کیفیت", "Afra · Quality report")}</h2><h1>${escape(report.title)}</h1><small>${escape(date)} · ${l("زمان تهران؛ هجری شمسی", "Tehran time; Solar Hijri")}</small></div><div>${l("آخرین مشاهده مرتبط با نسخه ثابت اجرا", "Latest linked observation of the frozen execution baseline")}</div></header><div class="metrics">${[
    [l("تست یکتا", "Unique cases"), report.summary.unique_cases],
    [l("اقلام اجرا", "Execution items"), report.summary.total_items],
    [l("اقلام الزامی", "Required items"), report.summary.required_items],
    [
      l("موفقیت الزامی", "Required pass rate"),
      report.summary.required_pass_rate + "%",
    ],
  ]
    .map(
      ([title, value]) =>
        `<div class="metric"><span>${escape(title)}</span><strong>${escape(value)}</strong></div>`,
    )
    .join(
      "",
    )}</div>${report.ready !== undefined ? `<p class="notice">${report.ready ? l("شرط‌های انتشار در دامنه کامل گزارش برقرارند.", "Release gates pass for the complete report scope.") : l("انتشار تأیید نشده است؛ شرط‌های ناموفق یا دامنه محدود را بررسی کنید.", "Release is not verified. Inspect failed gates or restricted scope.")}</p>` : ""}${
    report.checks
      ? `<section class="notice"><h2>${l("بررسی شرط‌های انتشار", "Release gate checks")}</h2><ul>${Object.entries(
          report.checks,
        )
          .map(
            ([key, value]) =>
              `<li>${value ? "✓" : "×"} ${escape(qualityCheckLabels(language)[key] || key)}</li>`,
          )
          .join(
            "",
          )}</ul><p>${l("خطای بحرانی باز", "Open critical defects")}: ${report.open_critical ?? 0} · ${l("بحرانی بسته‌شده بدون تأیید رفع", "Unverified force-closed critical defects")}: ${report.forced_critical ?? 0}</p></section>`
      : ""
  }<table><thead><tr>${["#", l("تست", "Case"), l("نسخه", "Revision"), l("پیکربندی", "Configuration"), l("ردیف داده", "Data row"), l("الزام", "Required"), l("نتیجه", "Outcome"), l("ثبت‌کننده", "Recorded by")].map((title) => `<th>${escape(title)}</th>`).join("")}</tr></thead><tbody>${rows}</tbody></table><footer>${l("تمام اقلام منطبق با دامنه گزارش درج شده‌اند. موارد اختیاری از مخرج درصد موفقیت الزامی خارج‌اند.", "All items in the report scope are included. Optional items are excluded from the required pass-rate denominator.")}</footer></body></html>`;
}
