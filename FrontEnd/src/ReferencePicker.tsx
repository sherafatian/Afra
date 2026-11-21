import { useEffect, useRef, useState } from "react";
import { App, Select } from "antd";
import { api, send } from "./api";
type Props = {
  project: string;
  kind: string;
  value?: string | string[];
  onChange?: (value: string | string[] | undefined) => void;
  multiple?: boolean;
  id?: string;
  disabled?: boolean;
  placeholder?: string;
};
export function ReferencePicker({
  project,
  kind,
  value,
  onChange,
  multiple,
  id,
  disabled,
  ...props
}: Props) {
  const { message } = App.useApp();
  const [query, setQuery] = useState("");
  const [options, setOptions] = useState<{ value: string; label: string }[]>(
    [],
  );
  const [busy, setBusy] = useState(false);
  const request = useRef(0);
  const labelRequest = useRef(0);
  const ids = Array.isArray(value) ? value : value ? [value] : [];
  const idsKey = ids.join(",");
  const selection = useRef(ids);
  selection.current = ids;
  useEffect(() => {
    const generation = ++request.current;
    const timer = setTimeout(async () => {
      setBusy(true);
      try {
        const path =
          kind === "cases"
            ? "/cases"
            : kind === "requirements"
              ? "/requirements"
              : "/records/" + kind;
        const result = await api(
          `v1/projects/${project}${path}?per_page=100&q=${encodeURIComponent(query)}&summary=1`,
        );
        if (generation === request.current)
          setOptions((previous) => [
            ...result.data.map((row: any) => ({
              value: row.id,
              label: row.title,
            })),
            ...previous.filter(
              (row) =>
                selection.current.includes(row.value) &&
                !result.data.some((item: any) => item.id === row.value),
            ),
          ]);
      } catch (e) {
        if (generation === request.current) message.error((e as Error).message);
      } finally {
        if (generation === request.current) setBusy(false);
      }
    }, 200);
    return () => {
      clearTimeout(timer);
      request.current++;
    };
  }, [project, kind, query]);
  useEffect(() => {
    const generation = ++labelRequest.current;
    if (ids.length)
      void send<any[]>(`v1/projects/${project}/reference-options`, {
        kind,
        ids,
      })
        .then((rows) => {
          if (generation !== labelRequest.current) return;
          setOptions((previous) => {
            const map = new Map(previous.map((row) => [row.value, row]));
            rows.forEach((row) =>
              map.set(row.id, { value: row.id, label: row.title }),
            );
            return [...map.values()];
          });
        })
        .catch(() => {});
    return () => {
      labelRequest.current++;
    };
  }, [project, kind, idsKey]);
  return (
    <Select
      {...props}
      id={id}
      disabled={disabled}
      mode={multiple ? "multiple" : undefined}
      showSearch
      filterOption={false}
      allowClear
      loading={busy}
      value={value}
      options={options}
      onSearch={setQuery}
      onChange={onChange}
    />
  );
}
