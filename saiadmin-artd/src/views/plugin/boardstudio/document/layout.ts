import type { CSSProperties } from 'vue'
import type { BoardDocument, DocumentNode } from './types'

// Both designer and player use these exact style functions. CSS performs layout;
// selection/dragging reads resolved browser rectangles without persisting them.
export function containerStyle(node: DocumentNode): CSSProperties {
  const l = node.layout!
  const style: CSSProperties = {
    position: 'relative',
    boxSizing: 'border-box',
    width: '100%',
    height: '100%',
    minWidth: 0,
    minHeight: 0,
    overflow: 'hidden',
    padding: `${l.padding}px`
  }
  if (l.mode === 'free') return style
  return {
    ...style,
    display: l.mode,
    gap: `${l.gap}px`,
    ...(l.mode === 'flex'
      ? {
          flexDirection: l.direction,
          flexWrap: l.wrap ? 'wrap' : 'nowrap',
          alignItems: l.align === 'start' ? 'flex-start' : l.align === 'end' ? 'flex-end' : l.align,
          justifyContent:
            l.justify === 'start' ? 'flex-start' : l.justify === 'end' ? 'flex-end' : l.justify
        }
      : {
          gridTemplateColumns: `repeat(${l.columns}, minmax(0, 1fr))`,
          gridAutoRows: `${l.rowHeight}px`,
          alignItems: l.align,
          alignContent: 'start'
        })
  }
}
export function nodeStyle(node: DocumentNode, parent: DocumentNode): CSSProperties {
  const p = node.placement
  const a = node.appearance
  const base: CSSProperties = {
    boxSizing: 'border-box',
    minWidth: 0,
    minHeight: 0,
    overflow: 'hidden',
    background: a.background,
    color: a.color,
    border: `${a.borderWidth}px solid ${a.borderColor}`,
    borderRadius: `${a.borderRadius}px`,
    opacity: a.opacity,
    zIndex: p.zIndex,
    display: node.hidden ? 'none' : undefined
  }
  switch (parent.layout!.mode) {
    case 'free':
      return {
        ...base,
        position: 'absolute',
        left: `${p.x}px`,
        top: `${p.y}px`,
        width: `${p.width}px`,
        height: `${p.height}px`
      }
    case 'flex':
      return {
        ...base,
        position: 'relative',
        flexGrow: p.grow,
        flexShrink: p.shrink,
        flexBasis: p.basis === 'auto' ? 'auto' : `${p.basis}px`,
        width: `${p.width}px`,
        height:
          parent.layout!.direction === 'row' && parent.layout!.align === 'stretch'
            ? undefined
            : `${p.height}px`
      }
    case 'grid':
      return {
        ...base,
        position: 'relative',
        gridColumn: `span ${Math.min(p.columnSpan, parent.layout!.columns)}`,
        gridRow: `span ${p.rowSpan}`
      }
  }
}
export function pageStyle(document: BoardDocument): CSSProperties {
  return {
    width: document.page.sizing === 'fixed' ? `${document.page.width}px` : '100%',
    height: `${document.page.height}px`,
    background: document.page.background,
    position: 'relative',
    flexShrink: 0
  }
}
