import React, { useMemo, useState } from "react";
import { Card, Input, Select, Space, Tooltip, Typography } from "antd";
import {
  CheckCircleFilled,
  CloseCircleFilled,
  BranchesOutlined,
} from "@ant-design/icons";
export type Effect = "inherit" | "allow" | "deny";
export type Permission = {
  key: string;
  fa: string;
  en: string;
  category: string | null;
};
export type Grants = Record<string, Effect>;
const domains: Record<string, string> = {
  project: "پروژه",
  cases: "تست کیس",
  sections: "بخش‌ها",
  shared: "مراحل مشترک",
  templates: "قالب‌ها",
  datasets: "داده‌های آزمون",
  views: "نماهای ذخیره‌شده",
  requirements: "نیازمندی‌ها",
  plans: "برنامه آزمون",
  profiles: "پیکربندی اجرا",
  runs: "اجرا",
  defects: "خطاها",
  releases: "انتشار",
  environments: "محیط",
  builds: "ساخت",
  reports: "گزارش",
  attachments: "شواهد",
  comments: "گفتگو",
  members: "اعضای پروژه",
  roles: "نقش‌های سفارشی",
  groups: "گروه‌ها",
  grants: "مجوز مستقیم",
  section_access: "دسترسی بخش",
  access: "مدیریت دسترسی",
  fields: "فیلد سفارشی",
};
const domainsEn: Record<string, string> = {
  project: "Project",
  cases: "Test cases",
  sections: "Sections",
  shared: "Shared steps",
  templates: "Templates",
  datasets: "Test data",
  views: "Saved views",
  requirements: "Requirements",
  plans: "Test plans",
  profiles: "Execution profiles",
  runs: "Execution",
  defects: "Defects",
  releases: "Releases",
  environments: "Environments",
  builds: "Builds",
  reports: "Reports",
  attachments: "Evidence",
  comments: "Discussion",
  members: "Project members",
  roles: "Custom roles",
  groups: "Groups",
  grants: "Direct grants",
  section_access: "Section access",
  access: "Access management",
  fields: "Custom fields",
};
export function EffectIcon({
  effect,
  label,
}: {
  effect: Effect;
  label: string;
}) {
  return (
    <Tooltip title={label}>
      <span
        className={"permission-icon " + effect}
        role="img"
        aria-label={label}
      >
        {effect === "allow" ? (
          <CheckCircleFilled />
        ) : effect === "deny" ? (
          <CloseCircleFilled />
        ) : (
          <BranchesOutlined />
        )}
      </span>
    </Tooltip>
  );
}
export function PermissionEditor({
  catalog,
  value,
  onChange,
  language,
  disabled = false,
  grantable,
  restorable,
}: {
  catalog: Permission[];
  value: Grants;
  onChange: (value: Grants) => void;
  language: "fa" | "en";
  disabled?: boolean;
  grantable?: Record<string, boolean>;
  restorable?: Record<string, boolean>;
}) {
  const [search, setSearch] = useState("");
  const fa = language === "fa";
  const effects = [
    { value: "inherit", label: fa ? "از نقش و گروه" : "Inherited" },
    { value: "allow", label: fa ? "مجاز" : "Allowed" },
    { value: "deny", label: fa ? "غیرمجاز" : "Denied" },
  ];
  const groups = useMemo(() => {
    const result: Record<string, Permission[]> = {};
    const query = search.trim().toLocaleLowerCase();
    for (const permission of catalog) {
      if (
        query &&
        ![permission.key, permission.fa, permission.en].some((text) =>
          text.toLocaleLowerCase().includes(query),
        )
      )
        continue;
      const domain = permission.key.split(".")[0];
      (result[domain] ??= []).push(permission);
    }
    return Object.entries(result);
  }, [catalog, search]);
  return (
    <div>
      <Typography.Paragraph type="secondary">
        {fa
          ? "منع صریح در نقش، گروه یا مجوز مستقیم بر اجازه مقدم است. فقط اختیارهای مؤثر خود را می‌توانید واگذار کنید."
          : "Explicit role, group and direct denies take precedence. You may delegate only your own effective authority."}
      </Typography.Paragraph>
      <Input.Search
        allowClear
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder={fa ? "جست‌وجوی مجوزها" : "Search permissions"}
        style={{ marginBottom: 16 }}
      />
      {groups.map(([domain, permissions]) => (
        <Card
          size="small"
          className="permission-domain"
          key={domain}
          title={fa ? domains[domain] || domain : domainsEn[domain] || domain}
        >
          <div className="permission-grid">
            {permissions.map((permission) => {
              const effect = value[permission.key] || "inherit";
              const label = effects.find(
                (item) => item.value === effect,
              )!.label;
              return (
                <div className="permission-row" key={permission.key}>
                  <EffectIcon effect={effect} label={label} />
                  <div className="permission-label">
                    <strong>{fa ? permission.fa : permission.en}</strong>
                    <small dir="ltr">{permission.key}</small>
                  </div>
                  <Select<Effect>
                    aria-label={
                      (fa ? "دسترسی: " : "Access: ") +
                      (fa ? permission.fa : permission.en)
                    }
                    value={effect}
                    disabled={disabled}
                    options={effects.map((option) => ({
                      ...option,
                      disabled:
                        option.value === "allow"
                          ? grantable?.[permission.key] === false ||
                            (effect === "deny" &&
                              restorable?.[permission.key] === false)
                          : option.value === "inherit" &&
                            effect === "deny" &&
                            restorable?.[permission.key] === false,
                    }))}
                    onChange={(next) =>
                      onChange({ ...value, [permission.key]: next })
                    }
                  />
                </div>
              );
            })}
          </div>
        </Card>
      ))}
      {!groups.length && (
        <Typography.Text type="secondary">
          {fa ? "مجوزی مطابق جست‌وجو پیدا نشد." : "No matching permissions."}
        </Typography.Text>
      )}
    </div>
  );
}
