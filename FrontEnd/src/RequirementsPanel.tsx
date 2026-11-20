import { useEffect, useRef, useState } from "react";
import {
  App,
  Button,
  Card,
  Drawer,
  Form,
  Input,
  List,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Tabs,
  Typography,
} from "antd";
import { api, ApiError, send } from "./api";
type Props = {
  project: string;
  language: "fa" | "en";
  permissions: Record<string, boolean>;
  userId: number;
};
type CoverageCase = {
  id: string;
  title: string;
  version: number;
  author_id: number;
  compatible: boolean;
  coverage: string;
  linked_version: number;
};
type Requirement = {
  id: string;
  title: string;
  version: number;
  archived: boolean;
  content: any;
  revisions?: any[];
  cases?: any;
  compatibilities?: any[];
};
const content = (record: Requirement) =>
  typeof record.content === "string"
    ? JSON.parse(record.content)
    : record.content;
export function RequirementsPanel({
  project,
  language,
  permissions,
  userId,
}: Props) {
  const { message } = App.useApp();
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  const base = `v1/projects/${project}/requirements`;
  const [rows, setRows] = useState<Requirement[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState("");
  const [archived, setArchived] = useState(false);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editor, setEditor] = useState<"create" | "edit" | "archive" | null>(
    null,
  );
  const [target, setTarget] = useState<Requirement | null>(null);
  const [detail, setDetail] = useState<Requirement | null>(null);
  const [casePage, setCasePage] = useState(1);
  const [confirmCase, setConfirmCase] = useState<any>(null);
  const [form] = Form.useForm();
  const [confirmForm] = Form.useForm();
  const generation = useRef(0);
  const detailGeneration = useRef(0);
  const [mobile, setMobile] = useState(matchMedia("(max-width:600px)").matches);
  const error = (e: unknown) => {
    if (e instanceof ApiError)
      message.error([e.message, ...Object.values(e.fields).flat()].join(" · "));
    else if (e instanceof Error) message.error(e.message);
  };
  useEffect(() => {
    const media = matchMedia("(max-width:600px)");
    const update = () => setMobile(media.matches);
    media.addEventListener("change", update);
    return () => media.removeEventListener("change", update);
  }, []);
  const load = async () => {
    const request = ++generation.current;
    setLoading(true);
    try {
      const result = await api(
        `${base}?page=${page}&q=${encodeURIComponent(query)}&archived=${archived ? 1 : 0}`,
      );
      if (request === generation.current) {
        setRows(result.data);
        setTotal(result.total);
      }
    } catch (e) {
      if (request === generation.current) error(e);
    } finally {
      if (request === generation.current) setLoading(false);
    }
  };
  useEffect(() => {
    void load();
    return () => {
      generation.current++;
      detailGeneration.current++;
    };
  }, [project, page, query, archived]);
  const show = async (row: Requirement, selectedPage = 1) => {
    const request = ++detailGeneration.current;
    try {
      const result = await api(`${base}/${row.id}?case_page=${selectedPage}`);
      if (request === detailGeneration.current) {
        setDetail(result);
        setCasePage(selectedPage);
        setConfirmCase(null);
      }
    } catch (e) {
      if (request === detailGeneration.current) error(e);
    }
  };
  const edit = (next: "create" | "edit" | "archive", row?: Requirement) => {
    setTarget(row || null);
    setEditor(next);
    form.resetFields();
    form.setFieldsValue(
      row
        ? { title: row.title, content: content(row), reason: "" }
        : { content: { priority: "medium" } },
    );
  };
  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);
      await send(
        base +
          (target ? "/" + target.id : "") +
          (editor === "archive" ? "/archive" : ""),
        values,
        editor === "edit" ? "PUT" : "POST",
        target?.version,
      );
      setEditor(null);
      await load();
      message.success(l("نیازمندی ثبت شد.", "Requirement saved."));
    } catch (e) {
      if (e instanceof ApiError) error(e);
    } finally {
      setSaving(false);
    }
  };
  const confirm = async () => {
    if (!detail || !confirmCase) return;
    try {
      const values = await confirmForm.validateFields();
      setSaving(true);
      await send(`${base}/${detail.id}/compatibility`, {
        ...values,
        case_id: confirmCase.id,
        case_version: confirmCase.version,
        requirement_version: detail.version,
      });
      await show(detail, casePage);
      message.success(
        l(
          "سازگاری تأیید شد؛ تأیید نتیجه آزمون به اجرای تازه نیاز دارد.",
          "Compatibility confirmed. Verification still requires a fresh execution.",
        ),
      );
    } catch (e) {
      if (e instanceof ApiError) error(e);
    } finally {
      setSaving(false);
    }
  };
  const state = (value: string) => (
    <Tag
      color={
        value === "pass"
          ? "green"
          : value === "fail" || value === "stale"
            ? "red"
            : "orange"
      }
    >
      {(
        {
          pass: l("موفق", "Passed"),
          fail: l("ناموفق", "Failed"),
          blocked: l("مسدود", "Blocked"),
          skipped: l("ردشده از اجرا", "Skipped"),
          stale: l("نیازمند بررسی تغییر", "Stale coverage"),
          not_run: l("اجرا نشده", "Not run"),
          not_planned: l("برنامه‌ریزی نشده", "Not planned"),
        } as Record<string, string>
      )[value] || value}
    </Tag>
  );
  return (
    <Card>
      <Space wrap style={{ marginBottom: 20 }}>
        <Input.Search
          allowClear
          placeholder={l("جست‌وجوی نیازمندی", "Search requirements")}
          onSearch={(value) => {
            setQuery(value);
            setPage(1);
          }}
        />
        <Space>
          <Switch
            checked={archived}
            onChange={(value) => {
              setArchived(value);
              setPage(1);
            }}
          />
          {l("بایگانی‌شده", "Archived")}
        </Space>
        <Button
          type="primary"
          disabled={!permissions["requirements.create"]}
          onClick={() => edit("create")}
        >
          {l("نیازمندی جدید", "New requirement")}
        </Button>
      </Space>
      <Table<Requirement>
        rowKey="id"
        loading={loading}
        dataSource={rows}
        scroll={{ x: 650 }}
        pagination={{
          current: page,
          pageSize: 25,
          total,
          showSizeChanger: false,
          onChange: setPage,
        }}
        columns={[
          { title: l("عنوان", "Title"), dataIndex: "title" },
          { title: l("نسخه محتوا", "Content revision"), dataIndex: "version" },
          {
            title: l("نسخه محصول", "Product version"),
            render: (_, row) => content(row).product_version || "—",
          },
          {
            title: l("عملیات", "Actions"),
            render: (_, row) => (
              <Space wrap>
                <Button onClick={() => show(row)}>
                  {l("جزئیات و پوشش", "Details & coverage")}
                </Button>
                {!row.archived && (
                  <>
                    <Button
                      disabled={!permissions["requirements.update"]}
                      onClick={() => edit("edit", row)}
                    >
                      {l("ویرایش", "Edit")}
                    </Button>
                    <Button
                      danger
                      disabled={!permissions["requirements.delete"]}
                      onClick={() => edit("archive", row)}
                    >
                      {l("بایگانی", "Archive")}
                    </Button>
                  </>
                )}
              </Space>
            ),
          },
        ]}
      />
      <Drawer
        key={editor || "closed"}
        open={!!editor}
        onClose={() => {
          if (!saving) setEditor(null);
        }}
        placement={mobile ? "bottom" : language === "fa" ? "left" : "right"}
        height={mobile ? "92dvh" : undefined}
        width={720}
        rootClassName="editor-sheet"
        title={
          editor === "archive"
            ? l("بایگانی نیازمندی", "Archive requirement")
            : l("نسخه نیازمندی", "Requirement revision")
        }
        footer={
          <Space>
            <Button type="primary" loading={saving} onClick={save}>
              {l("ذخیره", "Save")}
            </Button>
            <Button disabled={saving} onClick={() => setEditor(null)}>
              {l("انصراف", "Cancel")}
            </Button>
          </Space>
        }
      >
        <Form name="afra-requirement" form={form} layout="vertical">
          {editor !== "archive" && (
            <>
              <Form.Item
                name="title"
                label={l("عنوان", "Title")}
                rules={[{ required: true, max: 250 }]}
              >
                <Input />
              </Form.Item>
              <Form.Item
                name={["content", "description"]}
                label={l("شرح نیازمندی", "Description")}
                rules={[{ required: true, max: 20000 }]}
              >
                <Input.TextArea rows={4} />
              </Form.Item>
              <Form.Item
                name={["content", "acceptance_criteria"]}
                label={l(
                  "معیارهای پذیرش قابل مشاهده",
                  "Observable acceptance criteria",
                )}
                rules={[{ required: true, max: 20000 }]}
              >
                <Input.TextArea rows={4} />
              </Form.Item>
              <Form.Item
                name={["content", "product_version"]}
                label={l(
                  "نسخه هدف محصول؛ مستقل از نسخه محتوا",
                  "Target product version; independent of content revision",
                )}
              >
                <Input maxLength={100} />
              </Form.Item>
              <Form.Item
                name={["content", "priority"]}
                label={l("اولویت", "Priority")}
                rules={[{ required: true }]}
              >
                <Select
                  options={["critical", "high", "medium", "low"].map(
                    (value) => ({ value, label: value }),
                  )}
                />
              </Form.Item>
            </>
          )}
          <Form.Item
            name="reason"
            label={l("دلیل تغییر", "Change reason")}
            rules={[{ required: true, min: 10, max: 2000 }]}
          >
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Drawer>
      <Drawer
        open={!!detail}
        onClose={() => {
          detailGeneration.current++;
          setDetail(null);
          setConfirmCase(null);
        }}
        placement={mobile ? "bottom" : language === "fa" ? "left" : "right"}
        height={mobile ? "94dvh" : undefined}
        width={1000}
        rootClassName="editor-sheet"
        title={detail?.title}
      >
        {detail && (
          <>
            <Tag>
              {l("نسخه محتوا", "Content revision")} {detail.version}
            </Tag>
            <Typography.Paragraph>
              {content(detail).description}
            </Typography.Paragraph>
            <Typography.Title level={5}>
              {l("معیار پذیرش", "Acceptance criteria")}
            </Typography.Title>
            <Typography.Paragraph style={{ whiteSpace: "pre-wrap" }}>
              {content(detail).acceptance_criteria}
            </Typography.Paragraph>
            <Tabs
              items={[
                {
                  key: "coverage",
                  label: l("پوشش و اثر تغییر", "Coverage & change impact"),
                  children: (
                    <>
                      <Typography.Paragraph>
                        {l(
                          "تأیید سازگاری فقط ارتباط تست با نسخه نیازمندی را تأیید می‌کند. Pass نسخه قبلی، تأیید نسخه جدید نیست.",
                          "Compatibility confirms the relationship, not the product result. An older Pass never verifies a newer requirement.",
                        )}
                      </Typography.Paragraph>
                      <Table<CoverageCase>
                        rowKey="id"
                        scroll={{ x: 700 }}
                        dataSource={detail.cases?.data || []}
                        pagination={{
                          current: casePage,
                          total: detail.cases?.total,
                          pageSize: 25,
                          showSizeChanger: false,
                          onChange: (value) => show(detail, value),
                        }}
                        columns={[
                          { title: l("تست", "Case"), dataIndex: "title" },
                          {
                            title: l("نسخه تست", "Case revision"),
                            dataIndex: "version",
                          },
                          {
                            title: l(
                              "نسخه پیوند اولیه",
                              "Originally linked revision",
                            ),
                            dataIndex: "linked_version",
                          },
                          {
                            title: l("پوشش", "Coverage"),
                            dataIndex: "coverage",
                            render: state,
                          },
                          {
                            title: l("عملیات", "Actions"),
                            render: (_, row) => (
                              <Button
                                disabled={
                                  detail.archived ||
                                  row.compatible ||
                                  row.author_id === userId ||
                                  !permissions[
                                    "requirements.confirm_compatibility"
                                  ]
                                }
                                onClick={() => {
                                  confirmForm.resetFields();
                                  setConfirmCase(row);
                                }}
                              >
                                {l("تأیید سازگاری", "Confirm compatibility")}
                              </Button>
                            ),
                          },
                        ]}
                      />
                      {confirmCase && (
                        <Card title={confirmCase.title}>
                          <Form
                            form={confirmForm}
                            name="afra-requirement-compatibility"
                            layout="vertical"
                          >
                            <Form.Item
                              name="reason"
                              label={l(
                                "دلیل سازگاری همین نسخه تست با همین نسخه نیازمندی",
                                "Reason this case revision covers this requirement revision",
                              )}
                              rules={[{ required: true, min: 10, max: 2000 }]}
                            >
                              <Input.TextArea rows={3} />
                            </Form.Item>
                            <Button
                              type="primary"
                              loading={saving}
                              onClick={confirm}
                            >
                              {l(
                                "تأیید سازگاری؛ بدون ثبت Pass",
                                "Confirm compatibility; does not record Pass",
                              )}
                            </Button>
                          </Form>
                        </Card>
                      )}
                    </>
                  ),
                },
                {
                  key: "history",
                  label: l("تاریخچه نسخه‌ها", "Revision history"),
                  children: (
                    <List
                      dataSource={detail.revisions || []}
                      renderItem={(revision: any) => (
                        <List.Item>
                          <div>
                            <Typography.Text strong>
                              {l("نسخه", "Revision")} {revision.version} —{" "}
                              {revision.title}
                            </Typography.Text>
                            <p>{revision.reason}</p>
                            <p style={{ whiteSpace: "pre-wrap" }}>
                              {
                                (typeof revision.content === "string"
                                  ? JSON.parse(revision.content)
                                  : revision.content
                                ).acceptance_criteria
                              }
                            </p>
                          </div>
                        </List.Item>
                      )}
                    />
                  ),
                },
              ]}
            />
          </>
        )}
      </Drawer>
    </Card>
  );
}
