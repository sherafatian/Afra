export type SharedBinding = { id: string; version: number; position: number };
/** Preserve the exact combined order, including multiple blocks at one local boundary. */
export function detachShared(
  content: {
    checklist?: any[];
    shared_blocks?: SharedBinding[];
    [key: string]: any;
  },
  binding: SharedBinding,
  steps: any[],
) {
  const checklist = [...(content.checklist || [])];
  checklist.splice(binding.position, 0, ...steps);
  const blocks = content.shared_blocks || [];
  const detachedIndex = blocks.findIndex((item) => item.id === binding.id);
  return {
    ...content,
    checklist,
    shared_blocks: blocks.flatMap((item, index) =>
      item.id === binding.id
        ? []
        : [
            {
              ...item,
              position:
                item.position > binding.position ||
                (item.position === binding.position && index > detachedIndex)
                  ? item.position + steps.length
                  : item.position,
            },
          ],
    ),
  };
}
