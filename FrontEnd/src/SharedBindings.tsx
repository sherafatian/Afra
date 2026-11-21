import { useState } from "react";
import { App, Button, Card, InputNumber, Space, Typography } from "antd";
import { api } from "./api";
import { ReferencePicker } from "./ReferencePicker";
type Binding = { id: string; version: number; position: number };
export function SharedBindings({
  id,
  project,
  language,
  value = [],
  onChange,
  localCount,
  onDetach,
}: {
  id?: string;
  project: string;
  language: "fa" | "en";
  value?: Binding[];
  onChange?: (value: Binding[]) => void;
  localCount: number;
  onDetach: (binding: Binding, steps: any[]) => void;
}) {
  const { message } = App.useApp();
  const [details, setDetails] = useState<Record<string, any>>({});
  const [busy, setBusy] = useState(false);
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  const inspect = async (binding: Binding) => {
    const row = await api<any>(
      `v1/projects/${project}/shared-steps/${binding.id}/revisions/${binding.version}`,
    );
    setDetails((old) => ({ ...old, [binding.id]: row.snapshot }));
    return row.snapshot;
  };
  return (
    <Space orientation="vertical" style={{ width: "100%" }}>
      <Typography.Text type="secondary">
        {l(
          "نسخه تأییدشده ثابت می‌ماند. تغییر نسخه، یک پیش‌نویس جدید از تست می‌سازد و اجراهای قبلی را تغییر نمی‌دهد.",
          "Approved revisions are pinned. Changing a revision creates a new case draft and preserves past runs.",
        )}
      </Typography.Text>
      <ReferencePicker
        id={id}
        project={project}
        kind="shared-steps"
        placeholder={l(
          "افزودن مراحل مشترک تأییدشده",
          "Add approved shared steps",
        )}
        onChange={async (id) => {
          if (typeof id !== "string" || busy) return;
          setBusy(true);
          try {
            if (value.some((item) => item.id === id))
              throw new Error(
                l(
                  "این مجموعه قبلاً اضافه شده است.",
                  "This block is already linked.",
                ),
              );
            const row = await api<any>(
              `v1/projects/${project}/records/shared-steps/${id}`,
            );
            const approved = row.approved_version
              ? { version: row.approved_version }
              : null;
            if (!approved)
              throw new Error(
                l(
                  "ابتدا نسخه‌ای را با بازبین مستقل تأیید کنید.",
                  "An independently approved revision is required.",
                ),
              );
            const binding = {
              id,
              version: approved.version,
              position: localCount,
            };
            await inspect(binding);
            onChange?.([...value, binding]);
          } catch (e) {
            message.error(e instanceof Error ? e.message : String(e));
          } finally {
            setBusy(false);
          }
        }}
      />
      {value.map((binding) => (
        <Card
          size="small"
          key={binding.id}
          title={`${details[binding.id]?.title || l("مراحل مشترک", "Shared steps")} · v${binding.version}`}
        >
          <Space wrap>
            <Typography.Text>
              {l("پس از چند مورد محلی", "After local step count")}
            </Typography.Text>
            <InputNumber
              min={0}
              max={localCount}
              value={binding.position}
              onChange={(position) =>
                onChange?.(
                  value.map((item) =>
                    item.id === binding.id
                      ? { ...item, position: Number(position) }
                      : item,
                  ),
                )
              }
            />
            <Button
              onClick={async () => {
                try {
                  await inspect(binding);
                } catch (e) {
                  message.error(String(e));
                }
              }}
            >
              {l("مشاهده", "Inspect")}
            </Button>
            <Button
              onClick={async () => {
                try {
                  const snapshot = await inspect(binding);
                  onDetach(binding, snapshot.content.checklist);
                } catch (e) {
                  message.error(String(e));
                }
              }}
            >
              {l("تبدیل به موارد محلی", "Detach as local steps")}
            </Button>
            <Button
              onClick={async () => {
                try {
                  const row = await api<any>(
                    `v1/projects/${project}/records/shared-steps/${binding.id}`,
                  );
                  const approved = row.approved_version
                    ? { version: row.approved_version }
                    : null;
                  if (!approved || approved.version === binding.version) {
                    message.info(
                      l(
                        "نسخه تأییدشده جدیدتری وجود ندارد.",
                        "No newer approved revision.",
                      ),
                    );
                    return;
                  }
                  await inspect({ ...binding, version: approved.version });
                  onChange?.(
                    value.map((item) =>
                      item.id === binding.id
                        ? { ...item, version: approved.version }
                        : item,
                    ),
                  );
                } catch (e) {
                  message.error(String(e));
                }
              }}
            >
              {l(
                "انتخاب آخرین نسخه تأییدشده",
                "Choose latest approved revision",
              )}
            </Button>
            <Button
              danger
              onClick={() =>
                onChange?.(value.filter((item) => item.id !== binding.id))
              }
            >
              {l("حذف اتصال", "Unlink")}
            </Button>
          </Space>
          {details[binding.id]?.content.checklist.map(
            (step: any, index: number) => (
              <div key={index}>
                <Typography.Text strong>
                  {index + 1}. {step.text}
                </Typography.Text>
                <div>{step.expected}</div>
              </div>
            ),
          )}
        </Card>
      ))}
    </Space>
  );
}
