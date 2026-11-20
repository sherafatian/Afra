import React, { useEffect, useRef, useState } from "react";
import { App, Select } from "antd";
import { api } from "./api";
type Option = { value: number; label: string };
export function MemberPicker({
  project,
  source = "members",
  multiple = false,
  activeOnly = false,
  value,
  onChange,
  disabled = false,
  initialOptions = [],
  id,
  ...accessibility
}: {
  project: string;
  source?: "members" | "directory";
  multiple?: boolean;
  activeOnly?: boolean;
  value?: number | number[] | null;
  onChange?: (value: number | number[] | undefined) => void;
  disabled?: boolean;
  initialOptions?: Option[];
  id?: string;
  "aria-describedby"?: string;
  "aria-required"?: boolean;
  "aria-invalid"?: boolean;
}) {
  const { message } = App.useApp();
  const [options, setOptions] = useState<Option[]>(initialOptions);
  const [busy, setBusy] = useState(false);
  const counter = useRef(0);
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const cache = useRef(
    new Map(initialOptions.map((option) => [option.value, option])),
  );
  const search = async (query: string) => {
    const sequence = ++counter.current;
    if (source === "directory" && query.trim().length < 2) {
      setOptions([]);
      return;
    }
    setBusy(true);
    try {
      const path =
        source === "directory"
          ? `directory?q=${encodeURIComponent(query)}`
          : `members?per_page=100&active_only=${activeOnly ? 1 : 0}&q=${encodeURIComponent(query)}`;
      const result = await api(`v1/projects/${project}/${path}`);
      if (sequence !== counter.current) return;
      const rows = Array.isArray(result) ? result : result.data;
      const next = rows.map(
        (row: { id: number; name: string; email: string }) => ({
          value: row.id,
          label: row.name + " · " + row.email,
        }),
      );
      for (const option of next) cache.current.set(option.value, option);
      setOptions(next);
    } catch (error) {
      if (sequence === counter.current) message.error((error as Error).message);
    } finally {
      if (sequence === counter.current) setBusy(false);
    }
  };
  useEffect(() => {
    if (source === "members") void search("");
    return () => {
      counter.current++;
      if (timer.current) clearTimeout(timer.current);
    };
  }, [project, source, activeOnly]);
  const selected = Array.isArray(value) ? value : value == null ? [] : [value];
  useEffect(() => {
    const missing = selected.filter((id) => !cache.current.has(id));
    if (source !== "members" || !missing.length) return;
    let cancelled = false;
    api(`v1/projects/${project}/member-options`, {
      method: "POST",
      body: JSON.stringify({ ids: missing }),
    })
      .then((rows: { id: number; name: string; email: string }[]) => {
        if (cancelled) return;
        for (const row of rows)
          cache.current.set(row.id, {
            value: row.id,
            label: row.name + " · " + row.email,
          });
        setOptions((previous) => [...previous]);
      })
      .catch((error) => {
        if (!cancelled) message.error((error as Error).message);
      });
    return () => {
      cancelled = true;
    };
  }, [project, source, selected.join(",")]);
  const merged = new Map(options.map((option) => [option.value, option]));
  for (const id of selected) {
    if (!merged.has(id))
      merged.set(id, cache.current.get(id) || { value: id, label: "#" + id });
  }
  return (
    <Select
      id={id}
      {...accessibility}
      showSearch
      filterOption={false}
      mode={multiple ? "multiple" : undefined}
      value={value ?? undefined}
      disabled={disabled}
      loading={busy}
      options={[...merged.values()]}
      maxTagCount="responsive"
      style={{ width: "100%" }}
      allowClear
      onChange={(next) => onChange?.(next)}
      onSearch={(query) => {
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(() => {
          void search(query);
        }, 250);
      }}
    />
  );
}
