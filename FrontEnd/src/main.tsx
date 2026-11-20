import React, { useEffect, useState, useRef } from "react";
import { createRoot } from "react-dom/client";
import {
  App as AntApp,
  Button,
  Card,
  ConfigProvider,
  Drawer,
  Dropdown,
  Empty,
  Form,
  Input,
  InputNumber,
  Layout,
  List,
  Menu,
  Modal,
  Select,
  Space,
  Spin,
  Statistic,
  Switch,
  Table,
  Tag,
  Tree,
  Typography,
  theme as antTheme,
} from "antd";
import faIR from "antd/locale/fa_IR";
import enUS from "antd/locale/en_US";
import {
  ApiOutlined,
  AppstoreOutlined,
  BugOutlined,
  CheckCircleOutlined,
  ExperimentOutlined,
  FileTextOutlined,
  LogoutOutlined,
  MenuOutlined,
  PlusOutlined,
  ProjectOutlined,
  SettingOutlined,
  UserOutlined,
} from "@ant-design/icons";
import {
  api,
  send,
  setCredentials,
  ApiError,
  bootstrapSession,
  endSession,
} from "./api";
import { Evidence } from "./Evidence";
import { RequirementsPanel } from "./RequirementsPanel";
import { RequirementPicker } from "./RequirementPicker";
import { SystemUsers } from "./SystemUsers";
import { AccessPanel } from "./AccessPanel";
import { MemberPicker } from "./MemberPicker";
import "./style.css";
type Language = "fa" | "en";
type Mode = "light" | "dark" | "system";
type RecordData = {
  id: string;
  title: string;
  version: number;
  content: any;
  [key: string]: any;
};
const labels: Record<string, [string, string]> = {
  result: ["ثبت نتیجه آزمون", "Record test result"],
  transition: ["جزئیات خطا", "Defect details"],
  section: ["بخش پروژه", "Project section"],
  project: ["پروژه", "Project"],
  overview: ["نمای کلی", "Overview"],
  tests: ["تست‌ها و نیازمندی‌ها", "Tests & requirements"],
  planning: ["برنامه‌ریزی", "Planning"],
  execution: ["اجرا و تاریخچه", "Execution & history"],
  defects: ["خطاها", "Defects"],
  quality: ["کیفیت و انتشار", "Quality & releases"],
  people: ["پروژه و افراد", "Project & people"],
  cases: ["تست کیس‌ها", "Test cases"],
  requirements: ["نیازمندی‌ها", "Requirements"],
  "shared-steps": ["مراحل مشترک", "Shared steps"],
  templates: ["قالب‌ها", "Templates"],
  datasets: ["مجموعه داده‌ها", "Datasets"],
  "saved-views": ["نماهای ذخیره‌شده", "Saved views"],
  plans: ["برنامه‌های آزمون", "Test plans"],
  "configuration-profiles": ["ماتریس پیکربندی", "Configuration matrix"],
  environments: ["محیط‌ها", "Environments"],
  builds: ["ساخت‌ها", "Builds"],
  releases: ["انتشارها", "Releases"],
  audit: ["رویدادهای پروژه", "Project activity"],
  users: ["کاربران و دسترسی", "Users & access"],
  "custom-fields": ["فیلدهای سفارشی", "Custom fields"],
  services: ["سرویس‌ها و مصرف", "Services & usage"],
  profile: ["پروفایل من", "My profile"],
  password: ["تغییر گذرواژه", "Change password"],
  appearance: ["ظاهر و زبان", "Appearance & language"],
  logout: ["خروج", "Sign out"],
};
const groups: Record<string, string[]> = {
  overview: [],
  tests: [
    "cases",
    "requirements",
    "shared-steps",
    "templates",
    "datasets",
    "saved-views",
  ],
  planning: ["plans", "configuration-profiles", "environments", "builds"],
  execution: [],
  defects: [],
  quality: ["releases"],
  people: ["users", "audit", "custom-fields", "services"],
};
const icons = [
  <AppstoreOutlined />,
  <ExperimentOutlined />,
  <ProjectOutlined />,
  <CheckCircleOutlined />,
  <BugOutlined />,
  <FileTextOutlined />,
  <UserOutlined />,
];
function Application() {
  const { message } = AntApp.useApp();
  const [lang, setLang] = useState<Language>(
    (localStorage.getItem("afra.language") as Language) || "fa",
  );
  const [mode, setMode] = useState<Mode>(
    (localStorage.getItem("afra.theme") as Mode) || "system",
  );
  const [mobile, setMobile] = useState(
    matchMedia("(max-width: 600px)").matches,
  );
  const [systemDark, setSystemDark] = useState(
    matchMedia("(prefers-color-scheme: dark)").matches,
  );
  const dark = mode === "dark" || (mode === "system" && systemDark);
  const t = (k: string) => labels[k]?.[lang === "fa" ? 0 : 1] || k;
  const l = (fa: string, en: string) => (lang === "fa" ? fa : en);
  const [user, setUser] = useState<any>(null);
  const [capabilities, setCapabilities] = useState<Record<string, boolean>>({});
  const [projects, setProjects] = useState<any[]>([]);
  const [project, setProject] = useState("");
  const [group, setGroup] = useState("overview");
  const [tab, setTab] = useState("overview");
  const [nav, setNav] = useState(false);
  const [busy, setBusy] = useState(false);
  const [rows, setRows] = useState<any[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState("");
  const [sections, setSections] = useState<any[]>([]);
  const [section, setSection] = useState("");
  const [stats, setStats] = useState<any>({});
  const [editor, setEditor] = useState<string | null>(null);
  const [selected, setSelected] = useState<any>(null);
  const [run, setRun] = useState<any>(null);
  const [form] = Form.useForm();
  const [checks, setChecks] = useState<string[]>([]);
  const [evidenceIds, setEvidenceIds] = useState<string[]>([]);
  const [runItem, setRunItem] = useState<any>(null);
  const [historyItem, setHistoryItem] = useState<any>(null);
  const [assignmentTarget, setAssignmentTarget] = useState<any>(null);
  const [assignmentForm] = Form.useForm();
  const [reviewTarget, setReviewTarget] = useState<any>(null);
  const [reviewForm] = Form.useForm();
  const [picked, setPicked] = useState<React.Key[]>([]);
  const workspaceGeneration = useRef(0);
  const projectRequest = useRef(0);
  const dataRequest = useRef(0);
  const [systemUsersOpen, setSystemUsersOpen] = useState(false);
  const resetWorkspace = () => {
    setSystemUsersOpen(false);
    workspaceGeneration.current++;
    projectRequest.current++;
    dataRequest.current++;
    setBusy(false);
    setProjects([]);
    setProject("");
    setRows([]);
    setSections([]);
    setStats({});
    setRun(null);
    setGroup("overview");
    setTab("overview");
    setQuery("");
    setPage(1);
    setSection("");
    setPicked([]);
    setSelected(null);
    setEditor(null);
    setReviewTarget(null);
    setAssignmentTarget(null);
    setHistoryItem(null);
    setRunItem(null);
    setChecks([]);
    setEvidenceIds([]);
    setCapabilities({});
    if (editor) form.resetFields();
    if (assignmentTarget) assignmentForm.resetFields();
    if (reviewTarget) reviewForm.resetFields();
    message.destroy();
  };
  const logout = async () => {
    try {
      await endSession();
      setUser(null);
      setCredentials("", "");
      resetWorkspace();
    } catch (e) {
      error(e);
    }
  };
  const error = (e: unknown) => {
    const a = e as ApiError;
    message.error(
      [a.message, ...Object.values(a.fields || {}).flat()].join(" · "),
    );
  };
  useEffect(() => {
    const small = matchMedia("(max-width: 600px)");
    const resize = () => setMobile(small.matches);
    small.addEventListener("change", resize);
    return () => small.removeEventListener("change", resize);
  }, []);
  useEffect(() => {
    const media = matchMedia("(prefers-color-scheme: dark)");
    const fn = () => setSystemDark(media.matches);
    media.addEventListener("change", fn);
    return () => media.removeEventListener("change", fn);
  }, []);
  useEffect(() => {
    localStorage.setItem("afra.language", lang);
    localStorage.setItem("afra.theme", mode);
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === "fa" ? "rtl" : "ltr";
    document.documentElement.dataset.theme = dark ? "dark" : "light";
  }, [lang, mode, dark]);
  const loadProjects = async () => {
    const generation = workspaceGeneration.current;
    const request = ++projectRequest.current;
    const d = await api("v1/projects?per_page=100");
    if (project && !d.data.some((item: any) => item.id === project)) {
      try {
        d.data.push(await api(`v1/projects/${project}`));
      } catch (e) {
        if (!(e instanceof ApiError) || e.status !== 404) throw e;
      }
    }
    if (
      generation !== workspaceGeneration.current ||
      request !== projectRequest.current
    )
      return;
    setProjects(d.data);
    setProject((p) =>
      d.data.some((item: any) => item.id === p) ? p : d.data[0]?.id || "",
    );
  };
  const [authReady, setAuthReady] = useState(false);
  useEffect(() => {
    let mounted = true;
    bootstrapSession()
      .then(async (identity) => {
        if (!mounted) return;
        if (identity) {
          setUser(identity);
          await loadProjects();
        }
      })
      .catch(() => {
        /* Invalid sessions return to the sign-in form. */
      })
      .finally(() => {
        if (mounted) setAuthReady(true);
      });
    const changed = () => {
      setUser(null);
      resetWorkspace();
      setAuthReady(true);
    };
    window.addEventListener("afra-session-changed", changed);
    return () => {
      mounted = false;
      window.removeEventListener("afra-session-changed", changed);
    };
  }, []);
  const searchProjects = async (query: string) => {
    const generation = workspaceGeneration.current;
    const request = ++projectRequest.current;
    try {
      const data = await api(
        `v1/projects?per_page=100&q=${encodeURIComponent(query)}`,
      );
      if (
        generation !== workspaceGeneration.current ||
        request !== projectRequest.current
      )
        return;
      setProjects((previous) => {
        const selected = previous.find((item) => item.id === project);
        return selected && !data.data.some((item: any) => item.id === project)
          ? [selected, ...data.data]
          : data.data;
      });
    } catch (e) {
      if (
        generation === workspaceGeneration.current &&
        request === projectRequest.current
      )
        error(e);
    }
  };
  const login = async (values: any) => {
    setBusy(true);
    try {
      const d = await send("auth/login", values);
      resetWorkspace();
      setCredentials(d.access_token, d.csrf_token);
      setUser(d.user);
      if (d.user.must_change_password) {
        form.resetFields();
        setEditor("password");
      } else {
        await loadProjects();
      }
    } catch (e) {
      error(e);
    } finally {
      setBusy(false);
    }
  };
  const base = `v1/projects/${project}`;
  useEffect(() => {
    if (!project || !user || user.must_change_password) {
      setCapabilities({});
      return;
    }
    let current = true;
    const generation = workspaceGeneration.current;
    setCapabilities({});
    api(base + "/me/capabilities")
      .then((data) => {
        if (current && generation === workspaceGeneration.current)
          setCapabilities(data.permissions);
      })
      .catch((e) => {
        if (current && generation === workspaceGeneration.current) error(e);
      });
    return () => {
      current = false;
    };
  }, [project, user, tab]);
  const load = async () => {
    const request = ++dataRequest.current;
    const generation = workspaceGeneration.current;
    const current = () =>
      request === dataRequest.current &&
      generation === workspaceGeneration.current;
    if (!project || !user || user.must_change_password) return;
    if (tab === "users" || tab === "requirements") {
      setRows([]);
      setBusy(false);
      return;
    }
    setBusy(true);
    try {
      const [s, d] = await Promise.all([
        api(base + "/sections"),
        api(
          base +
            (tab === "overview"
              ? "/dashboard"
              : tab === "cases"
                ? `/cases?page=${page}&q=${encodeURIComponent(query)}${section ? "&section_id=" + section : ""}`
                : tab === "execution"
                  ? `/runs?page=${page}`
                  : tab === "defects"
                    ? `/defects?page=${page}`
                    : tab === "audit"
                      ? `/audit?page=${page}${query ? "&action=" + encodeURIComponent(query) : ""}`
                      : tab === "users"
                        ? "/members"
                        : tab === "services"
                          ? "/services"
                          : `/records/${tab}?page=${page}`),
        ),
      ]);
      if (!current()) return;
      setSections(s);
      if (tab === "overview") {
        setStats(d);
        setRows([]);
      } else {
        setRows(Array.isArray(d) ? d : d.data || []);
        setTotal(d.total || d.length || 0);
      }
    } catch (e) {
      if (current()) {
        error(e);
        setRows([]);
      }
    } finally {
      if (current()) setBusy(false);
    }
  };
  useEffect(() => {
    void load();
    return () => {
      dataRequest.current++;
    };
  }, [project, tab, page, query, section, user]);
  useEffect(() => setPicked([]), [project, tab, page, query, section]);
  const choose = (key: string) => {
    setGroup(key);
    setTab(groups[key][0] || key);
    setPage(1);
    setQuery("");
    setNav(false);
    setRun(null);
  };
  const open = (kind: string, row?: any) => {
    setSelected(row || null);
    form.resetFields();
    if (row) {
      form.setFieldsValue({
        ...row,
        content:
          typeof row.content === "string"
            ? JSON.parse(row.content)
            : row.content,
      });
    } else if (kind === "cases") {
      form.setFieldsValue({
        priority: "medium",
        content: {
          type: "functional",
          estimate: 5,
          checklist: [{ text: "", expected: "", required: true }],
        },
      });
    }
    setEditor(kind);
  };
  const save = async () => {
    try {
      const v = await form.validateFields();
      setBusy(true);
      if (editor === "project") {
        await send("v1/projects", v);
        await loadProjects();
      } else if (editor === "section") {
        await send(base + "/sections", v);
      } else if (editor === "password") {
        await send("v1/me/password", v);
        setUser(null);
        setCredentials("", "");
        resetWorkspace();
        message.success(
          l(
            "گذرواژه تغییر کرد؛ دوباره وارد شوید.",
            "Password changed. Please sign in again.",
          ),
        );
      } else if (editor === "cases") {
        if (!capabilities["cases.assign"]) {
          delete v.assignee_id;
          delete v.substitute_id;
        } else {
          v.assignee_id ??= null;
          v.substitute_id ??= null;
        }
        if (selected) v.assignment_version = selected.assignment_version;
        await send(
          base + "/cases" + (selected ? "/" + selected.id : ""),
          v,
          selected ? "PUT" : "POST",
          selected?.version,
        );
      } else if (editor === "execution") {
        await send(base + "/runs", {
          ...v,
          cases: rows
            .filter((r) => picked.includes(r.id))
            .map((r) => ({ id: r.id, version: r.version })),
        });
      } else if (editor === "result") {
        await send(`${base}/runs/${run.id}/items/${runItem.id}/results`, {
          ...v,
          checks,
          evidence_ids: evidenceIds,
          request_id: crypto.randomUUID(),
        });
        setRun(await api(base + "/runs/" + run.id));
      } else if (editor === "transition") {
        await send(base + "/defects/" + selected.id + "/transition", v);
      } else if (editor === "defects") {
        await send(base + "/defects", v);
      } else if (editor === "users") {
        await send(base + "/members", v);
      } else if (editor === "profile") {
        await send("v1/me/profile", v, "PATCH");
        setUser(await api("v1/me"));
      } else if (editor) {
        await send(
          base + "/records/" + editor + (selected ? "/" + selected.id : ""),
          {
            ...v,
            content: {
              ...(selected
                ? typeof selected.content === "string"
                  ? JSON.parse(selected.content)
                  : selected.content
                : {}),
              ...v.content,
            },
          },
          selected ? "PUT" : "POST",
          selected?.version,
        );
      }
      setEditor(null);
      await load();
    } catch (e) {
      if (e instanceof ApiError) {
        error(e);
      }
    } finally {
      setBusy(false);
    }
  };
  const saveAssignment = async () => {
    try {
      const values = await assignmentForm.validateFields();
      setBusy(true);
      await send(
        `${base}/cases/${assignmentTarget.id}/assignment`,
        {
          ...values,
          assignee_id: values.assignee_id ?? null,
          substitute_id: values.substitute_id ?? null,
        },
        "POST",
        assignmentTarget.assignment_version,
      );
      setAssignmentTarget(null);
      message.success(l("تخصیص آزمایشگر ثبت شد.", "Tester assignment saved."));
      await load();
    } catch (e) {
      if (e instanceof ApiError) error(e);
    } finally {
      setBusy(false);
    }
  };
  const saveReview = async () => {
    try {
      const values = await reviewForm.validateFields();
      setBusy(true);
      await send(`${base}/cases/${reviewTarget.record.id}/review`, {
        ...values,
        decision: reviewTarget.decision,
        version: reviewTarget.record.version,
      });
      setReviewTarget(null);
      await load();
    } catch (e) {
      if (e instanceof ApiError) error(e);
    } finally {
      setBusy(false);
    }
  };
  const date = (value: string) =>
    new Intl.DateTimeFormat(
      lang === "fa" ? "fa-IR-u-ca-persian" : "en-US-u-ca-persian",
      { dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Tehran" },
    ).format(new Date(value));
  const tree = (parent: string | null = null): any[] =>
    sections
      .filter((s) => (s.parent_id || null) === parent)
      .map((s) => ({ key: s.id, title: s.name, children: tree(s.id) }));
  const heading = labels[tab] ? t(tab) : t(group);
  const columns: any[] = [
    {
      title: l("عنوان", "Title"),
      dataIndex: tab === "users" ? "name" : "title",
      render: (v: any, row: any) => (
        <>
          <strong>{v || row.action}</strong>
          {row.status && (
            <Tag
              color={
                row.status === "approved" || row.status === "closed"
                  ? "green"
                  : row.status === "pending"
                    ? "orange"
                    : "default"
              }
            >
              {row.status}
            </Tag>
          )}
        </>
      ),
    },
    ...(tab === "cases"
      ? [
          {
            title: l("اولویت", "Priority"),
            dataIndex: "priority",
            render: (v: string) => (
              <Tag
                color={
                  v === "critical" ? "red" : v === "high" ? "orange" : "blue"
                }
              >
                {v}
              </Tag>
            ),
          },
          { title: l("نسخه", "Revision"), dataIndex: "version" },
        ]
      : []),
    {
      title: l("آخرین تغییر", "Updated"),
      dataIndex: "updated_at",
      render: (v: string) => (v ? <time dir="ltr">{date(v)}</time> : "—"),
    },
    {
      title: l("عملیات", "Actions"),
      render: (_: any, row: any) => (
        <Space wrap>
          {tab === "execution" ? (
            <Button
              onClick={async () => {
                try {
                  setRun(await api(base + "/runs/" + row.id));
                } catch (e) {
                  error(e);
                }
              }}
            >
              {l("اجرای تست", "Execute")}
            </Button>
          ) : tab === "cases" ? (
            <>
              <Button
                disabled={!capabilities["cases.assign"]}
                icon={<UserOutlined />}
                onClick={() => {
                  setAssignmentTarget(row);
                  assignmentForm.resetFields();
                  assignmentForm.setFieldsValue({
                    assignee_id: row.assignee_id,
                    substitute_id: row.substitute_id,
                  });
                }}
              >
                {l("تخصیص", "Assign")}
              </Button>
              <Button
                disabled={!capabilities["cases.update"]}
                onClick={() => open("cases", row)}
              >
                {l("ویرایش", "Edit")}
              </Button>
              <Dropdown
                menu={{
                  items: [
                    {
                      key: "submit",
                      label: l("ارسال برای بازبینی", "Request review"),
                      disabled:
                        !capabilities["cases.request_review"] ||
                        row.status !== "draft",
                    },
                    {
                      key: "approve",
                      label: l("تأیید مستقل", "Approve"),
                      disabled:
                        !capabilities["cases.review"] ||
                        row.status !== "pending" ||
                        row.author_id === user.id,
                    },
                    {
                      key: "reject",
                      label: l("برگشت به پیش‌نویس", "Return to draft"),
                      disabled:
                        !capabilities["cases.review"] ||
                        row.status !== "pending" ||
                        row.author_id === user.id,
                    },
                  ],
                  onClick: async ({ key }) => {
                    if (key === "submit") {
                      try {
                        await send(base + "/cases/" + row.id + "/review", {
                          decision: key,
                          version: row.version,
                        });
                        await load();
                      } catch (e) {
                        error(e);
                      }
                    } else {
                      const generation = workspaceGeneration.current;
                      try {
                        const record = await api(base + "/cases/" + row.id);
                        if (generation !== workspaceGeneration.current) return;
                        reviewForm.resetFields();
                        setReviewTarget({ record, decision: key });
                      } catch (e) {
                        error(e);
                      }
                    }
                  },
                }}
              >
                <Button>{l("بازبینی", "Review")}</Button>
              </Dropdown>
            </>
          ) : tab === "defects" ? (
            <Button onClick={() => open("transition", row)}>
              {l("جزئیات و تغییر وضعیت", "Details & status")}
            </Button>
          ) : tab === "audit" ? (
            <code>{row.actor_id}</code>
          ) : tab === "users" ? (
            <Tag>{row.role}</Tag>
          ) : (
            <Button onClick={() => open(tab, row)}>
              {l("ویرایش", "Edit")}
            </Button>
          )}
        </Space>
      ),
    },
  ];
  const tabs = groups[group];
  const reviewContent = reviewTarget
    ? typeof reviewTarget.record.content === "string"
      ? JSON.parse(reviewTarget.record.content)
      : reviewTarget.record.content
    : null;
  const accountItems = [
    ...(user?.is_system_admin
      ? [
          {
            key: "system-users",
            label: l("حساب‌های سازمانی", "Organization accounts"),
            icon: <UserOutlined />,
          },
        ]
      : []),
    { key: "profile", label: t("profile"), icon: <UserOutlined /> },
    { key: "password", label: t("password"), icon: <SettingOutlined /> },
    { key: "appearance", label: t("appearance"), icon: <SettingOutlined /> },
    { key: "logout", label: t("logout"), icon: <LogoutOutlined /> },
  ];
  return (
    <ConfigProvider
      direction={lang === "fa" ? "rtl" : "ltr"}
      locale={lang === "fa" ? faIR : enUS}
      theme={{
        algorithm: dark ? antTheme.darkAlgorithm : antTheme.defaultAlgorithm,
        token: {
          colorPrimary: "#fa791b",
          borderRadius: 10,
          fontFamily: "Vazirmatn, sans-serif",
          fontSize: 15,
          colorBgLayout: dark ? "#10151d" : "#f5f7fb",
          colorBgContainer: dark ? "#1b2330" : "#fff",
        },
      }}
    >
      {!user ? (
        <main className="login">
          <div className="login-art">
            <div className="brand-mark">
              <img src="/afra-logo.png" alt="Afra" />
            </div>
            <h1>{l("افرا", "Afra")}</h1>
            <p>
              {l(
                "کیفیت، از هر شاخه تا نتیجه",
                "Quality, from every branch to the result",
              )}
            </p>
          </div>
          <Card className="login-card">
            <Tag color="orange">AFRA / QUALITY</Tag>
            <Typography.Title level={2}>
              {l("به افرا خوش آمدید", "Welcome to Afra")}
            </Typography.Title>
            <p>
              {l(
                "با حساب سازمانی خود وارد شوید.",
                "Sign in with your organization account.",
              )}
            </p>
            <Form layout="vertical" onFinish={login}>
              <Form.Item
                name="email"
                label={l("ایمیل", "Email")}
                rules={[{ required: true, type: "email" }]}
              >
                <Input dir="ltr" autoComplete="username" />
              </Form.Item>
              <Form.Item
                name="password"
                label={l("گذرواژه", "Password")}
                rules={[{ required: true }]}
              >
                <Input.Password dir="ltr" autoComplete="current-password" />
              </Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                block
                disabled={!authReady}
                loading={busy || !authReady}
              >
                {l("ورود به سامانه", "Sign in")}
              </Button>
            </Form>
            <Button
              type="text"
              onClick={() => setLang(lang === "fa" ? "en" : "fa")}
            >
              {lang === "fa" ? "English" : "فارسی"}
            </Button>
          </Card>
        </main>
      ) : (
        <Layout className="shell">
          <Layout.Sider
            width={248}
            className="desktop-nav"
            theme={dark ? "dark" : "light"}
          >
            <div className="brand">
              <img src="/afra-logo.png" alt="" />
              <div>
                <b>{l("افرا", "Afra")}</b>
                <small>
                  {l("مدیریت کیفیت و آزمون", "Quality & test management")}
                </small>
              </div>
            </div>
            <Menu
              selectedKeys={[group]}
              mode="inline"
              items={Object.keys(groups).map((key, i) => ({
                key,
                label: t(key),
                icon: icons[i],
              }))}
              onClick={({ key }) => choose(key)}
            />
            <div className="nav-caption">
              {l(
                "دقت در هر گام، اعتماد در هر انتشار",
                "Care in every step. Confidence in every release.",
              )}
            </div>
          </Layout.Sider>
          <Layout>
            <Layout.Header className="topbar">
              <Space>
                <Button
                  className="mobile-only"
                  icon={<MenuOutlined />}
                  onClick={() => setNav(true)}
                  aria-label="Menu"
                />
                <Select
                  showSearch
                  filterOption={false}
                  onSearch={searchProjects}
                  aria-label={l("پروژه", "Project")}
                  value={project || undefined}
                  placeholder={l("انتخاب پروژه", "Select project")}
                  style={{ minWidth: 190 }}
                  options={projects.map((p) => ({
                    label: p.name,
                    value: p.id,
                  }))}
                  onChange={(v) => {
                    setProject(v);
                    setSection("");
                    setRun(null);
                  }}
                />
                {user.is_system_admin && (
                  <Button
                    icon={<PlusOutlined />}
                    onClick={() => open("project")}
                    aria-label={l("پروژه جدید", "New project")}
                  />
                )}
              </Space>
              <Dropdown
                trigger={["click"]}
                menu={{
                  items: accountItems,
                  onClick: async ({ key }) => {
                    if (key === "system-users") {
                      setSystemUsersOpen(true);
                    } else if (key === "logout") {
                      await logout();
                    } else if (key === "profile") {
                      open(key, { ...user, ...user.profile });
                    } else {
                      open(key);
                    }
                  },
                }}
              >
                <Button
                  className="account-menu"
                  shape="round"
                  icon={<UserOutlined />}
                >
                  <span className="account-name">{user.name}</span>
                </Button>
              </Dropdown>
            </Layout.Header>
            <Layout.Content className="workspace">
              {tabs.length > 0 && (
                <div className="subnav">
                  <Menu
                    mode="horizontal"
                    selectedKeys={[tab]}
                    items={tabs.map((k) => ({ key: k, label: t(k) }))}
                    onClick={({ key }) => {
                      setTab(key);
                      setPage(1);
                      setQuery("");
                      setRun(null);
                    }}
                  />
                </div>
              )}
              <div className="page-heading">
                <div>
                  <span className="eyebrow">
                    {projects.find((p) => p.id === project)?.name || "AFRA"}
                  </span>
                  <h1>{heading}</h1>
                  <p>
                    {l(
                      "تست‌ها، تصمیم‌ها و تاریخچه را در یک مسیر روشن دنبال کنید.",
                      "Follow tests, decisions and history in one clear workspace.",
                    )}
                  </p>
                </div>
                <Space wrap>
                  {tab === "overview" && (
                    <Button
                      icon={<FileTextOutlined />}
                      onClick={() => window.print()}
                    >
                      {l("خروجی PDF / چاپ", "PDF / Print")}
                    </Button>
                  )}
                  {tab === "cases" && (
                    <Button
                      disabled={!picked.length}
                      onClick={() => {
                        form.resetFields();
                        setEditor("execution");
                      }}
                    >
                      {l("ساخت اجرا از انتخاب‌ها", "Create run from selection")}
                    </Button>
                  )}
                  {![
                    "overview",
                    "audit",
                    "services",
                    "execution",
                    "users",
                    "requirements",
                  ].includes(tab) && (
                    <Button
                      type="primary"
                      icon={<PlusOutlined />}
                      disabled={!project}
                      onClick={() => open(tab)}
                    >
                      {l("افزودن", "Add")}
                    </Button>
                  )}
                </Space>
              </div>
              {!project ? (
                <Empty
                  description={l(
                    "ابتدا یک پروژه ایجاد یا انتخاب کنید.",
                    "Create or select a project to begin.",
                  )}
                />
              ) : (
                <Spin spinning={busy}>
                  {tab === "overview" ? (
                    <>
                      <div className="hero">
                        <div>
                          <Tag color="orange">
                            {l("دید یکپارچه کیفیت", "Quality at a glance")}
                          </Tag>
                          <h2>
                            {l(
                              "هر نتیجه، پشتوانه یک تصمیم",
                              "Every result supports a decision",
                            )}
                          </h2>
                          <p>
                            {l(
                              "این اعداد از داده‌های همین پروژه محاسبه می‌شوند.",
                              "These figures are calculated from this project’s actual data.",
                            )}
                          </p>
                          <Button
                            type="primary"
                            onClick={() => choose("tests")}
                          >
                            {l("مرور تست کیس‌ها", "Explore test cases")}
                          </Button>
                        </div>
                        <div className="hero-symbol">
                          <img src="/afra-logo.png" alt="" />
                        </div>
                      </div>
                      <div className="stats">
                        {[
                          ["test_cases", l("تست کیس", "Test cases")],
                          ["test_runs", l("اجرای آزمون", "Test runs")],
                          [
                            "pending_review",
                            l("منتظر بازبینی", "Awaiting review"),
                          ],
                          [
                            "open_critical",
                            l("خطای بحرانی باز", "Open critical defects"),
                          ],
                          [
                            "forced_closed",
                            l(
                              "بسته‌شده بدون تأیید",
                              "Closed without verification",
                            ),
                          ],
                          ["sections", l("بخش پروژه", "Project sections")],
                        ].map(([key, label]) => (
                          <Card key={key}>
                            <Statistic
                              title={label}
                              value={stats[key] || 0}
                              valueStyle={{
                                color:
                                  key === "open_critical"
                                    ? "#fb5b56"
                                    : "#fa791b",
                              }}
                            />
                          </Card>
                        ))}
                      </div>
                      <div className="overview-panels">
                        <Card
                          title={l("مسیر روزانه تیم", "Your team’s daily flow")}
                        >
                          <List
                            dataSource={[
                              l(
                                "تست کیس‌ها را تکمیل و برای بازبینی ارسال کنید.",
                                "Complete test cases and request independent review.",
                              ),
                              l(
                                "نسخه تأییدشده را وارد اجرای آزمون کنید.",
                                "Add approved revisions to a test run.",
                              ),
                              l(
                                "نتیجه هر مورد را ثبت و خطاها را پیگیری کنید.",
                                "Record checklist results and follow defects.",
                              ),
                            ]}
                            renderItem={(v, i) => (
                              <List.Item>
                                <Space>
                                  <Tag color="orange">{i + 1}</Tag>
                                  {v}
                                </Space>
                              </List.Item>
                            )}
                          />
                        </Card>
                        <Card
                          title={l("موارد نیازمند توجه", "Needs attention")}
                        >
                          <p>
                            {l(
                              "تست‌های منتظر بازبینی",
                              "Cases awaiting review",
                            )}
                            : <b>{stats.pending_review || 0}</b>
                          </p>
                          <p>
                            {l("خطاهای بحرانی باز", "Open critical defects")}:{" "}
                            <b>{stats.open_critical || 0}</b>
                          </p>
                          <p>
                            {l(
                              "بستن اجباری، تأیید رفع خطا محسوب نمی‌شود.",
                              "Forced closure does not verify a fix.",
                            )}
                          </p>
                          <Button onClick={() => choose("defects")}>
                            {t("defects")}
                          </Button>
                        </Card>
                      </div>
                    </>
                  ) : run ? (
                    <Card
                      title={run.title}
                      extra={
                        <Button onClick={() => setRun(null)}>
                          {l("بازگشت", "Back")}
                        </Button>
                      }
                    >
                      <List
                        dataSource={run.items}
                        renderItem={(item: any) => (
                          <List.Item
                            actions={[
                              <Button
                                key="history"
                                disabled={!item.results.length}
                                onClick={() => setHistoryItem(item)}
                              >
                                {l("تاریخچه تلاش‌ها", "Attempt history")}
                              </Button>,
                              <Button
                                key="execute"
                                type="primary"
                                onClick={() => {
                                  setRunItem(item);
                                  setEvidenceIds([]);
                                  setChecks(
                                    item.snapshot.content.checklist.map(
                                      () => "not_run",
                                    ),
                                  );
                                  form.resetFields();
                                  setEditor("result");
                                }}
                              >
                                {l("ثبت نتیجه", "Record result")}
                              </Button>,
                            ]}
                          >
                            <List.Item.Meta
                              title={item.snapshot.title}
                              description={
                                <>
                                  {l("نسخه ثابت", "Frozen revision")}{" "}
                                  {item.snapshot.version} ·{" "}
                                  {l("تعداد تلاش‌ها", "Attempts")}:{" "}
                                  {item.results.length}{" "}
                                  {item.results.at(-1) && (
                                    <Tag
                                      color={
                                        item.results.at(-1).outcome === "pass"
                                          ? "green"
                                          : "red"
                                      }
                                    >
                                      {item.results.at(-1).outcome}
                                    </Tag>
                                  )}
                                </>
                              }
                            />
                          </List.Item>
                        )}
                      />
                    </Card>
                  ) : tab === "requirements" ? (
                    <RequirementsPanel
                      key={project}
                      project={project}
                      language={lang}
                      permissions={capabilities}
                      userId={user.id}
                    />
                  ) : tab === "users" ? (
                    <AccessPanel
                      key={project}
                      project={project}
                      language={lang}
                      onChanged={loadProjects}
                    />
                  ) : (
                    <div className={tab === "cases" ? "case-layout" : ""}>
                      {tab === "cases" && (
                        <Card
                          className="section-tree"
                          title={l("بخش‌های پروژه", "Project sections")}
                          extra={
                            <Button
                              type="text"
                              icon={<PlusOutlined />}
                              onClick={() => open("section")}
                            />
                          }
                        >
                          <Button type="text" onClick={() => setSection("")}>
                            {l("همه بخش‌ها", "All sections")}
                          </Button>
                          <Tree
                            treeData={tree()}
                            selectedKeys={section ? [section] : []}
                            onSelect={(keys) =>
                              setSection(String(keys[0] || ""))
                            }
                            defaultExpandAll
                          />
                        </Card>
                      )}
                      <Card className="data-card">
                        <Input.Search
                          allowClear
                          placeholder={
                            tab === "audit"
                              ? l("فیلتر نوع عملیات", "Filter action")
                              : l("جست‌وجو در عنوان", "Search titles")
                          }
                          onSearch={(v) => {
                            setQuery(v);
                            setPage(1);
                          }}
                          style={{ maxWidth: 380, marginBottom: 20 }}
                        />
                        <Table
                          rowKey="id"
                          dataSource={rows}
                          columns={columns}
                          rowSelection={
                            tab === "cases"
                              ? { selectedRowKeys: picked, onChange: setPicked }
                              : undefined
                          }
                          pagination={{
                            current: page,
                            total,
                            pageSize: 25,
                            onChange: setPage,
                            showSizeChanger: false,
                          }}
                          scroll={{ x: 650 }}
                          locale={{
                            emptyText: (
                              <Empty
                                description={l(
                                  "هنوز موردی ثبت نشده است.",
                                  "No items yet.",
                                )}
                              />
                            ),
                          }}
                        />
                      </Card>
                    </div>
                  )}
                </Spin>
              )}
            </Layout.Content>
            <nav className="bottom-nav">
              {["overview", "tests", "execution", "defects"].map((key, i) => (
                <button
                  key={key}
                  className={key === group ? "active" : ""}
                  onClick={() => choose(key)}
                >
                  {icons[i]}
                  <span>{t(key)}</span>
                </button>
              ))}
              <button onClick={() => setNav(true)}>
                <MenuOutlined />
                <span>{l("بیشتر", "More")}</span>
              </button>
            </nav>
          </Layout>
          {user.is_system_admin && (
            <SystemUsers
              open={systemUsersOpen}
              onClose={() => setSystemUsersOpen(false)}
              language={lang}
              currentUser={user.id}
            />
          )}
          <Drawer
            open={!!reviewTarget}
            onClose={() => setReviewTarget(null)}
            placement={mobile ? "bottom" : lang === "fa" ? "left" : "right"}
            height={mobile ? "92dvh" : undefined}
            width={760}
            rootClassName="editor-sheet"
            title={l("بازبینی نسخه تست", "Review case revision")}
            footer={
              <Space>
                <Button
                  type="primary"
                  danger={reviewTarget?.decision === "reject"}
                  loading={busy}
                  onClick={saveReview}
                >
                  {reviewTarget?.decision === "approve"
                    ? l("تأیید همین نسخه", "Approve this revision")
                    : l("بازگشت به پیش‌نویس", "Return to draft")}
                </Button>
                <Button onClick={() => setReviewTarget(null)}>
                  {l("انصراف", "Cancel")}
                </Button>
              </Space>
            }
          >
            {reviewTarget && (
              <>
                <Typography.Title level={4}>
                  {reviewTarget.record.title}
                </Typography.Title>
                <Tag color="orange">
                  {l("نسخه", "Revision")} {reviewTarget.record.version}
                </Tag>
                {(reviewTarget.record.requirement_bindings || []).map(
                  (binding: any) => (
                    <Tag
                      key={binding.id}
                      color={binding.compatible ? "blue" : "red"}
                    >
                      {binding.title} · v{binding.version}
                      {!binding.compatible &&
                        l(" — نیازمند بررسی تغییر", " — stale coverage")}
                    </Tag>
                  ),
                )}
                {reviewContent.preconditions && (
                  <Card
                    size="small"
                    title={l("پیش‌شرط‌ها", "Preconditions")}
                    style={{ marginBlock: 12 }}
                  >
                    {reviewContent.preconditions}
                  </Card>
                )}
                {reviewContent.expected && (
                  <Card
                    size="small"
                    title={l(
                      "نتیجه مورد انتظار کلی",
                      "Overall expected result",
                    )}
                    style={{ marginBlock: 12 }}
                  >
                    {reviewContent.expected}
                  </Card>
                )}
                <List
                  dataSource={reviewContent.checklist}
                  renderItem={(step: any, index: number) => (
                    <List.Item>
                      <div>
                        <strong>
                          {index + 1}. {step.text}
                        </strong>
                        <p>
                          {l("نتیجه مورد انتظار: ", "Expected: ")}
                          {step.expected}
                        </p>
                        <Tag>
                          {step.required
                            ? l("الزامی", "Required")
                            : l("اختیاری", "Optional")}
                        </Tag>
                      </div>
                    </List.Item>
                  )}
                />
                <Form name="afra-review" form={reviewForm} layout="vertical">
                  <Form.Item
                    name="reason"
                    label={l("توضیح بازبینی", "Review comment")}
                    rules={
                      reviewTarget.decision === "reject"
                        ? [{ required: true, min: 10, max: 2000 }]
                        : [{ min: 10, max: 2000 }]
                    }
                  >
                    <Input.TextArea rows={3} />
                  </Form.Item>
                </Form>
              </>
            )}
          </Drawer>
          <Drawer
            open={!!assignmentTarget}
            onClose={() => setAssignmentTarget(null)}
            placement={mobile ? "bottom" : lang === "fa" ? "left" : "right"}
            height={mobile ? "92dvh" : undefined}
            width={520}
            rootClassName="editor-sheet"
            title={l("تخصیص آزمایشگر", "Assign testers")}
            footer={
              <Space>
                <Button type="primary" loading={busy} onClick={saveAssignment}>
                  {l("ذخیره", "Save")}
                </Button>
                <Button onClick={() => setAssignmentTarget(null)}>
                  {l("انصراف", "Cancel")}
                </Button>
              </Space>
            }
          >
            <Typography.Title level={5}>
              {assignmentTarget?.title}
            </Typography.Title>
            <p>
              {l(
                "این تخصیص برای اجراهای بعدی است. مسئول و جانشین اجراهای ثبت‌شده قبلی تغییر نمی‌کنند.",
                "This assignment applies to future runs. Existing execution assignments stay unchanged.",
              )}
            </p>
            <Form
              name="afra-assignment"
              form={assignmentForm}
              layout="vertical"
            >
              <Form.Item
                name="assignee_id"
                label={l("آزمایشگر مسئول", "Assigned tester")}
              >
                <MemberPicker project={project} activeOnly />
              </Form.Item>
              <Form.Item
                name="substitute_id"
                label={l("آزمایشگر جانشین", "Substitute tester")}
              >
                <MemberPicker project={project} activeOnly />
              </Form.Item>
              <Form.Item
                name="reason"
                label={l("دلیل تخصیص", "Assignment reason")}
                rules={[{ required: true, min: 10, max: 1000 }]}
              >
                <Input.TextArea rows={3} />
              </Form.Item>
            </Form>
          </Drawer>
          <Drawer
            open={!!historyItem}
            onClose={() => setHistoryItem(null)}
            placement={mobile ? "bottom" : lang === "fa" ? "left" : "right"}
            height={mobile ? "92dvh" : undefined}
            width={760}
            title={l("تاریخچه تلاش‌ها", "Attempt history")}
            rootClassName="editor-sheet"
          >
            {historyItem && (
              <>
                <Typography.Title level={4}>
                  {historyItem.snapshot.title}
                </Typography.Title>
                <p>
                  {l("نسخه ثابت تست", "Frozen case revision")}:{" "}
                  {historyItem.snapshot.version}
                </p>
                {[...historyItem.results].reverse().map((result: any) => (
                  <Card
                    key={result.id}
                    style={{ marginBottom: 16 }}
                    title={
                      <Space wrap>
                        <Tag
                          color={
                            result.outcome === "pass"
                              ? "green"
                              : result.outcome === "fail"
                                ? "red"
                                : "orange"
                          }
                        >
                          {result.outcome}
                        </Tag>
                        <span>
                          {result.actor_name ||
                            result.actor_current_name ||
                            result.actor_id}
                        </span>
                        <time dir="ltr">{date(result.created_at)}</time>
                      </Space>
                    }
                  >
                    <List
                      size="small"
                      dataSource={historyItem.snapshot.content.checklist}
                      renderItem={(step: any, index: number) => (
                        <List.Item>
                          <div>
                            <strong>
                              {index + 1}. {step.text}
                            </strong>
                            <p>{step.expected}</p>
                            <Tag>
                              {
                                (typeof result.checks === "string"
                                  ? JSON.parse(result.checks)
                                  : result.checks)[index]
                              }
                            </Tag>
                          </div>
                        </List.Item>
                      )}
                    />
                    {result.comment && <p>{result.comment}</p>}
                    <Evidence
                      project={project}
                      entityType="result"
                      entityId={result.id}
                      language={lang}
                      userId={user.id}
                    />
                  </Card>
                ))}
              </>
            )}
          </Drawer>
          <Drawer
            open={nav}
            onClose={() => setNav(false)}
            title={l("افرا", "Afra")}
            placement={lang === "fa" ? "right" : "left"}
          >
            <Menu
              selectedKeys={[group]}
              items={Object.keys(groups).map((key, i) => ({
                key,
                label: t(key),
                icon: icons[i],
              }))}
              onClick={({ key }) => choose(key)}
            />
          </Drawer>
          <Drawer
            placement={mobile ? "bottom" : lang === "fa" ? "left" : "right"}
            height={mobile ? "92dvh" : undefined}
            open={!!editor}
            onClose={() => {
              if (!user.must_change_password) setEditor(null);
            }}
            closable={!user.must_change_password}
            title={
              editor === "cases"
                ? selected
                  ? l("ویرایش تست کیس", "Edit test case")
                  : l("تست کیس جدید", "New test case")
                : editor === "execution"
                  ? l("ساخت اجرای آزمون", "Create test run")
                  : editor
                    ? labels[editor]
                      ? t(editor)
                      : l("افزودن مورد", "Add item")
                    : ""
            }
            width={editor === "cases" || editor === "result" ? 760 : 560}
            rootClassName="editor-sheet"
            footer={
              editor !== "appearance" ? (
                <Space>
                  <Button type="primary" onClick={save} loading={busy}>
                    {l("ذخیره", "Save")}
                  </Button>
                  {user.must_change_password ? (
                    <Button
                      onClick={() => {
                        void logout();
                      }}
                    >
                      {l("خروج", "Sign out")}
                    </Button>
                  ) : (
                    <Button onClick={() => setEditor(null)}>
                      {l("انصراف", "Cancel")}
                    </Button>
                  )}
                </Space>
              ) : null
            }
          >
            {editor === "appearance" ? (
              <Space direction="vertical" style={{ width: "100%" }}>
                <Select
                  value={mode}
                  onChange={setMode}
                  options={[
                    { value: "light", label: l("روشن", "Light") },
                    { value: "dark", label: l("تاریک", "Dark") },
                    { value: "system", label: l("هماهنگ با سیستم", "System") },
                  ]}
                  style={{ width: "100%" }}
                />
                <Select
                  value={lang}
                  onChange={setLang}
                  options={[
                    { value: "fa", label: "فارسی" },
                    { value: "en", label: "English" },
                  ]}
                  style={{ width: "100%" }}
                />
              </Space>
            ) : (
              <Form
                name="afra-editor"
                form={form}
                layout="vertical"
                requiredMark="optional"
              >
                {editor === "password" ? (
                  <>
                    <p>
                      {l(
                        "پس از تغییر گذرواژه، نشست‌ها بسته می‌شوند و باید دوباره وارد شوید.",
                        "Changing your password signs out all sessions. Sign in again afterward.",
                      )}
                    </p>
                    <Form.Item
                      name="current_password"
                      label={l("گذرواژه فعلی", "Current password")}
                      rules={[{ required: true }]}
                    >
                      <Input.Password autoComplete="current-password" />
                    </Form.Item>
                    <Form.Item
                      name="password"
                      label={l(
                        "گذرواژه جدید (حداقل ۱۲ نویسه)",
                        "New password (at least 12 characters)",
                      )}
                      rules={[{ required: true, min: 12, max: 128 }]}
                    >
                      <Input.Password autoComplete="new-password" />
                    </Form.Item>
                    <Form.Item
                      name="password_confirmation"
                      label={l("تکرار گذرواژه", "Confirm password")}
                      rules={[{ required: true }]}
                    >
                      <Input.Password autoComplete="new-password" />
                    </Form.Item>
                  </>
                ) : editor === "profile" ? (
                  <>
                    <Form.Item name="name" label={l("نام", "Name")}>
                      <Input />
                    </Form.Item>
                    <Form.Item name="mobile" label={l("موبایل", "Mobile")}>
                      <Input dir="ltr" />
                    </Form.Item>
                    <Form.Item name="bio" label={l("درباره من", "About me")}>
                      <Input.TextArea rows={4} />
                    </Form.Item>
                  </>
                ) : editor === "transition" ? (
                  <>
                    <Card
                      size="small"
                      title={selected?.title}
                      extra={
                        <Button
                          disabled={selected?.status !== "resolved"}
                          onClick={async () => {
                            try {
                              const d = await send(
                                base + "/defects/" + selected.id + "/retest",
                                {},
                              );
                              setRun(await api(base + "/runs/" + d.run_id));
                              setGroup("execution");
                              setTab("execution");
                              setEditor(null);
                            } catch (e) {
                              error(e);
                            }
                          }}
                        >
                          {l("تست مجدد", "Retest")}
                        </Button>
                      }
                    >
                      <p>{selected?.description}</p>
                      {JSON.parse(selected?.history || "[]").map(
                        (h: any, i: number) => (
                          <div className="history" key={i}>
                            <strong>{h.actor}</strong>
                            <p>{h.description}</p>
                            <small>{date(h.at)}</small>
                          </div>
                        ),
                      )}
                    </Card>
                    <Form.Item
                      name="status"
                      label={l("وضعیت جدید", "New status")}
                      rules={[{ required: true }]}
                    >
                      <Select
                        options={["open", "in_fix", "resolved", "closed"].map(
                          (v) => ({ value: v, label: v }),
                        )}
                      />
                    </Form.Item>
                    <Form.Item
                      name="force"
                      valuePropName="checked"
                      label={l(
                        "بستن اجباری بدون تأیید رفع",
                        "Force close without verification",
                      )}
                    >
                      <Switch />
                    </Form.Item>
                    <Form.Item
                      name="confirmed"
                      valuePropName="checked"
                      label={l(
                        "تأیید می‌کنم که رفع خطا تأیید نشده است",
                        "I confirm the fix has not been verified",
                      )}
                    >
                      <Switch />
                    </Form.Item>
                    <Form.Item
                      name="reason"
                      label={l(
                        "دلیل (حداقل ۱۰ نویسه)",
                        "Reason (at least 10 characters)",
                      )}
                      rules={[{ required: true, min: 10 }]}
                    >
                      <Input.TextArea rows={3} />
                    </Form.Item>
                  </>
                ) : editor === "result" ? (
                  <>
                    <Typography.Title level={4}>
                      {runItem?.snapshot.title}
                    </Typography.Title>
                    <p>{runItem?.snapshot.content.preconditions}</p>
                    <div className="execution-checklist">
                      {runItem?.snapshot.content.checklist.map(
                        (s: any, i: number) => (
                          <Card
                            size="small"
                            key={i}
                            className={"check-row " + checks[i]}
                          >
                            <div className="step-number">{i + 1}</div>
                            <div className="step-body">
                              <strong>{s.text}</strong>
                              <p>
                                <b>
                                  {l("نتیجه مورد انتظار:", "Expected result:")}
                                </b>{" "}
                                {s.expected}
                              </p>
                              <Tag>
                                {s.required
                                  ? l("الزامی", "Required")
                                  : l("اختیاری", "Optional")}
                              </Tag>
                            </div>
                            <Select
                              aria-label={l("نتیجه مورد", "Step result")}
                              value={checks[i]}
                              options={[
                                "not_run",
                                "pass",
                                "fail",
                                "skipped",
                              ].map((v) => ({ value: v, label: v }))}
                              onChange={(v) =>
                                setChecks((old) =>
                                  old.map((x, j) => (j === i ? v : x)),
                                )
                              }
                            />
                          </Card>
                        ),
                      )}
                    </div>
                    <Evidence
                      project={project}
                      entityType="run_item"
                      entityId={runItem?.id || ""}
                      language={lang}
                      userId={user.id}
                      selected={evidenceIds}
                      onChange={setEvidenceIds}
                    />
                    <Form.Item
                      name="outcome"
                      label={l("نتیجه نهایی", "Overall result")}
                      rules={[{ required: true }]}
                    >
                      <Select
                        options={["pass", "fail", "blocked", "skipped"].map(
                          (v) => ({ value: v, label: v }),
                        )}
                      />
                    </Form.Item>
                    <Form.Item
                      name="comment"
                      label={l("توضیح نتیجه", "Result comment")}
                    >
                      <Input.TextArea rows={3} />
                    </Form.Item>
                    <p>
                      {l(
                        "تغییر گزینه‌ها تا پیش از ذخیره، نتیجه‌ای در تاریخچه ثبت نمی‌کند.",
                        "Selections are not recorded until you press Save.",
                      )}
                    </p>
                  </>
                ) : editor === "cases" ? (
                  <>
                    <Form.Item
                      name="title"
                      label={l("عنوان تست کیس", "Test case title")}
                      rules={[{ required: true, max: 250 }]}
                    >
                      <Input />
                    </Form.Item>
                    <div className="form-grid">
                      <Form.Item name="section_id" label={l("بخش", "Section")}>
                        <Select
                          allowClear
                          options={sections.map((s) => ({
                            value: s.id,
                            label: s.name,
                          }))}
                        />
                      </Form.Item>
                      <Form.Item
                        name="assignee_id"
                        label={l("آزمایشگر مسئول", "Assigned tester")}
                      >
                        <MemberPicker
                          project={project}
                          activeOnly
                          disabled={!capabilities["cases.assign"]}
                        />
                      </Form.Item>
                      <Form.Item
                        name="substitute_id"
                        label={l("آزمایشگر جانشین", "Substitute tester")}
                      >
                        <MemberPicker
                          project={project}
                          activeOnly
                          disabled={!capabilities["cases.assign"]}
                        />
                      </Form.Item>
                      <Form.Item
                        name="priority"
                        label={l("اولویت", "Priority")}
                      >
                        <Select
                          options={["critical", "high", "medium", "low"].map(
                            (v) => ({ value: v, label: v }),
                          )}
                        />
                      </Form.Item>
                      <Form.Item
                        name={["content", "type"]}
                        label={l("نوع آزمون", "Test type")}
                      >
                        <Select
                          options={[
                            "functional",
                            "regression",
                            "smoke",
                            "security",
                            "performance",
                          ].map((v) => ({ value: v, label: v }))}
                        />
                      </Form.Item>
                      <Form.Item
                        name={["content", "estimate"]}
                        label={l("زمان تخمینی (دقیقه)", "Estimate (minutes)")}
                      >
                        <InputNumber min={1} max={1440} />
                      </Form.Item>
                    </div>
                    <Form.Item
                      name={["content", "preconditions"]}
                      label={l("پیش‌شرط‌ها", "Preconditions")}
                    >
                      <Input.TextArea rows={2} />
                    </Form.Item>
                    <Form.Item
                      name={["content", "expected"]}
                      label={l(
                        "نتیجه مورد انتظار کلی",
                        "Overall expected result",
                      )}
                    >
                      <Input.TextArea rows={2} />
                    </Form.Item>
                    <Form.Item
                      name={["content", "requirements"]}
                      label={l(
                        "نیازمندی‌های مرتبط و نسخه آن‌ها",
                        "Linked requirement revisions",
                      )}
                    >
                      <RequirementPicker project={project} language={lang} />
                    </Form.Item>
                    <Typography.Title level={5}>
                      {l("چک‌لیست آزمون", "Test checklist")}
                    </Typography.Title>
                    <Form.List name={["content", "checklist"]}>
                      {(fields, { add, remove }) => (
                        <>
                          {fields.map(({ key, name, ...rest }, i) => (
                            <Card
                              size="small"
                              className="check-editor"
                              key={key}
                              title={l("مورد ", "Step ") + (i + 1)}
                              extra={
                                fields.length > 1 && (
                                  <Button
                                    danger
                                    type="text"
                                    onClick={() => remove(name)}
                                  >
                                    {l("حذف", "Remove")}
                                  </Button>
                                )
                              }
                            >
                              <Form.Item
                                {...rest}
                                name={[name, "text"]}
                                label={l(
                                  "کاری که باید انجام شود",
                                  "Action to perform",
                                )}
                                rules={[{ required: true }]}
                              >
                                <Input.TextArea
                                  autoSize={{ minRows: 1, maxRows: 4 }}
                                />
                              </Form.Item>
                              <Form.Item
                                {...rest}
                                name={[name, "expected"]}
                                label={l(
                                  "نتیجه مورد انتظار همین مورد",
                                  "Expected result for this step",
                                )}
                                rules={[{ required: true }]}
                              >
                                <Input.TextArea
                                  autoSize={{ minRows: 1, maxRows: 4 }}
                                />
                              </Form.Item>
                              <Form.Item
                                {...rest}
                                name={[name, "required"]}
                                valuePropName="checked"
                                label={l("الزامی", "Required")}
                              >
                                <Switch />
                              </Form.Item>
                            </Card>
                          ))}
                          <Button
                            block
                            type="dashed"
                            icon={<PlusOutlined />}
                            onClick={() =>
                              add({ text: "", expected: "", required: true })
                            }
                          >
                            {l("افزودن مورد", "Add step")}
                          </Button>
                        </>
                      )}
                    </Form.List>
                  </>
                ) : editor === "section" ? (
                  <>
                    <Form.Item
                      name="name"
                      label={l("نام بخش", "Section name")}
                      rules={[{ required: true }]}
                    >
                      <Input />
                    </Form.Item>
                    <Form.Item
                      name="parent_id"
                      label={l("بخش والد", "Parent section")}
                    >
                      <Select
                        allowClear
                        options={sections.map((s) => ({
                          value: s.id,
                          label: s.name,
                        }))}
                      />
                    </Form.Item>
                  </>
                ) : editor === "project" ? (
                  <>
                    <Form.Item
                      name="name"
                      label={l("نام پروژه", "Project name")}
                      rules={[{ required: true }]}
                    >
                      <Input />
                    </Form.Item>
                    <Form.Item
                      name="description"
                      label={l("شرح پروژه", "Description")}
                    >
                      <Input.TextArea rows={4} />
                    </Form.Item>
                  </>
                ) : editor === "users" ? (
                  <>
                    <Form.Item
                      name="user_id"
                      label={l("شناسه کاربر", "User ID")}
                      rules={[{ required: true }]}
                    >
                      <InputNumber min={1} />
                    </Form.Item>
                    <Form.Item
                      name="role"
                      label={l("نقش پروژه", "Project role")}
                      rules={[{ required: true }]}
                    >
                      <Select
                        options={["admin", "tester", "reviewer", "viewer"].map(
                          (v) => ({ value: v, label: v }),
                        )}
                      />
                    </Form.Item>
                  </>
                ) : editor === "execution" ? (
                  <>
                    <Form.Item
                      name="title"
                      label={l("عنوان اجرا", "Run title")}
                      rules={[{ required: true }]}
                    >
                      <Input />
                    </Form.Item>
                    <p>
                      {l(
                        "تست کیس‌های انتخاب‌شده با نسخه فعلی تأییدشده در اجرا ثابت می‌شوند.",
                        "Selected cases are frozen at their currently approved revisions.",
                      )}
                    </p>
                  </>
                ) : editor === "defects" ? (
                  <>
                    <Form.Item
                      name="title"
                      label={l("عنوان خطا", "Defect title")}
                      rules={[{ required: true }]}
                    >
                      <Input />
                    </Form.Item>
                    <Form.Item
                      name="run_item_id"
                      label={l("شناسه مورد اجرای ناموفق", "Failed run item ID")}
                      rules={[{ required: true }]}
                    >
                      <Input dir="ltr" />
                    </Form.Item>
                    <Form.Item
                      name="severity"
                      label={l("شدت", "Severity")}
                      rules={[{ required: true }]}
                    >
                      <Select
                        options={["critical", "high", "medium", "low"].map(
                          (v) => ({ value: v, label: v }),
                        )}
                      />
                    </Form.Item>
                    <Form.Item
                      name="description"
                      label={l("شرح خطا", "Description")}
                      rules={[{ required: true }]}
                    >
                      <Input.TextArea rows={4} />
                    </Form.Item>
                  </>
                ) : (
                  <>
                    <Form.Item
                      name="title"
                      label={l("عنوان", "Title")}
                      rules={[{ required: true }]}
                    >
                      <Input />
                    </Form.Item>
                    <Form.Item
                      name={["content", "description"]}
                      label={l("شرح", "Description")}
                    >
                      <Input.TextArea rows={6} />
                    </Form.Item>
                  </>
                )}
              </Form>
            )}
          </Drawer>
        </Layout>
      )}
    </ConfigProvider>
  );
}
createRoot(document.getElementById("root")!).render(
  <React.StrictMode>
    <AntApp>
      <Application />
    </AntApp>
  </React.StrictMode>,
);
