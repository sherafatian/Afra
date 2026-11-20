import { useEffect, useRef, useState } from "react";
import {
  App,
  Button,
  Drawer,
  Form,
  Input,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Typography,
} from "antd";
import { api, ApiError, send } from "./api";
import { civilToSolar, solarToCivil } from "./solarDate";

type Account = {
  id: number;
  name: string;
  email: string;
  active: boolean;
  is_system_admin: boolean;
  must_change_password: boolean;
  expires_on: string | null;
  account_version: number;
};
type Props = {
  open: boolean;
  onClose: () => void;
  language: "fa" | "en";
  currentUser: number;
};
export function SystemUsers({ open, onClose, language, currentUser }: Props) {
  const { message } = App.useApp();
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  const [rows, setRows] = useState<Account[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [query, setQuery] = useState("");
  const [active, setActive] = useState<string | undefined>();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [mode, setMode] = useState<"create" | "edit" | "reset" | null>(null);
  const [selected, setSelected] = useState<Account | null>(null);
  const [form] = Form.useForm();
  const generation = useRef(0);
  const [mobile, setMobile] = useState(matchMedia("(max-width:600px)").matches);
  const error = (e: unknown) => {
    if (e instanceof ApiError) {
      message.error([e.message, ...Object.values(e.fields).flat()].join(" · "));
    } else if (e instanceof Error) message.error(e.message);
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
        `v1/system/users?page=${page}&q=${encodeURIComponent(query)}${active !== undefined ? "&active=" + active : ""}`,
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
    if (open) void load();
    else {
      setRows([]);
      setMode(null);
    }
    return () => {
      generation.current++;
    };
  }, [open, page, query, active]);
  const edit = (next: "create" | "edit" | "reset", row?: Account) => {
    setSelected(row || null);
    setMode(next);
    form.resetFields();
    form.setFieldsValue(
      row
        ? { ...row, expires_on: civilToSolar(row.expires_on), reason: "" }
        : { active: true, is_system_admin: false, must_change_password: true },
    );
  };
  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);
      if (mode !== "reset") {
        values.expires_on = values.expires_on
          ? solarToCivil(values.expires_on)
          : null;
        await send(
          "v1/system/users" + (selected ? "/" + selected.id : ""),
          values,
          selected ? "PATCH" : "POST",
          selected?.account_version,
        );
      } else {
        await send(
          `v1/system/users/${selected!.id}/reset-password`,
          values,
          "POST",
          selected!.account_version,
        );
      }
      setMode(null);
      form.resetFields();
      message.success(
        l(
          "حساب ثبت شد؛ تغییرات امنیتی نشست‌های قبلی را می‌بندند.",
          "Account saved. Security changes revoke previous sessions.",
        ),
      );
      await load();
    } catch (e) {
      if (e instanceof ApiError) error(e);
    } finally {
      setSaving(false);
    }
  };
  return (
    <>
      <Drawer
        open={open}
        onClose={onClose}
        width={1100}
        placement={mobile ? "bottom" : language === "fa" ? "left" : "right"}
        height={mobile ? "94dvh" : undefined}
        rootClassName="editor-sheet"
        title={l("حساب‌های سازمانی", "Organization accounts")}
      >
        <Typography.Paragraph>
          {l(
            "ایجاد حساب، دسترسی پروژه نمی‌دهد. پس از ایجاد، کاربر را از بخش اعضای پروژه اضافه کنید.",
            "Creating an account does not grant project access. Add it through Project members afterwards.",
          )}
        </Typography.Paragraph>
        <Space wrap style={{ marginBottom: 20 }}>
          <Input.Search
            allowClear
            placeholder={l("جست‌وجوی نام یا ایمیل", "Search name or email")}
            onSearch={(value) => {
              setPage(1);
              setQuery(value);
            }}
          />
          <Select
            allowClear
            placeholder={l("وضعیت حساب", "Account status")}
            style={{ minWidth: 150 }}
            value={active}
            options={[
              { value: "1", label: l("فعال", "Active") },
              { value: "0", label: l("غیرفعال", "Inactive") },
            ]}
            onChange={(value) => {
              setPage(1);
              setActive(value);
            }}
          />
          <Button type="primary" onClick={() => edit("create")}>
            {l("کاربر جدید", "New account")}
          </Button>
        </Space>
        <Table<Account>
          rowKey="id"
          loading={loading}
          dataSource={rows}
          scroll={{ x: 850 }}
          pagination={{
            current: page,
            total,
            pageSize: 25,
            showSizeChanger: false,
            onChange: setPage,
          }}
          columns={[
            { title: l("نام", "Name"), dataIndex: "name" },
            { title: l("ایمیل", "Email"), dataIndex: "email" },
            {
              title: l("وضعیت", "Status"),
              render: (_, row) => (
                <Space wrap>
                  <Tag color={row.active ? "green" : "red"}>
                    {row.active
                      ? l("فعال", "Active")
                      : l("غیرفعال", "Inactive")}
                  </Tag>
                  {row.is_system_admin && (
                    <Tag color="orange">
                      {l("مدیر سامانه", "System administrator")}
                    </Tag>
                  )}
                  {row.must_change_password && (
                    <Tag>
                      {l("تغییر رمز الزامی", "Password change required")}
                    </Tag>
                  )}
                </Space>
              ),
            },
            {
              title: l("پایان دسترسی (شمسی)", "Access expiry (Solar Hijri)"),
              dataIndex: "expires_on",
              render: (value) => civilToSolar(value) || "—",
            },
            {
              title: l("عملیات", "Actions"),
              render: (_, row) => (
                <Space>
                  <Button
                    disabled={row.id === currentUser}
                    onClick={() => edit("edit", row)}
                  >
                    {l("ویرایش", "Edit")}
                  </Button>
                  <Button
                    disabled={row.id === currentUser}
                    onClick={() => edit("reset", row)}
                  >
                    {l("بازنشانی رمز", "Reset password")}
                  </Button>
                </Space>
              ),
            },
          ]}
        />
      </Drawer>
      <Drawer
        key={mode || "closed"}
        open={!!mode}
        onClose={() => {
          if (!saving) setMode(null);
        }}
        width={620}
        placement={mobile ? "bottom" : language === "fa" ? "left" : "right"}
        height={mobile ? "92dvh" : undefined}
        rootClassName="editor-sheet"
        title={
          mode === "reset"
            ? l("رمز موقت ۲۴ساعته", "24-hour temporary password")
            : mode === "create"
              ? l("کاربر جدید", "New account")
              : l("ویرایش حساب", "Edit account")
        }
        footer={
          <Space>
            <Button type="primary" onClick={save} loading={saving}>
              {l("ذخیره", "Save")}
            </Button>
            <Button disabled={saving} onClick={() => setMode(null)}>
              {l("انصراف", "Cancel")}
            </Button>
          </Space>
        }
      >
        <Form form={form} layout="vertical" name="afra-system-user">
          {mode !== "reset" && (
            <>
              <Form.Item
                name="name"
                label={l("نام", "Name")}
                rules={[{ required: true, max: 200 }]}
              >
                <Input />
              </Form.Item>
              <Form.Item
                name="email"
                label={l("ایمیل", "Email")}
                rules={[{ required: true, type: "email", max: 250 }]}
              >
                <Input dir="ltr" autoComplete="off" />
              </Form.Item>
              <Form.Item
                name="active"
                valuePropName="checked"
                label={l("حساب فعال", "Active account")}
              >
                <Switch />
              </Form.Item>
              <Form.Item
                name="is_system_admin"
                valuePropName="checked"
                label={l(
                  "مدیر سامانه؛ دسترسی سراسری",
                  "System administrator; global access",
                )}
              >
                <Switch />
              </Form.Item>
              <Form.Item
                name="expires_on"
                label={l(
                  "آخرین روز مجاز دسترسی (هجری شمسی)",
                  "Last allowed access day (Solar Hijri)",
                )}
                extra={l(
                  "خالی یعنی بدون انقضا؛ دسترسی تا پایان این روز به وقت تهران برقرار است.",
                  "Blank means no expiry. Access ends after this Tehran calendar day.",
                )}
                rules={[
                  {
                    validator: (_, value) =>
                      !value || solarToCivil(value)
                        ? Promise.resolve()
                        : Promise.reject(
                            new Error(
                              l(
                                "تاریخ شمسی معتبر مانند ۱۴۰۵/۰۹/۳۰ وارد کنید.",
                                "Enter a valid Solar Hijri date such as 1405/09/30.",
                              ),
                            ),
                          ),
                  },
                ]}
              >
                <Input dir="ltr" placeholder="1405/09/30" maxLength={10} />
              </Form.Item>
            </>
          )}
          {(mode === "create" || mode === "reset") && (
            <Form.Item
              name="password"
              label={l("گذرواژه اولیه / موقت", "Initial / temporary password")}
              rules={[{ required: true, min: 12, max: 128 }]}
            >
              <Input.Password autoComplete="new-password" />
            </Form.Item>
          )}
          {mode === "create" && (
            <Form.Item
              name="must_change_password"
              valuePropName="checked"
              label={l(
                "تغییر رمز در اولین ورود",
                "Require password change at first sign-in",
              )}
            >
              <Switch />
            </Form.Item>
          )}
          {mode === "reset" && (
            <Typography.Paragraph>
              {l(
                "رمز موقت حداکثر ۲۴ ساعت معتبر است. نشست‌های قبلی بسته می‌شوند و کاربر پیش از استفاده باید رمز را تغییر دهد. رمز را از مسیر امن تحویل دهید.",
                "Temporary password expires in24 hours, revokes prior sessions and requires a password change. Deliver it through a secure channel.",
              )}
            </Typography.Paragraph>
          )}
          <Form.Item
            name="reason"
            label={l("دلیل اقدام", "Reason")}
            rules={[{ required: true, min: 10, max: 1000 }]}
          >
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Drawer>
    </>
  );
}
