import { useEffect, useState, type ReactNode } from "react";
import { api } from "./api";
import {
  App,
  Button,
  Card,
  Form,
  Input,
  InputNumber,
  Select,
  Space,
  Switch,
  Table,
  Typography,
} from "antd";
import { ReferencePicker } from "./ReferencePicker";
import { MemberPicker } from "./MemberPicker";
import { civilToSolar, solarToCivil } from "./solarDate";
type Locale = "fa" | "en";
const label = (language: Locale, fa: string, en: string) =>
  language === "fa" ? fa : en;
export function VariablesEditor({
  value = {},
  onChange,
  language = "fa",
}: {
  language?: Locale;
  value?: Record<string, string | number | boolean>;
  onChange?: (value: Record<string, string | number | boolean>) => void;
}) {
  const l = (fa: string, en: string) => label(language, fa, en);
  const { message } = App.useApp();
  const entries = Object.entries(value);
  return (
    <>
      <Space direction="vertical" style={{ width: "100%" }}>
        {entries.map(([key, val], index) => (
          <Space key={index} wrap>
            <Input
              aria-label={l("نام متغیر", "Variable name")}
              maxLength={50}
              dir="ltr"
              value={key}
              onChange={(e) => {
                if (
                  entries.some(
                    ([name], i) => i !== index && name === e.target.value,
                  )
                ) {
                  message.error(
                    l("نام متغیر تکراری است.", "Variable name already exists."),
                  );
                  return;
                }
                const next = entries.map(([k, v], i) => [
                  i === index ? e.target.value : k,
                  v,
                ]);
                onChange?.(Object.fromEntries(next));
              }}
            />
            <Input
              aria-label={l("مقدار متغیر", "Variable value")}
              value={String(val)}
              onChange={(e) => onChange?.({ ...value, [key]: e.target.value })}
            />
            <Button
              danger
              onClick={() =>
                onChange?.(
                  Object.fromEntries(entries.filter(([k]) => k !== key)),
                )
              }
            >
              ×
            </Button>
          </Space>
        ))}
      </Space>
      <Button
        onClick={() => {
          let key = "value" + (entries.length + 1);
          while (key in value) key += "x";
          onChange?.({ ...value, [key]: "" });
        }}
      >
        ＋
      </Button>
    </>
  );
}
export function SolarDateInput({
  value,
  onChange,
  id,
}: {
  value?: string;
  onChange?: (value: string | undefined) => void;
  id?: string;
}) {
  const display =
    value &&
    /^\d{4}-\d{2}-\d{2}$/.test(value) &&
    Number(value.slice(0, 4)) >= 1800
      ? civilToSolar(value)
      : value;
  return (
    <Input
      id={id}
      value={display}
      dir="ltr"
      placeholder="1405/09/30"
      onChange={(e) =>
        onChange?.(
          e.target.value
            ? solarToCivil(e.target.value) || e.target.value
            : undefined,
        )
      }
    />
  );
}
type Column = {
  key: string;
  type: "string" | "number" | "boolean" | "date";
  required: boolean;
};
type Dataset = { columns: Column[]; rows: Record<string, unknown>[] };
export function DatasetEditor({
  value = { columns: [], rows: [] },
  onChange,
  language,
}: {
  value?: Dataset;
  onChange?: (value: Dataset) => void;
  language: Locale;
}) {
  value = {
    columns: Array.isArray(value?.columns) ? value.columns : [],
    rows: Array.isArray(value?.rows) ? value.rows : [],
  };
  const l = (fa: string, en: string) => label(language, fa, en);
  const patch = (index: number, field: string, cell: unknown) =>
    onChange?.({
      ...value,
      rows: value.rows.map((row, i) =>
        i === index ? { ...row, [field]: cell } : row,
      ),
    });
  return (
    <Space direction="vertical" style={{ width: "100%" }}>
      <Typography.Text>
        {l(
          "ستون‌ها و نوع داده را تعریف کنید؛ صفر و مقدار «خیر» داده معتبرند.",
          "Define columns and types; zero and false are valid values.",
        )}
      </Typography.Text>
      {value.columns.map((column, index) => (
        <Card size="small" key={index}>
          <Space wrap>
            <Input
              aria-label={l("نام ستون", "Column name")}
              dir="ltr"
              value={column.key}
              onChange={(e) => {
                const key = e.target.value;
                if (
                  value.columns.some((col, j) => j !== index && col.key === key)
                )
                  return;
                const rows = value.rows.map((row) => {
                  const next = { ...row };
                  if (column.key in next) {
                    next[key] = next[column.key];
                    delete next[column.key];
                  }
                  return next;
                });
                onChange?.({
                  ...value,
                  columns: value.columns.map((col, i) =>
                    i === index ? { ...col, key } : col,
                  ),
                  rows,
                });
              }}
            />
            <Select
              value={column.type}
              options={[
                { value: "string", label: l("متن", "Text") },
                { value: "number", label: l("عدد", "Number") },
                { value: "boolean", label: l("بله/خیر", "Boolean") },
                { value: "date", label: l("تاریخ شمسی", "Solar Hijri date") },
              ]}
              onChange={(type) =>
                onChange?.({
                  ...value,
                  columns: value.columns.map((col, i) =>
                    i === index ? { ...col, type } : col,
                  ),
                })
              }
            />
            <Switch
              checked={column.required}
              onChange={(required) =>
                onChange?.({
                  ...value,
                  columns: value.columns.map((col, i) =>
                    i === index ? { ...col, required } : col,
                  ),
                })
              }
            />
            {l("الزامی", "Required")}
            <Button
              danger
              onClick={() =>
                onChange?.({
                  columns: value.columns.filter((_, i) => i !== index),
                  rows: value.rows.map((row) => {
                    const next = { ...row };
                    delete next[column.key];
                    return next;
                  }),
                })
              }
            >
              {l("حذف ستون", "Remove column")}
            </Button>
          </Space>
        </Card>
      ))}
      <Button
        disabled={value.columns.length >= 30}
        onClick={() =>
          onChange?.({
            ...value,
            columns: [
              ...value.columns,
              {
                key: "column" + (value.columns.length + 1),
                type: "string",
                required: true,
              },
            ],
          })
        }
      >
        {l("افزودن ستون", "Add column")}
      </Button>
      <Table
        rowKey="index"
        dataSource={value.rows.map((row, index) => ({ row, index }))}
        pagination={{ pageSize: 10, showSizeChanger: false }}
        scroll={{ x: Math.max(600, value.columns.length * 180) }}
        columns={[
          { title: "#", dataIndex: "index", render: (i: number) => i + 1 },
          ...value.columns.map((column) => ({
            title: column.key + (column.required ? " *" : ""),
            render: (
              _: unknown,
              item: { row: Record<string, unknown>; index: number },
            ) =>
              column.type === "number" ? (
                <InputNumber
                  value={item.row[column.key] as number}
                  onChange={(cell) => patch(item.index, column.key, cell)}
                />
              ) : column.type === "boolean" ? (
                <Select
                  allowClear
                  style={{ width: 120 }}
                  value={
                    typeof item.row[column.key] === "boolean"
                      ? String(item.row[column.key])
                      : undefined
                  }
                  options={[
                    { value: "true", label: l("بله", "True") },
                    { value: "false", label: l("خیر", "False") },
                  ]}
                  onChange={(cell) =>
                    patch(
                      item.index,
                      column.key,
                      cell === undefined ? null : cell === "true",
                    )
                  }
                />
              ) : column.type === "date" ? (
                <SolarDateInput
                  value={item.row[column.key] as string}
                  onChange={(cell) =>
                    patch(item.index, column.key, cell ?? null)
                  }
                />
              ) : (
                <Input
                  value={item.row[column.key] as string}
                  onChange={(e) =>
                    patch(item.index, column.key, e.target.value)
                  }
                />
              ),
          })),
          {
            title: "",
            render: (_: unknown, item: { index: number }) => (
              <Button
                danger
                onClick={() =>
                  onChange?.({
                    ...value,
                    rows: value.rows.filter((_, i) => i !== item.index),
                  })
                }
              >
                ×
              </Button>
            ),
          },
        ]}
      />
      <Button
        disabled={value.rows.length >= 500}
        onClick={() => onChange?.({ ...value, rows: [...value.rows, {}] })}
      >
        {l("افزودن ردیف داده", "Add data row")}
      </Button>
    </Space>
  );
}
type Node = {
  mode?: "and" | "or";
  rules?: Node[];
  field?: string;
  operator?: string;
  value?: unknown;
};
export function ConditionsEditor({
  value = { mode: "and", rules: [] },
  onChange,
  project,
  language,
}: {
  value?: Node;
  onChange?: (value: Node) => void;
  project: string;
  language: Locale;
}) {
  const l = (fa: string, en: string) => label(language, fa, en);
  const [sections, setSections] = useState<{ id: string; name: string }[]>([]);
  useEffect(() => {
    let active = true;
    api(`v1/projects/${project}/sections`)
      .then((rows) => {
        if (active) setSections(rows);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [project]);
  const render = (
    node: Node,
    change: (next: Node) => void,
    depth = 0,
  ): ReactNode =>
    node.rules ? (
      <Card
        size="small"
        title={
          <Select
            value={node.mode}
            options={[
              { value: "and", label: l("همه شرط‌ها", "All conditions") },
              { value: "or", label: l("حداقل یک شرط", "Any condition") },
            ]}
            onChange={(mode) => change({ ...node, mode })}
          />
        }
      >
        <Space direction="vertical" style={{ width: "100%" }}>
          {node.rules.map((child, index) => (
            <div key={index}>
              {render(
                child,
                (next) =>
                  change({
                    ...node,
                    rules: node.rules!.map((rule, i) =>
                      i === index ? next : rule,
                    ),
                  }),
                depth + 1,
              )}
              <Button
                type="text"
                danger
                onClick={() =>
                  change({
                    ...node,
                    rules: node.rules!.filter((_, i) => i !== index),
                  })
                }
              >
                {l("حذف شرط", "Remove condition")}
              </Button>
            </div>
          ))}
          <Space>
            <Button
              disabled={node.rules.length >= 20}
              onClick={() =>
                change({
                  ...node,
                  rules: [
                    ...node.rules!,
                    { field: "title", operator: "contains", value: "" },
                  ],
                })
              }
            >
              {l("افزودن شرط", "Add condition")}
            </Button>
            {depth < 2 && (
              <Button
                onClick={() =>
                  change({
                    ...node,
                    rules: [
                      ...node.rules!,
                      {
                        mode: "and",
                        rules: [
                          { field: "title", operator: "contains", value: "" },
                        ],
                      },
                    ],
                  })
                }
              >
                {l("گروه شرط", "Condition group")}
              </Button>
            )}
          </Space>
        </Space>
      </Card>
    ) : (
      <Space wrap>
        <Select
          value={node.field}
          style={{ width: 170 }}
          options={[
            ["title", l("عنوان", "Title")],
            ["priority", l("اولویت", "Priority")],
            ["status", l("وضعیت بازبینی", "Review state")],
            ["type", l("نوع تست", "Test type")],
            ["tag", l("برچسب", "Tag")],
            ["assignee_id", l("مسئول", "Assignee")],
            ["section_id", l("بخش", "Section")],
            ["requirement_id", l("نیازمندی", "Requirement")],
          ].map(([value, label]) => ({ value, label }))}
          onChange={(field) =>
            change({
              field,
              operator: field === "title" ? "contains" : "eq",
              value: field === "assignee_id" ? null : "",
            })
          }
        />
        <Select
          value={node.operator}
          style={{ width: 150 }}
          options={(node.field === "title"
            ? ["eq", "contains"]
            : node.field === "section_id"
              ? ["eq", "descendants"]
              : ["priority", "status", "type"].includes(node.field || "")
                ? ["eq", "in"]
                : ["eq"]
          ).map((value) => ({
            value,
            label: (
              {
                eq: l("برابر", "Equals"),
                contains: l("شامل متن", "Contains"),
                descendants: l("همراه زیرشاخه‌ها", "With descendants"),
                in: l("یکی از", "One of"),
              } as Record<string, string>
            )[value],
          }))}
          onChange={(operator) => {
            if (operator !== node.operator)
              change({
                ...node,
                operator,
                value:
                  operator === "in"
                    ? []
                    : node.field === "assignee_id"
                      ? null
                      : "",
              });
          }}
        />
        {["priority", "status", "type"].includes(node.field || "") ? (
          <Select
            mode={node.operator === "in" ? "multiple" : undefined}
            style={{ minWidth: 180 }}
            value={node.value as string}
            options={(node.field === "priority"
              ? ["critical", "high", "medium", "low"]
              : node.field === "status"
                ? ["draft", "pending", "approved"]
                : [
                    "functional",
                    "regression",
                    "smoke",
                    "security",
                    "performance",
                  ]
            ).map((value) => ({ value, label: value }))}
            onChange={(value) => change({ ...node, value })}
          />
        ) : node.field === "assignee_id" ? (
          <MemberPicker
            project={project}
            value={node.value as number}
            onChange={(value) => change({ ...node, value: value ?? null })}
          />
        ) : node.field === "requirement_id" ? (
          <ReferencePicker
            project={project}
            kind="requirements"
            value={node.value as string}
            onChange={(value) => change({ ...node, value })}
          />
        ) : node.field === "section_id" ? (
          <Select
            showSearch
            optionFilterProp="label"
            style={{ minWidth: 200 }}
            value={node.value as string}
            options={sections.map((section) => ({
              value: section.id,
              label: section.name,
            }))}
            onChange={(value) => change({ ...node, value })}
          />
        ) : (
          <Input
            value={node.value as string}
            onChange={(e) => change({ ...node, value: e.target.value })}
          />
        )}
      </Space>
    );
  return render(value, (next) => onChange?.(next));
}
export function DefinitionFields({
  kind,
  project,
  language,
  permissions = {},
}: {
  permissions?: Record<string, boolean>;
  kind: string;
  project: string;
  language: Locale;
}) {
  const l = (fa: string, en: string) => label(language, fa, en);
  const description = (
    <Form.Item
      name={["content", "description"]}
      label={l("شرح", "Description")}
    >
      <Input.TextArea rows={3} />
    </Form.Item>
  );
  if (kind === "datasets")
    return (
      <Form.Item
        name="content"
        label={l("ستون‌ها و ردیف‌های داده", "Data columns and rows")}
        rules={[{ required: true }]}
      >
        <DatasetEditor language={language} />
      </Form.Item>
    );
  if (kind === "plans")
    return (
      <>
        {description}
        <Form.Item
          name={["content", "case_ids"]}
          label={l(
            "تست‌های صریح؛ یا نمای ذخیره‌شده انتخاب کنید",
            "Explicit cases; alternatively select a saved view",
          )}
        >
          <ReferencePicker project={project} kind="cases" multiple />
        </Form.Item>
        <Form.Item
          name={["content", "saved_view_id"]}
          label={l("نمای ذخیره‌شده پویا", "Dynamic saved view")}
        >
          <ReferencePicker project={project} kind="saved-views" />
        </Form.Item>
        <Form.Item
          name={["content", "configuration_profile_id"]}
          label={l("ماتریس پیکربندی", "Configuration profile")}
        >
          <ReferencePicker project={project} kind="configuration-profiles" />
        </Form.Item>
        <Form.Item
          name={["content", "release_id"]}
          label={l("انتشار", "Release")}
        >
          <ReferencePicker project={project} kind="releases" />
        </Form.Item>
      </>
    );
  if (kind === "configuration-profiles")
    return (
      <Form.List name={["content", "rows"]}>
        {(fields, { add, remove }) => (
          <>
            {fields.map(({ key, name }) => (
              <Card
                key={key}
                size="small"
                style={{ marginBottom: 16 }}
                title={l("پیکربندی", "Configuration") + " " + (name + 1)}
                extra={
                  <Button danger onClick={() => remove(name)}>
                    ×
                  </Button>
                }
              >
                <Form.Item name={[name, "id"]} hidden>
                  <Input />
                </Form.Item>
                <Form.Item
                  name={[name, "name"]}
                  label={l("نام", "Name")}
                  rules={[{ required: true }]}
                >
                  <Input />
                </Form.Item>
                <Form.Item
                  name={[name, "environment_id"]}
                  label={l("محیط", "Environment")}
                >
                  <ReferencePicker project={project} kind="environments" />
                </Form.Item>
                <Form.Item
                  name={[name, "build_id"]}
                  label={l(
                    "ساخت؛ باید متعلق به محیط باشد",
                    "Build; must belong to environment",
                  )}
                >
                  <ReferencePicker project={project} kind="builds" />
                </Form.Item>
                {(["browser", "os", "device"] as const).map((field) => (
                  <Form.Item
                    key={field}
                    name={[name, field]}
                    label={
                      {
                        browser: l("مرورگر", "Browser"),
                        os: l("سیستم عامل", "Operating system"),
                        device: l("دستگاه", "Device"),
                      }[field]
                    }
                  >
                    <Input maxLength={100} />
                  </Form.Item>
                ))}
                <Form.Item
                  name={[name, "dataset_id"]}
                  label={l("مجموعه داده", "Dataset")}
                >
                  <ReferencePicker project={project} kind="datasets" />
                </Form.Item>
                <Form.Item
                  name={[name, "required"]}
                  valuePropName="checked"
                  label={l("اجرای الزامی", "Required execution")}
                >
                  <Switch />
                </Form.Item>
              </Card>
            ))}
            <Button
              disabled={fields.length >= 20}
              onClick={() =>
                add({ id: crypto.randomUUID(), name: "", required: true })
              }
            >
              {l("افزودن پیکربندی", "Add configuration")}
            </Button>
          </>
        )}
      </Form.List>
    );
  if (kind === "saved-views")
    return (
      <>
        <Form.Item
          name={["content", "visibility"]}
          label={l("دسترسی نما", "View visibility")}
          rules={[{ required: true }]}
        >
          <Select
            options={[
              { value: "personal", label: l("شخصی", "Personal") },
              {
                value: "team",
                label: l("تیمی", "Team"),
                disabled: !permissions["views.manage"],
              },
            ]}
          />
        </Form.Item>
        <Form.Item
          name={["content", "conditions"]}
          label={l("شرط‌ها", "Conditions")}
        >
          <ConditionsEditor project={project} language={language} />
        </Form.Item>
        <Form.Item
          name={["content", "columns"]}
          label={l("ستون‌های نما", "View columns")}
        >
          <Select
            mode="multiple"
            options={[
              ["title", l("عنوان", "Title")],
              ["priority", l("اولویت", "Priority")],
              ["status", l("وضعیت", "State")],
              ["assignee_id", l("مسئول", "Assignee")],
              ["section_id", l("بخش", "Section")],
              ["version", l("نسخه", "Revision")],
              ["updated_at", l("آخرین تغییر", "Updated")],
            ].map(([value, label]) => ({ value, label }))}
          />
        </Form.Item>
        <Form.Item
          name={["content", "order"]}
          label={l("مرتب‌سازی", "Sort by")}
        >
          <Select
            options={["title", "priority", "updated_at"].map((value) => ({
              value,
              label: value,
            }))}
          />
        </Form.Item>
        <Form.Item
          name={["content", "direction"]}
          label={l("جهت مرتب‌سازی", "Sort direction")}
        >
          <Select
            options={[
              { value: "asc", label: l("صعودی", "Ascending") },
              { value: "desc", label: l("نزولی", "Descending") },
            ]}
          />
        </Form.Item>
      </>
    );
  if (kind === "templates" || kind === "shared-steps")
    return (
      <>
        <Form.Item
          name={["content", "preconditions"]}
          label={l("پیش‌شرط", "Preconditions")}
        >
          <Input.TextArea rows={3} />
        </Form.Item>
        <Form.Item
          name={["content", "expected"]}
          label={l("انتظار کلی", "Overall expectation")}
        >
          <Input.TextArea rows={2} />
        </Form.Item>
        <Form.List name={["content", "checklist"]}>
          {(fields, { add, remove }) => (
            <>
              {fields.map(({ key, name }) => (
                <Card key={key} size="small">
                  <Form.Item
                    name={[name, "text"]}
                    label={l("اقدام", "Action")}
                    rules={[{ required: true }]}
                  >
                    <Input.TextArea />
                  </Form.Item>
                  <Form.Item
                    name={[name, "expected"]}
                    label={l(
                      "نتیجه مورد انتظار همین مورد",
                      "Expected result for this step",
                    )}
                    rules={[{ required: true }]}
                  >
                    <Input.TextArea />
                  </Form.Item>
                  <Form.Item
                    name={[name, "required"]}
                    valuePropName="checked"
                    label={l("الزامی", "Required")}
                  >
                    <Switch />
                  </Form.Item>
                  <Button danger onClick={() => remove(name)}>
                    ×
                  </Button>
                </Card>
              ))}
              <Button
                onClick={() => add({ text: "", expected: "", required: true })}
              >
                {l("افزودن مورد", "Add step")}
              </Button>
            </>
          )}
        </Form.List>
      </>
    );
  if (kind === "builds")
    return (
      <>
        {description}
        <Form.Item
          name={["content", "label"]}
          label={l("شناسه ساخت", "Build label")}
          rules={[{ required: true }]}
        >
          <Input />
        </Form.Item>
        <Form.Item
          name={["content", "environment_id"]}
          label={l("محیط", "Environment")}
          rules={[{ required: true }]}
        >
          <ReferencePicker project={project} kind="environments" />
        </Form.Item>
        <Form.Item
          name={["content", "release_id"]}
          label={l("انتشار", "Release")}
        >
          <ReferencePicker project={project} kind="releases" />
        </Form.Item>
      </>
    );
  if (kind === "releases")
    return (
      <>
        {description}
        <Form.Item
          name={["content", "product_version"]}
          label={l("نسخه محصول", "Product version")}
          rules={[{ required: true }]}
        >
          <Input />
        </Form.Item>
        <Form.Item
          name={["content", "gates", "max_open_critical"]}
          label={l("حداکثر خطای بحرانی باز", "Maximum open critical defects")}
          rules={[{ required: true }]}
        >
          <InputNumber min={0} />
        </Form.Item>
        <Form.Item
          name={["content", "gates", "minimum_pass_rate"]}
          label={l(
            "حداقل درصد موفقیت اقلام الزامی",
            "Minimum required-item pass percentage",
          )}
          rules={[{ required: true }]}
        >
          <InputNumber min={0} max={100} />
        </Form.Item>
        <Form.Item
          name={["content", "gates", "allow_forced_critical"]}
          valuePropName="checked"
          label={l(
            "اجازه انتشار با خطای بحرانی بسته‌شده بدون تأیید رفع",
            "Allow release with force-closed unverified critical defects",
          )}
        >
          <Switch />
        </Form.Item>
        <Form.Item
          name={["content", "gates", "allow_blocked"]}
          valuePropName="checked"
          label={l("اجازه مورد مسدود", "Allow blocked items")}
        >
          <Switch />
        </Form.Item>
      </>
    );
  return (
    <>
      {description}
      <Form.Item
        name={["content", "url"]}
        label={l("نشانی محیط", "Environment URL")}
      >
        <Input dir="ltr" />
      </Form.Item>
    </>
  );
}
