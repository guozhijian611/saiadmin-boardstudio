import type { Preset } from '../core/presets'
import { clone, importLegacyLayout, validateDocument } from './model'
import type { BoardDocument } from './types'

export function presetDocument(preset: Preset): BoardDocument {
  if (preset.type === 'screen' && preset.content.layout)
    return importLegacyLayout(clone(preset.content.layout))
  const components = clone(preset.content.components || [])
  const left = Math.min(0, ...components.map((c) => c.rect.x)),
    top = Math.min(0, ...components.map((c) => c.rect.y))
  for (const c of components) {
    c.rect.x -= left
    c.rect.y -= top
    c.dataset = {}
  }
  const width = Math.max(1, ...components.map((c) => c.rect.x + c.rect.w)),
    height = Math.max(1, ...components.map((c) => c.rect.y + c.rect.h))
  return importLegacyLayout({ canvas: { width, height }, components })
}
export function insertPreset(
  document: BoardDocument,
  parentId: string,
  preset: Preset,
  namespace: string
): BoardDocument {
  const next = clone(document),
    parent = next.nodes[parentId]
  if (!parent || parent.kind !== 'container') throw new Error('请选择容器')
  let current = parent
  while (current) {
    if (current.locked) throw new Error('容器已锁定')
    current = Object.values(next.nodes).find((n) => n.children?.includes(current.id))!
  }
  const source = presetDocument(preset)
  const ids = Object.fromEntries(
    Object.keys(source.nodes).map((id, i) => [id, `${namespace}_${i}`])
  )
  for (const node of Object.values(source.nodes)) {
    const copy = clone(node)
    copy.id = ids[node.id]
    if (next.nodes[copy.id]) throw new Error('节点 ID 冲突')
    if (copy.children) copy.children = copy.children.map((id) => ids[id])
    delete copy.binding
    next.nodes[copy.id] = copy
  }
  const group = next.nodes[ids[source.rootId]]
  group.name = preset.name
  group.placement = {
    ...group.placement,
    x: 24,
    y: 24,
    width: source.page.width,
    height: source.page.height,
    grow: 0,
    shrink: 1
  }
  group.appearance.background = 'transparent'
  parent.children!.push(group.id)
  validateDocument(next)
  return next
}

export function presetInsertion(
  document: BoardDocument,
  parentId: string,
  preset: Preset,
  namespace: string
) {
  const next = insertPreset(document, parentId, preset, namespace)
  const nodes = Object.fromEntries(Object.entries(next.nodes).filter(([id]) => !document.nodes[id]))
  const rootId = next.nodes[parentId].children!.at(-1)!
  return { type: 'insertTree' as const, parentId, rootId, nodes }
}
