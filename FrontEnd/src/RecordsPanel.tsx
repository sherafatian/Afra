import { useEffect, useRef, useState } from "react";
import {
  App,
  Button,
  Card,
  Descriptions,
  Drawer,
  Form,
  Input,
  List,
  Space,
  Table,
  Tag,
  Typography,
} from "antd";
import { api, ApiError, send } from "./api";
import { QualityReport } from "./QualityReport";
import { DefinitionFields } from "./DefinitionEditors";
type Row = {
  id: string;
  title: string;
  version: number;
  content?: any;
  visibility?: string;
  owner_id?: number;
  review_status?: string;
  approved_version?: number;
};
type Item = {
  key: string;
  case_id: string;
  required: boolean;
  configuration: { name: string };
  data: { row: number; values: Record<string, unknown> };
  snapshot: {
    title: string;
    version: number;
    content: {
      preconditions?: string;
      checklist: { text: string; expected: string; required: boolean }[];
    };
  };
};
type Preview = {
  preview_token: string;
  unique_cases: number;
  total_items: number;
  required_items: number;
  items: Item[];
};
const decode = (value: unknown): any =>
  typeof value === "string" ? JSON.parse(value) : value;
export const definitionKinds = [
  "shared-steps",
  "plans",
  "configuration-profiles",
  "datasets",
  "saved-views",
  "templates",
  "environments",
  "builds",
  "releases",
];
export function RecordsPanel({
  project,
  kind,
  language,
  permissions,
  onRun,
  userId,
}: {
  project: string;
  kind: string;
  language: "fa" | "en";
  permissions: Record<string, boolean>;
  onRun: (run: any) => void;
  userId: number;
}) {
  const { message } = App.useApp();
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  const base = `v1/projects/${project}`;
  const [viewResult, setViewResult] = useState<{
    row: Row;
    data: any[];
    page: number;
    total: number;
  } | null>(null);
  const [quality, setQuality] = useState<string | null>(null);
  const [rows, setRows] = useState<Row[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editor, setEditor] = useState(false);
  const [target, setTarget] = useState<Row | null>(null);
  const [history, setHistory] = useState<any>(null);
  const [consumers, setConsumers] = useState<any>(null);
  const [rejection, setRejection] = useState<Row | null>(null);
  const [reviewReason, setReviewReason] = useState("");
  const [preview, setPreview] = useState<Preview | null>(null);
  const [chosen, setChosen] = useState<string[]>([]);
  const [attempt, setAttempt] = useState<Item | null>(null);
  const [runTitle, setRunTitle] = useState("");
  const [archive, setArchive] = useState<Row | null>(null);
  const [reason, setReason] = useState("");
  const [form] = Form.useForm();
  const request = useRef(0);
  const detailRequest = useRef(0);
  const [mobile, setMobile] = useState(matchMedia("(max-width:600px)").matches);
  const error = (e: unknown) => {
    if (e instanceof ApiError)
      message.error([e.message, ...Object.values(e.fields).flat()].join(" · "));
    else if (e instanceof Error) message.error(e.message);
  };
  const prefix =
    kind === "shared-steps"
      ? "shared"
      : kind === "configuration-profiles"
        ? "profiles"
        : kind;
  const canCreate =
    kind === "saved-views" ||
    permissions[prefix + ".create"] ||
    permissions[prefix + ".manage"];
  const canEdit =
    kind === "saved-views" ||
    permissions[prefix + ".update"] ||
    permissions[prefix + ".manage"];
  const canEditRow = (row: Row) =>
    kind === "saved-views"
      ? (row.visibility || decode(row.content)?.visibility) === "personal"
        ? row.owner_id === userId
        : !!permissions["views.manage"]
      : !!canEdit;
  const canArchiveRow = (row: Row) =>
    kind === "saved-views"
      ? canEditRow(row)
      : !!permissions[
          (
            {
              "shared-steps": "shared.archive",
              plans: "plans.delete",
              templates: "templates.manage",
              datasets: "datasets.update",
              "configuration-profiles": "profiles.update",
              environments: "environments.manage",
              builds: "environments.manage",
              releases: "releases.manage",
            } as Record<string, string>
          )[kind]
        ];
  useEffect(() => {
    const media = matchMedia("(max-width:600px)");
    const update = () => setMobile(media.matches);
    media.addEventListener("change", update);
    return () => media.removeEventListener("change", update);
  }, []);
  const load = async () => {
    const generation = ++request.current;
    setLoading(true);
    try {
      const data = await api(
        `${base}/records/${kind}?summary=1&page=${page}&q=${encodeURIComponent(query)}`,
      );
      if (generation === request.current) {
        setRows(data.data);
        setTotal(data.total);
      }
    } catch (e) {
      if (generation === request.current) error(e);
    } finally {
      if (generation === request.current) setLoading(false);
    }
  };
  useEffect(() => {
    void load();
    return () => {
      request.current++;
      detailRequest.current++;
    };
  }, [project, kind, page, query]);
  const defaults = () =>
    kind === "plans"
      ? { case_ids: [] }
      : kind === "configuration-profiles"
        ? {
            rows: [
              {
                id: crypto.randomUUID(),
                name: l("پیکربندی اصلی", "Primary configuration"),
                required: true,
              },
            ],
          }
        : kind === "datasets"
          ? {
              columns: [{ key: "value", type: "string", required: true }],
              rows: [{}],
            }
          : kind === "saved-views"
            ? {
                visibility: "personal",
                columns: ["title", "priority", "status", "version"],
                conditions: {
                  mode: "and",
                  rules: [
                    { field: "status", operator: "eq", value: "approved" },
                  ],
                },
                order: "title",
                direction: "asc",
              }
            : kind === "templates" || kind === "shared-steps"
              ? { checklist: [{ text: "", expected: "", required: true }] }
              : kind === "releases"
                ? {
                    gates: {
                      max_open_critical: 0,
                      minimum_pass_rate: 100,
                      allow_blocked: false,
                    },
                  }
                : {};
  const open = async (row?: Row) => {
    const generation = ++detailRequest.current;
    try {
      const full = row
        ? await api<Row>(`${base}/records/${kind}/${row.id}`)
        : null;
      if (generation !== detailRequest.current) return;
      setTarget(full);
      form.resetFields();
      form.setFieldsValue({
        title: full?.title || "",
        content: { ...defaults(), ...(full ? decode(full.content) : {}) },
      });
      setEditor(true);
    } catch (e) {
      if (generation === detailRequest.current) error(e);
    }
  };
  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);
      await send(
        `${base}/records/${kind}` + (target ? "/" + target.id : ""),
        values,
        target ? "PUT" : "POST",
        target?.version,
      );
      setEditor(false);
      await load();
    } catch (e) {
      if (e instanceof ApiError) error(e);
    } finally {
      setSaving(false);
    }
  };
  const showHistory = async (row: Row, revisionPage = 1) => {
    const generation = ++detailRequest.current;
    try {
      const data = await api(
        `${base}/records/${kind}/${row.id}?revision_page=${revisionPage}`,
      );
      if (generation === detailRequest.current) setHistory(data);
    } catch (e) {
      if (generation === detailRequest.current) error(e);
    }
  };
  const evaluate = async (row: Row, page = 1) => {
    const generation = ++detailRequest.current;
    try {
      const full = row.content
        ? row
        : await api<Row>(`${base}/records/${kind}/${row.id}`);
      const result = await api(`${base}/views/${row.id}/cases?page=${page}`);
      if (generation === detailRequest.current)
        setViewResult({
          row: full,
          data: result.data,
          page,
          total: result.total,
        });
    } catch (e) {
      if (generation === detailRequest.current) error(e);
    }
  };
  const showPreview = async (row: Row) => {
    setSaving(true);
    try {
      const data = await send<Preview>(`${base}/runs/preview`, {
        plan_id: row.id,
      });
      setPreview(data);
      setChosen(data.items.map((item) => item.key));
      setRunTitle(row.title);
    } catch (e) {
      error(e);
    } finally {
      setSaving(false);
    }
  };
  const create = async () => {
    if (!preview) return;
    setSaving(true);
    try {
      const record = await send<any>(`${base}/planned-runs`, {
        preview_token: preview.preview_token,
        title: runTitle,
        selected: chosen,
      });
      const run = await api(`${base}/runs/${record.id}`);
      setPreview(null);
      onRun(run);
    } catch (e) {
      error(e);
    } finally {
      setSaving(false);
    }
  };
  const remove = async () => {
    if (!archive) return;
    setSaving(true);
    try {
      await send(
        `${base}/records/${kind}/${archive.id}/archive`,
        { reason },
        "POST",
        archive.version,
      );
      setArchive(null);
      await load();
    } catch (e) {
      error(e);
    } finally {
      setSaving(false);
    }
  };
  const reviewShared = async (row: Row, decision: string, reason?: string) => {
    setSaving(true);
    try {
      await send(`${base}/shared-steps/${row.id}/review`, {
        version: row.version,
        decision,
        reason,
      });
      await load();
      return true;
    } catch (e) {
      error(e);
      return false;
    } finally {
      setSaving(false);
    }
  };
  const showConsumers = async (row: Row, page = 1) => {
    const generation = ++detailRequest.current;
    try {
      const data = await api<any>(
        `${base}/shared-steps/${row.id}/consumers?page=${page}`,
      );
      if (generation === detailRequest.current) setConsumers({ row, ...data });
    } catch (e) {
      if (generation === detailRequest.current) error(e);
    }
  };
  const placement = mobile ? "bottom" : language === "fa" ? "left" : "right";
  return (
    <Card>
      <Drawer
        open={!!consumers}
        onClose={() => {
          detailRequest.current++;
          setConsumers(null);
        }}
        placement={placement}
        width={800}
        height={mobile ? "90dvh" : undefined}
        title={l("تست‌های متصل به مراحل مشترک", "Shared step consumers")}
      >
        <Table
          rowKey="id"
          dataSource={consumers?.data || []}
          pagination={{
            current: consumers?.current_page || 1,
            total: consumers?.total || 0,
            pageSize: 25,
            showSizeChanger: false,
            onChange: (page) => showConsumers(consumers.row, page),
          }}
          columns={[
            { title: l("عنوان", "Title"), dataIndex: "title" },
            { title: l("نسخه تست", "Case revision"), dataIndex: "version" },
            {
              title: l("نسخه مراحل مشترک", "Pinned shared revision"),
              render: (_, row: any) =>
                decode(row.shared_blocks).find(
                  (binding: any) => binding.id === consumers.row.id,
                )?.version,
            },
          ]}
        />
      </Drawer>
      <Drawer
        open={!!rejection}
        onClose={() => {
          if (!saving) setRejection(null);
        }}
        placement={placement}
        width={500}
        height={mobile ? "70dvh" : undefined}
        title={l("رد بازبینی مراحل مشترک", "Reject shared step review")}
        footer={
          <Button
            danger
            loading={saving}
            disabled={reviewReason.trim().length < 10}
            onClick={async () => {
              if (rejection) {
                if (await reviewShared(rejection, "reject", reviewReason))
                  setRejection(null);
              }
            }}
          >
            {l("ثبت نظر بازبینی", "Submit review")}
          </Button>
        }
      >
        <Input.TextArea
          rows={5}
          maxLength={2000}
          value={reviewReason}
          onChange={(event) => setReviewReason(event.target.value)}
          placeholder={l(
            "علت رد و اصلاحات لازم را بنویسید.",
            "Describe the reason and required corrections.",
          )}
        />
      </Drawer>
      {quality && (
        <QualityReport
          project={project}
          kind="releases"
          id={quality}
          language={language}
          canExport={!!permissions["reports.export"]}
          onClose={() => setQuality(null)}
        />
      )}
      <Space wrap style={{ marginBottom: 20 }}>
        <Input.Search
          allowClear
          placeholder={l("جست‌وجوی عنوان", "Search titles")}
          onSearch={(value) => {
            setQuery(value);
            setPage(1);
          }}
        />
        <Button type="primary" disabled={!canCreate} onClick={() => open()}>
          {l("افزودن", "Add")}
        </Button>
      </Space>
      <Table<Row>
        rowKey="id"
        loading={loading}
        dataSource={rows}
        scroll={{ x: 650 }}
        pagination={{
          current: page,
          pageSize: 25,
          total,
          onChange: setPage,
          showSizeChanger: false,
        }}
        columns={[
          { title: l("عنوان", "Title"), dataIndex: "title" },
          { title: l("نسخه", "Revision"), dataIndex: "version" },
          {
            title: l("عملیات", "Actions"),
            render: (_, row) => (
              <Space wrap>
                <Button disabled={!canEditRow(row)} onClick={() => open(row)}>
                  {l("ویرایش", "Edit")}
                </Button>
                <Button onClick={() => showHistory(row)}>
                  {l("تاریخچه نسخه‌ها", "Revision history")}
                </Button>
                {kind === "saved-views" && (
                  <Button onClick={() => evaluate(row)}>
                    {l("نمایش تست‌ها", "View cases")}
                  </Button>
                )}
                {kind === "shared-steps" && (
                  <>
                    <Button onClick={() => showConsumers(row)}>
                      {l("تست‌های متصل", "Consumers")}
                    </Button>
                    <Tag>
                      {
                        (
                          {
                            draft: l("پیش‌نویس", "Draft"),
                            pending: l("در انتظار بازبینی", "Pending review"),
                            approved: l("تأییدشده", "Approved"),
                          } as Record<string, string>
                        )[row.review_status || "draft"]
                      }
                    </Tag>
                    {row.review_status === "draft" && (
                      <Button
                        disabled={
                          saving || !permissions["shared.request_review"]
                        }
                        onClick={() => reviewShared(row, "submit")}
                      >
                        {l("درخواست بازبینی", "Request review")}
                      </Button>
                    )}
                    {row.review_status === "pending" && (
                      <>
                        <Button
                          disabled={saving || !permissions["shared.approve"]}
                          onClick={() => reviewShared(row, "approve")}
                        >
                          {l("تأیید مستقل", "Independent approval")}
                        </Button>
                        <Button
                          disabled={saving || !permissions["shared.approve"]}
                          onClick={() => {
                            setReviewReason("");
                            setRejection(row);
                          }}
                        >
                          {l("رد", "Reject")}
                        </Button>
                      </>
                    )}
                  </>
                )}
                {kind === "releases" && (
                  <Button
                    disabled={!permissions["reports.read"]}
                    onClick={() => setQuality(row.id)}
                  >
                    {l("شرط‌های انتشار و گزارش", "Release gates & report")}
                  </Button>
                )}
                {kind === "plans" && (
                  <Button
                    type="primary"
                    loading={saving}
                    disabled={!permissions["runs.create"]}
                    onClick={() => showPreview(row)}
                  >
                    {l("پیش‌نمایش اجرا", "Preview execution")}
                  </Button>
                )}
                <Button
                  danger
                  disabled={!canArchiveRow(row)}
                  onClick={() => {
                    setReason("");
                    setArchive(row);
                  }}
                >
                  {l("بایگانی", "Archive")}
                </Button>
              </Space>
            ),
          },
        ]}
      />
      <Drawer
        open={!!viewResult}
        onClose={() => {
          detailRequest.current++;
          setViewResult(null);
        }}
        placement={placement}
        width={1000}
        height={mobile ? "94dvh" : undefined}
        rootClassName="editor-sheet"
        title={viewResult?.row.title}
      >
        {viewResult && (
          <Table
            rowKey="id"
            dataSource={viewResult.data}
            scroll={{ x: 700 }}
            pagination={{
              current: viewResult.page,
              total: viewResult.total,
              pageSize: 25,
              showSizeChanger: false,
              onChange: (page) => evaluate(viewResult.row, page),
            }}
            columns={(
              decode(viewResult.row.content).columns || [
                "title",
                "priority",
                "status",
                "version",
              ]
            ).map((key: string) => ({
              title: (
                {
                  title: l("عنوان", "Title"),
                  priority: l("اولویت", "Priority"),
                  status: l("وضعیت", "State"),
                  assignee_id: l("مسئول", "Assignee"),
                  section_id: l("بخش", "Section"),
                  version: l("نسخه", "Revision"),
                  updated_at: l("آخرین تغییر", "Updated"),
                } as Record<string, string>
              )[key],
              dataIndex: key,
              render: (value: unknown, row: any) =>
                key === "section_id"
                  ? row.section_name || "—"
                  : key === "assignee_id"
                    ? row.assignee_name || "—"
                    : key === "updated_at" && value
                      ? new Intl.DateTimeFormat(
                          language === "fa"
                            ? "fa-IR-u-ca-persian"
                            : "en-US-u-ca-persian",
                          { timeZone: "Asia/Tehran", dateStyle: "medium" },
                        ).format(new Date(String(value)))
                      : String(value ?? "—"),
            }))}
          />
        )}
      </Drawer>
      <Drawer
        key={editor ? "editing" : "closed"}
        open={editor}
        onClose={() => {
          if (!saving) setEditor(false);
        }}
        placement={placement}
        width={900}
        height={mobile ? "94dvh" : undefined}
        rootClassName="editor-sheet"
        title={
          target
            ? l("ویرایش تعریف", "Edit definition")
            : l("تعریف جدید", "New definition")
        }
        footer={
          <Space>
            <Button type="primary" loading={saving} onClick={save}>
              {l("ذخیره", "Save")}
            </Button>
            <Button disabled={saving} onClick={() => setEditor(false)}>
              {l("انصراف", "Cancel")}
            </Button>
          </Space>
        }
      >
        <Form name="afra-definition" form={form} layout="vertical">
          <Form.Item
            name="title"
            label={l("عنوان", "Title")}
            rules={[{ required: true, max: 250 }]}
          >
            <Input />
          </Form.Item>
          <DefinitionFields
            kind={kind}
            project={project}
            language={language}
            permissions={permissions}
          />
        </Form>
      </Drawer>
      <Drawer
        open={!!preview}
        onClose={() => {
          if (!saving) setPreview(null);
        }}
        placement={placement}
        width={1100}
        height={mobile ? "94dvh" : undefined}
        rootClassName="editor-sheet"
        title={l(
          "پیش‌نمایش دامنه اجرای ثابت",
          "Frozen execution scope preview",
        )}
        footer={
          <Space>
            <Button
              type="primary"
              disabled={!chosen.length || !runTitle.trim()}
              loading={saving}
              onClick={create}
            >
              {l("تأیید و ساخت اجرا", "Confirm and create run")}
            </Button>
            <Button disabled={saving} onClick={() => setPreview(null)}>
              {l("انصراف", "Cancel")}
            </Button>
          </Space>
        }
      >
        {preview && (
          <>
            <Space wrap>
              <Tag>
                {l("تست یکتا", "Unique cases")}: {preview.unique_cases}
              </Tag>
              <Tag>
                {l("کل اقلام", "Total items")}: {preview.total_items}
              </Tag>
              <Tag color="orange">
                {l("اقلام الزامی", "Required items")}: {preview.required_items}
              </Tag>
              <Tag>
                {l("انتخاب‌شده", "Selected")}: {chosen.length}
              </Tag>
            </Space>
            <Typography.Paragraph>
              {l(
                "پیش‌نمایش پنج دقیقه معتبر است. تغییر منبع یا دسترسی به پیش‌نمایش تازه نیاز دارد. اقلام الزامی قابل حذف نیستند.",
                "Preview is valid for five minutes. Source/access changes require a new preview. Required items cannot be deselected.",
              )}
            </Typography.Paragraph>
            <Input
              aria-label={l("عنوان اجرا", "Run title")}
              value={runTitle}
              onChange={(e) => setRunTitle(e.target.value)}
              maxLength={250}
            />
            <Table<Item>
              rowKey="key"
              dataSource={preview.items}
              scroll={{ x: 750 }}
              rowSelection={{
                selectedRowKeys: chosen,
                onChange: (keys) => setChosen(keys as string[]),
                getCheckboxProps: (item) => ({ disabled: item.required }),
              }}
              pagination={{ pageSize: 10, showSizeChanger: false }}
              columns={[
                {
                  title: l("تست", "Case"),
                  render: (_, item) => item.snapshot.title,
                },
                {
                  title: l("نسخه", "Revision"),
                  render: (_, item) => item.snapshot.version,
                },
                {
                  title: l("پیکربندی", "Configuration"),
                  render: (_, item) => item.configuration.name,
                },
                {
                  title: l("ردیف داده", "Data row"),
                  render: (_, item) => item.data.row + 1,
                },
                {
                  title: l("الزام", "Requirement"),
                  render: (_, item) => (
                    <Tag color={item.required ? "orange" : "default"}>
                      {item.required
                        ? l("الزامی", "Required")
                        : l("اختیاری", "Optional")}
                    </Tag>
                  ),
                },
                {
                  title: l("جزئیات", "Details"),
                  render: (_, item) => (
                    <Button onClick={() => setAttempt(item)}>
                      {l("مشاهده مورد", "Inspect item")}
                    </Button>
                  ),
                },
              ]}
            />
          </>
        )}
      </Drawer>
      <Drawer
        open={!!attempt}
        onClose={() => setAttempt(null)}
        placement={placement}
        width={720}
        height={mobile ? "90dvh" : undefined}
        rootClassName="editor-sheet"
        title={attempt?.snapshot.title}
      >
        {attempt && (
          <>
            <Typography.Paragraph>
              {attempt.snapshot.content.preconditions}
            </Typography.Paragraph>
            <Descriptions
              column={1}
              items={Object.entries(attempt.data.values).map(
                ([key, value]) => ({
                  key,
                  label: key,
                  children: String(value),
                }),
              )}
            />
            <List
              dataSource={attempt.snapshot.content.checklist}
              renderItem={(step, index) => (
                <List.Item>
                  <div>
                    <strong>
                      {index + 1}. {step.text}
                    </strong>
                    <p>{step.expected}</p>
                    <Tag>
                      {step.required
                        ? l("الزامی", "Required")
                        : l("اختیاری", "Optional")}
                    </Tag>
                  </div>
                </List.Item>
              )}
            />
          </>
        )}
      </Drawer>
      <Drawer
        open={!!history}
        onClose={() => {
          detailRequest.current++;
          setHistory(null);
        }}
        placement={placement}
        width={800}
        height={mobile ? "92dvh" : undefined}
        rootClassName="editor-sheet"
        title={l("تاریخچه تغییرناپذیر نسخه‌ها", "Immutable revision history")}
      >
        <List
          dataSource={history?.revisions || []}
          pagination={{
            current: history?.revision_page || 1,
            pageSize: 25,
            total: history?.revision_total || 0,
            showSizeChanger: false,
            onChange: (page) => showHistory(history, page),
          }}
          renderItem={(revision: any) => {
            const snapshot = decode(revision.snapshot);
            return (
              <List.Item>
                <div>
                  <strong>
                    {l("نسخه", "Revision")} {revision.version} —{" "}
                    {snapshot.title}
                  </strong>
                  <p>
                    {snapshot.content.description ||
                      snapshot.content.preconditions}
                  </p>
                  {snapshot.content.checklist?.map(
                    (step: any, index: number) => (
                      <p key={index}>
                        {step.text} → {step.expected}
                      </p>
                    ),
                  )}
                  {snapshot.content.rows && (
                    <Tag>
                      {l("ردیف‌ها", "Rows")}: {snapshot.content.rows.length}
                    </Tag>
                  )}
                  {snapshot.content.case_ids && (
                    <Tag>
                      {l("تست‌ها", "Cases")}: {snapshot.content.case_ids.length}
                    </Tag>
                  )}
                </div>
              </List.Item>
            );
          }}
        />
      </Drawer>
      <Drawer
        open={!!archive}
        onClose={() => setArchive(null)}
        placement={placement}
        width={500}
        height={mobile ? "70dvh" : undefined}
        rootClassName="editor-sheet"
        title={l("بایگانی با حفظ تاریخچه", "Archive and preserve history")}
        footer={
          <Button
            danger
            type="primary"
            disabled={reason.trim().length < 10}
            loading={saving}
            onClick={remove}
          >
            {l("بایگانی", "Archive")}
          </Button>
        }
      >
        <Input.TextArea
          aria-label={l("دلیل بایگانی", "Archive reason")}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={4}
          maxLength={2000}
        />
      </Drawer>
    </Card>
  );
}
