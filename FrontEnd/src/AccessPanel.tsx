import React, { useEffect, useState, useRef } from "react";
import {
  App,
  Button,
  Card,
  Drawer,
  Form,
  Input,
  Select,
  Space,
  Switch,
  Table,
  Tabs,
  Tag,
  Typography,
} from "antd";
import { EditOutlined, PlusOutlined } from "@ant-design/icons";
import { api, ApiError, send } from "./api";
import {
  EffectIcon,
  Effect,
  Grants,
  Permission,
  PermissionEditor,
} from "./PermissionEditor";
import { MemberPicker } from "./MemberPicker";
type Kind = "member" | "group" | "role" | "section";
type Member = {
  id: number;
  name: string;
  email: string;
  role: string;
  custom_role_id?: string;
  membership_version: number;
  grants: Grants | string;
  active: boolean;
};
type Group = {
  id: string;
  name: string;
  active: boolean;
  version: number;
  grants: Grants | string;
  members: number[];
};
type Role = {
  id: string;
  name: string;
  active: boolean;
  version: number;
  grants: Grants | string;
};
type Section = { id: string; name: string; parent_id: string | null };
type Rule = {
  id: number;
  section_id: string;
  user_id: number;
  action: string;
  effect: Effect;
  version: number;
};
const parse = (value: Grants | string | undefined): Grants =>
  typeof value === "string" ? JSON.parse(value) : value || {};
export function AccessPanel({
  project,
  language,
  onChanged,
}: {
  project: string;
  language: "fa" | "en";
  onChanged?: () => Promise<void> | void;
}) {
  const { message } = App.useApp();
  const l = (fa: string, en: string) => (language === "fa" ? fa : en);
  const base = `v1/projects/${project}`;
  const [tab, setTab] = useState("members");
  const [members, setMembers] = useState<Member[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [groups, setGroups] = useState<Group[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [sections, setSections] = useState<Section[]>([]);
  const [rules, setRules] = useState<Rule[]>([]);
  const [catalog, setCatalog] = useState<Permission[]>([]);
  const [caps, setCaps] = useState<Record<string, boolean>>({});
  const [grantable, setGrantable] = useState<Record<string, boolean>>({});
  const [restorable, setRestorable] = useState<Record<string, boolean>>({});
  const [roleDefaults, setRoleDefaults] = useState<Record<string, string[]>>(
    {},
  );
  const [busy, setBusy] = useState(false);
  const [kind, setKind] = useState<Kind | null>(null);
  const [selected, setSelected] = useState<Member | Group | Role | Rule | null>(
    null,
  );
  const [grants, setGrants] = useState<Grants>({});
  const [form] = Form.useForm();
  const custom = Form.useWatch("role", form) === "custom";
  const [mobile, setMobile] = useState(matchMedia("(max-width:600px)").matches);
  const error = (e: unknown) => {
    if (e instanceof ApiError) {
      message.error([e.message, ...Object.values(e.fields).flat()].join(" · "));
    } else if (e instanceof Error) {
      message.error(e.message);
    }
  };
  useEffect(() => {
    const media = matchMedia("(max-width:600px)");
    const resize = () => setMobile(media.matches);
    media.addEventListener("change", resize);
    return () => media.removeEventListener("change", resize);
  }, []);
  const request = useRef(0);
  const load = async () => {
    const generation = ++request.current;
    const current = () => generation === request.current;
    setBusy(true);
    try {
      const [
        memberData,
        groupData,
        roleData,
        sectionData,
        permissionData,
        capabilityData,
      ] = await Promise.all([
        api(base + `/members?page=${page}&q=${encodeURIComponent(search)}`),
        api<Group[]>(base + "/groups"),
        api<Role[]>(base + "/roles"),
        api<Section[]>(base + "/sections"),
        api<Permission[]>(base + "/permissions"),
        api<{
          permissions: Record<string, boolean>;
          grantable: Record<string, boolean>;
          restorable: Record<string, boolean>;
          role_defaults: Record<string, string[]>;
        }>(base + "/me/capabilities"),
      ]);
      if (!current()) return;
      setMembers(memberData.data);
      setTotal(memberData.total);
      setGroups(groupData);
      setRoles(roleData);
      setSections(sectionData);
      setCatalog(permissionData);
      setCaps(capabilityData.permissions);
      setGrantable(capabilityData.grantable);
      setRestorable(capabilityData.restorable);
      setRoleDefaults(capabilityData.role_defaults);
      if (capabilityData.permissions["section_access.manage"]) {
        const data = await api<Rule[]>(base + "/section-access");
        if (current()) setRules(data);
      } else setRules([]);
    } catch (e) {
      if (current()) error(e);
    } finally {
      if (current()) setBusy(false);
    }
  };
  useEffect(() => {
    void load();
    return () => {
      request.current++;
    };
  }, [project, page, search]);
  const open = (next: Kind, row?: Member | Group | Role | Rule) => {
    setSelected(row || null);
    setGrants(row && "grants" in row ? parse(row.grants) : {});
    form.resetFields();
    if (row) {
      form.setFieldsValue({
        ...row,
        ...(next === "member" ? { user_id: row.id } : {}),
        reason: "",
      });
    } else {
      form.setFieldsValue({
        active: true,
        members: [],
        role:
          ["viewer", "tester", "reviewer", "admin", "custom"].find((role) =>
            (roleDefaults[role] || []).every((key) => caps[key]),
          ) || "viewer",
        action: "view",
        effect: "inherit",
      });
    }
    setKind(next);
  };
  const save = async () => {
    try {
      const values = await form.validateFields();
      setBusy(true);
      if (kind === "member") {
        await send(
          base + "/members",
          { ...values, ...(caps["grants.update"] ? { grants } : {}) },
          "POST",
          selected && "membership_version" in selected
            ? selected.membership_version
            : undefined,
        );
      } else if (kind === "group" || kind === "role") {
        const resource = kind === "group" ? "groups" : "roles";
        await send(
          base + "/" + resource + (selected ? "/" + selected.id : ""),
          { ...values, grants },
          selected ? "PUT" : "POST",
          selected && "version" in selected ? selected.version : undefined,
        );
      } else if (kind === "section") {
        const existing = rules.find(
          (rule) =>
            rule.section_id === values.section_id &&
            rule.user_id === values.user_id &&
            rule.action === values.action,
        );
        await send(
          base + "/section-access",
          {
            rules: [
              {
                section_id: values.section_id,
                user_id: values.user_id,
                action: values.action,
                effect: values.effect,
                ...(existing ? { version: existing.version } : {}),
              },
            ],
            reason: values.reason,
          },
          "PUT",
        );
      }
      setKind(null);
      message.success(l("دسترسی‌ها ثبت شدند.", "Access changes saved."));
      await load();
      await onChanged?.();
    } catch (e) {
      error(e);
    } finally {
      setBusy(false);
    }
  };
  const path = (id: string) => {
    const names: string[] = [];
    let section = sections.find((row) => row.id === id);
    for (let depth = 0; section && depth < 8; depth++) {
      names.unshift(section.name);
      section = sections.find((row) => row.id === section?.parent_id);
    }
    return names.join(" / ");
  };
  const explicit = (value: Grants | string) => {
    const map = parse(value);
    return (
      <Space>
        <Tag color="green">
          {Object.values(map).filter((effect) => effect === "allow").length}{" "}
          {l("اجازه", "allow")}
        </Tag>
        <Tag color="red">
          {Object.values(map).filter((effect) => effect === "deny").length}{" "}
          {l("منع", "deny")}
        </Tag>
      </Space>
    );
  };
  const membershipColumns = [
    {
      title: l("نام", "Name"),
      dataIndex: "name",
      render: (name: string, row: Member) => (
        <div>
          <strong>{name}</strong>
          <small style={{ display: "block" }} dir="ltr">
            {row.email}
          </small>
        </div>
      ),
    },
    {
      title: l("نقش", "Role"),
      dataIndex: "role",
      render: (role: string, row: Member) => (
        <Tag>
          {role === "custom"
            ? roles.find((item) => item.id === row.custom_role_id)?.name ||
              l("سفارشی", "Custom")
            : (
                {
                  admin: l("مدیر پروژه", "Project administrator"),
                  tester: l("آزمایشگر", "Tester"),
                  reviewer: l("بازبین", "Reviewer"),
                  viewer: l("مشاهده‌گر", "Viewer"),
                } as Record<string, string>
              )[role] || role}
        </Tag>
      ),
    },
    {
      title: l("وضعیت حساب", "Account"),
      dataIndex: "active",
      render: (active: boolean) => (
        <Tag color={active ? "green" : "red"}>
          {active ? l("فعال", "Active") : l("غیرفعال", "Inactive")}
        </Tag>
      ),
    },
    {
      title: l("عملیات", "Actions"),
      render: (_: unknown, row: Member) => (
        <Button
          icon={<EditOutlined />}
          disabled={busy || !caps["members.update"]}
          onClick={() => open("member", row)}
        >
          {l("نقش و دسترسی", "Role & access")}
        </Button>
      ),
    },
  ];
  const entityColumns = (type: "group" | "role") => [
    { title: l("نام", "Name"), dataIndex: "name" },
    {
      title: l("وضعیت", "Status"),
      dataIndex: "active",
      render: (active: boolean) => (
        <Tag color={active ? "green" : "default"}>
          {active ? l("فعال", "Active") : l("غیرفعال", "Inactive")}
        </Tag>
      ),
    },
    {
      title: l("مجوزهای صریح", "Explicit grants"),
      dataIndex: "grants",
      render: explicit,
    },
    {
      title: l("عملیات", "Actions"),
      render: (_: unknown, row: Group | Role) => (
        <Button
          icon={<EditOutlined />}
          disabled={
            busy || !caps[type === "group" ? "groups.update" : "roles.update"]
          }
          onClick={() => open(type, row)}
        >
          {l("ویرایش", "Edit")}
        </Button>
      ),
    },
  ];
  return (
    <Card>
      <Tabs
        activeKey={tab}
        onChange={setTab}
        items={[
          { key: "members", label: l("اعضای پروژه", "Project members") },
          { key: "groups", label: l("گروه‌های دسترسی", "Access groups") },
          { key: "roles", label: l("نقش‌های سفارشی", "Custom roles") },
          {
            key: "sections",
            label: l("دسترسی بخش‌ها", "Section access"),
            disabled: !caps["section_access.manage"],
          },
        ]}
        tabBarExtraContent={
          <Button
            type="primary"
            icon={<PlusOutlined />}
            disabled={
              busy ||
              (tab === "members"
                ? !caps["members.add"]
                : tab === "groups"
                  ? !caps["groups.create"]
                  : tab === "roles"
                    ? !caps["roles.create"]
                    : !caps["section_access.manage"])
            }
            onClick={() =>
              open(
                tab === "members"
                  ? "member"
                  : tab === "groups"
                    ? "group"
                    : tab === "roles"
                      ? "role"
                      : "section",
              )
            }
          >
            {l("افزودن", "Add")}
          </Button>
        }
      />
      {tab === "members" ? (
        <>
          <Input.Search
            allowClear
            placeholder={l("جست‌وجوی اعضا", "Search members")}
            onSearch={(value) => {
              setSearch(value);
              setPage(1);
            }}
            style={{ maxWidth: 380, marginBottom: 20 }}
          />
          <Table<Member>
            rowKey="id"
            loading={busy}
            dataSource={members}
            columns={membershipColumns}
            pagination={{
              current: page,
              total,
              pageSize: 25,
              onChange: setPage,
              showSizeChanger: false,
            }}
            scroll={{ x: 700 }}
          />
        </>
      ) : tab === "groups" ? (
        <Table<Group>
          rowKey="id"
          loading={busy}
          dataSource={groups}
          columns={entityColumns("group")}
          scroll={{ x: 650 }}
        />
      ) : tab === "roles" ? (
        <Table<Role>
          rowKey="id"
          loading={busy}
          dataSource={roles}
          columns={entityColumns("role")}
          scroll={{ x: 650 }}
        />
      ) : (
        <Table<Rule>
          rowKey="id"
          loading={busy}
          dataSource={rules}
          columns={[
            {
              title: l("بخش", "Section"),
              dataIndex: "section_id",
              render: path,
            },
            {
              title: l("کاربر", "User"),
              dataIndex: "user_id",
              render: (id: number) =>
                members.find((member) => member.id === id)?.name || "#" + id,
            },
            {
              title: l("عملیات", "Action"),
              dataIndex: "action",
              render: (key: string) =>
                (
                  ({
                    view: l("مشاهده", "View"),
                    edit: l("ویرایش", "Edit"),
                    execute: l("اجرا", "Execute"),
                  }) as Record<string, string>
                )[key] ||
                catalog.find((permission) => permission.key === key)?.[
                  language
                ] ||
                key,
            },
            {
              title: l("دسترسی", "Access"),
              dataIndex: "effect",
              render: (effect: Effect) => (
                <EffectIcon
                  effect={effect}
                  label={
                    effect === "allow"
                      ? l("مجاز", "Allowed")
                      : effect === "deny"
                        ? l("غیرمجاز", "Denied")
                        : l("ارث‌بری", "Inherited")
                  }
                />
              ),
            },
            {
              title: l("عملیات", "Actions"),
              render: (_: unknown, row: Rule) => (
                <Button
                  icon={<EditOutlined />}
                  onClick={() => open("section", row)}
                >
                  {l("ویرایش", "Edit")}
                </Button>
              ),
            },
          ]}
          scroll={{ x: 650 }}
        />
      )}
      <Drawer
        key={kind || "closed"}
        open={!!kind}
        onClose={() => setKind(null)}
        placement={mobile ? "bottom" : language === "fa" ? "left" : "right"}
        height={mobile ? "92dvh" : undefined}
        width={900}
        rootClassName="editor-sheet"
        title={
          kind === "member"
            ? l("عضویت و مجوز کاربر", "Membership & user grants")
            : kind === "group"
              ? l("گروه دسترسی", "Access group")
              : kind === "role"
                ? l("نقش سفارشی", "Custom role")
                : l("دسترسی بخش", "Section access")
        }
        footer={
          <Space>
            <Button
              type="primary"
              aria-label={l("ذخیره", "Save")}
              aria-busy={busy}
              disabled={busy}
              loading={busy}
              onClick={save}
            >
              {l("ذخیره", "Save")}
            </Button>
            <Button onClick={() => setKind(null)}>
              {l("انصراف", "Cancel")}
            </Button>
          </Space>
        }
      >
        <Form name="afra-access-editor" form={form} layout="vertical">
          {kind === "member" ? (
            <>
              <Form.Item
                name="user_id"
                label={l("کاربر سازمان", "Organization account")}
                rules={[{ required: true }]}
                extra={
                  !selected
                    ? l(
                        "برای یافتن حساب موجود، حداقل دو حرف از نام یا ایمیل را بنویسید.",
                        "Type at least two characters of an existing name or email.",
                      )
                    : undefined
                }
              >
                <MemberPicker
                  key={"member-" + (selected?.id || "new")}
                  project={project}
                  source="directory"
                  disabled={!!selected}
                  initialOptions={
                    selected && "email" in selected
                      ? [
                          {
                            value: selected.id,
                            label: selected.name + " · " + selected.email,
                          },
                        ]
                      : []
                  }
                />
              </Form.Item>
              <Form.Item
                name="role"
                label={l("نقش پایه", "Base role")}
                rules={[{ required: true }]}
              >
                <Select
                  options={[
                    {
                      value: "viewer",
                      label: l("مشاهده‌گر", "Viewer"),
                      disabled: !(roleDefaults.viewer || []).every(
                        (key) => caps[key],
                      ),
                    },
                    {
                      value: "tester",
                      label: l("آزمایشگر", "Tester"),
                      disabled: !(roleDefaults.tester || []).every(
                        (key) => caps[key],
                      ),
                    },
                    {
                      value: "reviewer",
                      label: l("بازبین", "Reviewer"),
                      disabled: !(roleDefaults.reviewer || []).every(
                        (key) => caps[key],
                      ),
                    },
                    {
                      value: "admin",
                      label: l("مدیر پروژه", "Project administrator"),
                      disabled:
                        !caps["access.manage"] ||
                        !(roleDefaults.admin || []).every((key) => caps[key]),
                    },
                    { value: "custom", label: l("سفارشی", "Custom") },
                  ]}
                />
              </Form.Item>
              {custom && (
                <Form.Item
                  name="custom_role_id"
                  label={l("نقش سفارشی", "Custom role")}
                  rules={[{ required: true }]}
                >
                  <Select
                    options={roles
                      .filter((role) => role.active)
                      .map((role) => ({ value: role.id, label: role.name }))}
                  />
                </Form.Item>
              )}
            </>
          ) : kind === "group" || kind === "role" ? (
            <>
              <Form.Item
                name="name"
                label={l("نام", "Name")}
                rules={[{ required: true, max: 100 }]}
              >
                <Input />
              </Form.Item>
              <Form.Item
                name="active"
                label={l("فعال", "Active")}
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
              {kind === "group" && (
                <Form.Item
                  name="members"
                  label={l(
                    "اعضای گروه در همین پروژه",
                    "Group members in this project",
                  )}
                >
                  <MemberPicker project={project} multiple />
                </Form.Item>
              )}
            </>
          ) : kind === "section" ? (
            <>
              <Form.Item
                name="section_id"
                label={l("بخش", "Section")}
                rules={[{ required: true }]}
              >
                <Select
                  showSearch
                  optionFilterProp="label"
                  options={sections.map((section) => ({
                    value: section.id,
                    label: path(section.id),
                  }))}
                />
              </Form.Item>
              <Form.Item
                name="user_id"
                label={l("کاربر پروژه", "Project member")}
                rules={[{ required: true }]}
              >
                <MemberPicker project={project} />
              </Form.Item>
              <Form.Item
                name="action"
                label={l("نوع دسترسی", "Access action")}
                rules={[{ required: true }]}
              >
                <Select
                  showSearch
                  optionFilterProp="label"
                  options={[
                    {
                      value: "view",
                      label: l("مشاهده بخش", "Section visibility"),
                    },
                    {
                      value: "edit",
                      label: l("ویرایش در بخش", "Section editing"),
                    },
                    {
                      value: "execute",
                      label: l("اجرا در بخش", "Section execution"),
                    },
                    ...catalog
                      .filter((permission) =>
                        /^(cases|runs|defects|attachments)\./.test(
                          permission.key,
                        ),
                      )
                      .map((permission) => ({
                        value: permission.key,
                        label: permission[language],
                      })),
                  ]}
                />
              </Form.Item>
              <Form.Item
                name="effect"
                label={l("وضعیت مجوز", "Permission effect")}
                rules={[{ required: true }]}
              >
                <Select
                  options={[
                    {
                      value: "inherit",
                      label: l("ارث‌بری از والد", "Inherit parent"),
                    },
                    { value: "allow", label: l("مجاز", "Allowed") },
                    { value: "deny", label: l("غیرمجاز", "Denied") },
                  ]}
                />
              </Form.Item>
              <Typography.Paragraph type="secondary">
                {l(
                  "قاعده نزدیک‌تر در درخت، ارث‌بری را تعیین می‌کند. منع مشاهده، دسترسی به تست‌ها و تاریخچه همان بخش را هم می‌بندد.",
                  "The nearest explicit tree rule resolves inheritance. A visibility denial also protects the section’s cases and history.",
                )}
              </Typography.Paragraph>
            </>
          ) : null}
          {(kind === "group" ||
            kind === "role" ||
            (kind === "member" && caps["grants.update"])) && (
            <PermissionEditor
              catalog={catalog}
              value={grants}
              onChange={setGrants}
              language={language}
              grantable={grantable}
              restorable={restorable}
            />
          )}
          <Form.Item
            name="reason"
            label={l("دلیل تغییر دسترسی", "Reason for access change")}
            rules={[{ required: true, min: 10, max: 1000 }]}
          >
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Drawer>
    </Card>
  );
}
