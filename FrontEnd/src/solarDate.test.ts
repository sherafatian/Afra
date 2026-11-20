import { describe, expect, it } from "vitest";
import { civilToSolar, solarToCivil } from "./solarDate";
describe("Solar Hijri account date boundary", () => {
  it("converts Nowruz and accepts Persian/Arabic digits", () => {
    expect(solarToCivil("۱۴۰۴/۰۱/۰۱")).toBe("2025-03-21");
    expect(solarToCivil("١٤٠٤/٠١/٠١")).toBe("2025-03-21");
    expect(civilToSolar("2025-03-21")).toBe("1404/01/01");
  });
  it("rejects overflowing days, non-leap Esfand and civil years", () => {
    expect(solarToCivil("1404/12/30")).toBeNull();
    expect(solarToCivil("1404/07/31")).toBeNull();
    expect(solarToCivil("2026/01/01")).toBeNull();
    expect(solarToCivil("1404/13/01")).toBeNull();
  });
  it("accepts leap Esfand and empty display", () => {
    expect(solarToCivil("1403/12/30")).toBe("2025-03-20");
    expect(civilToSolar(null)).toBe("");
  });
});
