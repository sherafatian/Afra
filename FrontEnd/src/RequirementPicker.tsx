import { useEffect, useRef, useState } from "react";
import { App, Select } from "antd";
import { api } from "./api";
type Binding = { id: string; version: number };
type Props = {
  project: string;
  value?: Binding[];
  onChange?: (value: Binding[]) => void;
  language: "fa" | "en";
  id?: string;
};
export function RequirementPicker({
  project,
  value = [],
  onChange,
  language,
  ...props
}: Props) {
  const { message } = App.useApp();
  const [options, setOptions] = useState<
    { value: string; label: string; version: number }[]
  >([]);
  const generation = useRef(0);
  const search = async (query = "") => {
    const request = ++generation.current;
    try {
      const response = await api(
        `v1/projects/${project}/requirements?q=${encodeURIComponent(query)}&per_page=100`,
      );
      if (request === generation.current)
        setOptions((current) => {
          const map = new Map(current.map((row) => [row.value, row]));
          response.data.forEach((row: any) =>
            map.set(row.id, {
              value: row.id,
              label: `${row.title} · v${row.version}`,
              version: row.version,
            }),
          );
          return [...map.values()];
        });
    } catch (e) {
      if (request === generation.current) message.error((e as Error).message);
    }
  };
  useEffect(() => {
    setOptions([]);
    void search();
    return () => {
      generation.current++;
    };
  }, [project]);
  return (
    <Select
      {...props}
      mode="multiple"
      showSearch
      filterOption={false}
      allowClear
      value={value.map((row) => row.id)}
      options={[
        ...options.map((option) => {
          const pinned = value.find((row) => row.id === option.value);
          return pinned && pinned.version !== option.version
            ? {
                ...option,
                label:
                  option.label.replace(/v\d+$/, `v${pinned.version}`) +
                  (language === "fa"
                    ? " (نسخه تازه موجود است)"
                    : " (newer revision available)"),
              }
            : option;
        }),
        ...value
          .filter((row) => !options.some((option) => option.value === row.id))
          .map((row) => ({
            value: row.id,
            label: `${row.id} · v${row.version}`,
            version: row.version,
          })),
      ]}
      onSearch={search}
      onChange={(ids) =>
        onChange?.(
          ids.map(
            (id) =>
              value.find((row) => row.id === id) || {
                id,
                version: options.find((row) => row.value === id)!.version,
              },
          ),
        )
      }
      placeholder={
        language === "fa"
          ? "جست‌وجو و انتخاب نسخه نیازمندی"
          : "Search and select requirement revisions"
      }
    />
  );
}
