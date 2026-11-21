import { describe, expect, it } from "vitest";
import { buildQualityPaper, type Report } from "./QualityPaper";
describe("Quality report paper", () => {
  const fixture: Report = {
    title: "<script>unsafe</script>",
    prepared_at: "2026-11-21T10:00:00Z",
    summary: {
      total_items: 60,
      unique_cases: 30,
      required_items: 40,
      optional_items: 20,
      required_pass_rate: 50,
      counts: { pass: 20 },
      required_counts: { pass: 20 },
      items: Array.from({ length: 60 }, (_, index) => ({
        id: String(index),
        title: "Case " + index,
        case_revision: 1,
        required: index < 40,
        configuration: { name: "Desktop" },
        data: { row: 0 },
        outcome: "pass",
        actor_name: '<img onerror="attack">',
        is_retest: false,
      })),
      cases: [],
    },
  };
  it("includes every report item, not only the visible page", () => {
    const html = buildQualityPaper(fixture, "en", "https://afra.example");
    expect(html).toContain("Case 59");
    expect(html.match(/<tr><td>/g)?.length).toBe(60);
    expect(html).toContain("A4 landscape");
    expect(html).toContain("display:table-header-group");
  });
  it("escapes untrusted titles and actor names", () => {
    const html = buildQualityPaper(fixture, "fa", "https://afra.example");
    expect(html).not.toContain("<script>unsafe");
    expect(html).not.toContain("<img onerror");
    expect(html).toContain("&lt;script&gt;unsafe");
    expect(html).toContain('dir="rtl"');
  });
});
