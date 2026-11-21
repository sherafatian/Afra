import { useEffect, useRef, useState } from "react";
import {
  App,
  Button,
  Card,
  Drawer,
  Space,
  Statistic,
  Table,
  Tag,
  Typography,
} from "antd";
import { api } from "./api";
import {
  buildQualityPaper,
  qualityCheckLabels,
  type Report,
  type ReportItem,
  type Locale,
} from "./QualityPaper";
export function QualityReport({
  project,
  kind,
  id,
  language,
  onClose,
  canExport,
}: {
  project: string;
  kind: "runs" | "releases";
  id: string;
  language: Locale;
  onClose: () => void;
  canExport: boolean;
}) {
  const { message } = App.useApp();
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  const [report, setReport] = useState<Report | null>(null);
  const [busy, setBusy] = useState(false);
  const request = useRef(0);
  const [mobile, setMobile] = useState(matchMedia("(max-width:600px)").matches);
  const path = `v1/projects/${project}/reports/${kind}/${id}`;
  useEffect(() => {
    const media = matchMedia("(max-width:600px)");
    const update = () => setMobile(media.matches);
    media.addEventListener("change", update);
    return () => media.removeEventListener("change", update);
  }, []);
  useEffect(() => {
    const generation = ++request.current;
    setBusy(true);
    setReport(null);
    api<Report>(path)
      .then((value) => {
        if (generation === request.current) setReport(value);
      })
      .catch((e) => {
        if (generation === request.current) message.error((e as Error).message);
      })
      .finally(() => {
        if (generation === request.current) setBusy(false);
      });
    return () => {
      request.current++;
    };
  }, [path]);
  const print = async () => {
    const generation = request.current;
    const popup = window.open("", "_blank");
    if (!popup) {
      message.error(
        l(
          "اجازه بازکردن پنجره چاپ را به مرورگر بدهید.",
          "Allow this site to open the print window.",
        ),
      );
      return;
    }
    popup.opener = null;
    try {
      const data = await api<Report>(path + "?export=1");
      if (generation !== request.current) {
        popup.close();
        return;
      }
      popup.document.open();
      popup.document.write(buildQualityPaper(data, language, location.origin));
      popup.document.close();
      await popup.document.fonts.ready;
      popup.focus();
      popup.print();
    } catch (e) {
      popup.close();
      message.error((e as Error).message);
    }
  };
  const checks = qualityCheckLabels(language);
  return (
    <Drawer
      open
      onClose={onClose}
      placement={mobile ? "bottom" : language === "fa" ? "left" : "right"}
      width={1150}
      height={mobile ? "95dvh" : undefined}
      rootClassName="editor-sheet"
      title={report?.title || l("گزارش کیفیت", "Quality report")}
      loading={busy}
      extra={
        <Button disabled={!report || !canExport} onClick={print}>
          {l("خروجی PDF / چاپ", "PDF / Print")}
        </Button>
      }
    >
      {report && (
        <>
          <Space wrap>
            <Card size="small">
              <Statistic
                title={l("تست یکتا", "Unique cases")}
                value={report.summary.unique_cases}
              />
            </Card>
            <Card size="small">
              <Statistic
                title={l("اقلام الزامی", "Required items")}
                value={report.summary.required_items}
              />
            </Card>
            <Card size="small">
              <Statistic
                title={l("اقلام اختیاری", "Optional items")}
                value={report.summary.optional_items}
              />
            </Card>
            <Card size="small">
              <Statistic
                title={l("موفقیت الزامی", "Required pass rate")}
                value={report.summary.required_pass_rate}
                suffix="%"
              />
            </Card>
          </Space>
          {report.ready !== undefined && (
            <Card style={{ marginBlock: 20 }}>
              <Typography.Title level={4}>
                {report.ready
                  ? l("شرط‌های انتشار برقرار است", "Release gates pass")
                  : l("انتشار تأیید نشده است", "Release is not verified")}
              </Typography.Title>
              <Space wrap>
                {Object.entries(report.checks || {}).map(([key, value]) => (
                  <Tag color={value ? "green" : "red"} key={key}>
                    {checks[key] || key}: {value ? "✓" : "×"}
                  </Tag>
                ))}
              </Space>
              <p>
                {l("خطای بحرانی باز", "Open critical defects")}:{" "}
                {report.open_critical}
              </p>
              <p>
                {l(
                  "بحرانی بسته‌شده بدون تأیید رفع",
                  "Unverified force-closed critical defects",
                )}
                : {report.forced_critical ?? 0}
              </p>
            </Card>
          )}
          <Table<ReportItem>
            rowKey="id"
            dataSource={report.summary.items}
            scroll={{ x: 850 }}
            pagination={{ pageSize: 25, showSizeChanger: false }}
            columns={[
              { title: l("تست", "Case"), dataIndex: "title" },
              { title: l("نسخه", "Revision"), dataIndex: "case_revision" },
              {
                title: l("پیکربندی", "Configuration"),
                render: (_, item) => item.configuration?.name || "—",
              },
              {
                title: l("ردیف داده", "Data row"),
                render: (_, item) =>
                  item.data?.row === undefined || item.data.row === null
                    ? "—"
                    : item.data.row + 1,
              },
              {
                title: l("الزام", "Requirement"),
                render: (_, item) =>
                  item.required
                    ? l("الزامی", "Required")
                    : l("اختیاری", "Optional"),
              },
              {
                title: l("نتیجه", "Outcome"),
                render: (_, item) => (
                  <Tag
                    color={
                      item.outcome === "pass"
                        ? "green"
                        : item.outcome === "fail"
                          ? "red"
                          : "orange"
                    }
                  >
                    {item.outcome}
                    {item.is_retest ? " · " + l("تست مجدد", "Retest") : ""}
                  </Tag>
                ),
              },
              { title: l("ثبت‌کننده", "Recorded by"), dataIndex: "actor_name" },
            ]}
          />
          <Typography.Paragraph type="secondary">
            {l(
              "گزارش آخرین مشاهده هر قلم و تست مجددِ مرتبط را نشان می‌دهد؛ تعداد تلاش‌ها مخرج درصد نیست.",
              "The report uses each item’s latest linked observation, including retests; attempts are not the denominator.",
            )}
          </Typography.Paragraph>
        </>
      )}
    </Drawer>
  );
}
