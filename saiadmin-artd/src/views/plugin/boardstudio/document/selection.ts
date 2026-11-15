import { parentOf } from './model'
import type { BoardDocument } from './types'
import type { DocumentCommand } from './commands'
export type Alignment =
  | 'left'
  | 'center'
  | 'right'
  | 'top'
  | 'middle'
  | 'bottom'
  | 'horizontal'
  | 'vertical'
export function selectionRoots(document: BoardDocument, ids: string[]): string[] {
  const selected = new Set(ids.filter((id) => document.nodes[id] && id !== document.rootId))
  return [...selected].filter((id) => {
    let parent = parentOf(document, id)
    while (parent) {
      if (selected.has(parent.id)) return false
      parent = parentOf(document, parent.id)
    }
    return true
  })
}
export function alignSelection(
  document: BoardDocument,
  ids: string[],
  alignment: Alignment
): DocumentCommand {
  const nodes = selectionRoots(document, ids).map((id) => document.nodes[id])
  if (nodes.length < 2) throw new Error('至少选择两个节点')
  const parent = parentOf(document, nodes[0].id)!
  if (
    parent.layout?.mode !== 'free' ||
    nodes.some((n) => parentOf(document, n.id)?.id !== parent.id)
  )
    throw new Error('对齐仅支持同一个自由布局容器内的节点')
  const left = Math.min(...nodes.map((n) => n.placement.x)),
    top = Math.min(...nodes.map((n) => n.placement.y))
  const right = Math.max(...nodes.map((n) => n.placement.x + n.placement.width)),
    bottom = Math.max(...nodes.map((n) => n.placement.y + n.placement.height))
  let sorted = nodes,
    gap = 0,
    position = 0
  if (alignment === 'horizontal' || alignment === 'vertical') {
    if (nodes.length < 3) throw new Error('等间距至少选择三个节点')
    const horizontal = alignment === 'horizontal'
    sorted = [...nodes].sort((a, b) =>
      horizontal ? a.placement.x - b.placement.x : a.placement.y - b.placement.y
    )
    gap =
      ((horizontal ? right - left : bottom - top) -
        nodes.reduce((sum, n) => sum + (horizontal ? n.placement.width : n.placement.height), 0)) /
      (nodes.length - 1)
    position = horizontal ? left : top
  }
  const commands = sorted.map((node) => {
    const p = { ...node.placement }
    switch (alignment) {
      case 'left':
        p.x = left
        break
      case 'center':
        p.x = (left + right - p.width) / 2
        break
      case 'right':
        p.x = right - p.width
        break
      case 'top':
        p.y = top
        break
      case 'middle':
        p.y = (top + bottom - p.height) / 2
        break
      case 'bottom':
        p.y = bottom - p.height
        break
      case 'horizontal':
        p.x = position
        position += p.width + gap
        break
      case 'vertical':
        p.y = position
        position += p.height + gap
        break
    }
    return { type: 'patch' as const, id: node.id, patch: { placement: p } }
  })
  return { type: 'batch', commands }
}
