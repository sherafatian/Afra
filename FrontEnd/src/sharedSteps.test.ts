import { describe, expect, it } from "vitest";
import { detachShared, type SharedBinding } from "./sharedSteps";
describe("shared step detachment", () => {
  it("preserves preceding and following blocks at the same boundary", () => {
    const blocks: SharedBinding[] = ["before", "chosen", "after"].map((id) => ({
      id,
      version: 1,
      position: 0,
    }));
    const source = { checklist: ["local"], shared_blocks: blocks };
    const result = detachShared(source, blocks[1], ["shared1", "shared2"]);
    expect(result.checklist).toEqual(["shared1", "shared2", "local"]);
    expect(
      result.shared_blocks.map((item) => [item.id, item.position]),
    ).toEqual([
      ["before", 0],
      ["after", 2],
    ]);
    expect(source.checklist).toEqual(["local"]);
    expect(source.shared_blocks).toHaveLength(3);
  });
  it("shifts later boundaries while leaving earlier boundaries unchanged", () => {
    const blocks: SharedBinding[] = [
      { id: "early", version: 1, position: 0 },
      { id: "chosen", version: 2, position: 1 },
      { id: "late", version: 3, position: 2 },
    ];
    const result = detachShared(
      { checklist: ["a", "b"], shared_blocks: blocks },
      blocks[1],
      ["inserted"],
    );
    expect(result.checklist).toEqual(["a", "inserted", "b"]);
    expect(result.shared_blocks.map((item) => item.position)).toEqual([0, 3]);
  });
});
