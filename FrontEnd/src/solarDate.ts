import dayjs from "dayjs";
import jalaliday from "jalaliday/dayjs";
dayjs.extend(jalaliday);

export function normalizeDigits(value: string): string {
  return value.replace(/[۰-۹٠-٩]/g, (digit) => {
    const code = digit.charCodeAt(0);
    return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
  });
}

export function solarToCivil(value: string): string | null {
  const normalized = normalizeDigits(value).trim().replaceAll("/", "-");
  if (!/^\d{4}-\d{2}-\d{2}$/.test(normalized)) return null;
  const year = Number(normalized.slice(0, 4));
  if (year < 1200 || year > 1700) return null;
  const parsed = dayjs(normalized, { jalali: true });
  if (
    !parsed.isValid() ||
    parsed.calendar("jalali").format("YYYY-MM-DD") !== normalized
  )
    return null;
  return parsed.calendar("gregory").format("YYYY-MM-DD");
}

export function civilToSolar(value: string | null | undefined): string {
  return value ? dayjs(value).calendar("jalali").format("YYYY/MM/DD") : "";
}
