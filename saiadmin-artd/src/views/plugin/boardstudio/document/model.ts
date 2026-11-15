import type { BoardLayout } from '../widgets/types'
import type {
  BoardDocument,
  ContainerLayout,
  DocumentNode,
  LayoutMode,
  NodePlacement
} from './types'

export const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value))
export const placement = (): NodePlacement => ({
  x: 24,
  y: 24,
  width: 320,
  height: 180,
  zIndex: 1,
  grow: 0,
  shrink: 1,
  basis: 'auto',
  columnSpan: 1,
  rowSpan: 1
})
export const containerLayout = (mode: LayoutMode = 'free'): ContainerLayout => ({
  mode,
  direction: 'row',
  gap: 16,
  padding: 16,
  align: 'stretch',
  justify: 'start',
  wrap: false,
  columns: 3,
  rowHeight: 180
})
export function createNode(
  id: string,
  kind: DocumentNode['kind'],
  type = 'stat-number'
): DocumentNode {
  return {
    id,
    name: kind === 'container' ? '容器' : '组件',
    kind,
    hidden: false,
    locked: false,
    placement: placement(),
    appearance: {
      background: 'transparent',
      color: '#e5efff',
      borderColor: '#294666',
      borderWidth: 0,
      borderRadius: 0,
      opacity: 1
    },
    ...(kind === 'container'
      ? { layout: containerLayout(), children: [] }
      : { widget: { type, version: 1, props: {} } })
  }
}
export function createDocument(width = 1920, height = 1080): BoardDocument {
  const root = createNode('root', 'container')
  root.name = '页面'
  root.layout!.padding = 0
  root.placement = { ...placement(), x: 0, y: 0, width, height }
  return {
    schemaVersion: 2,
    page: { width, height, sizing: 'fixed', background: '#07111f' },
    rootId: root.id,
    nodes: { [root.id]: root },
    bindings: {},
    parameters: {}
  }
}

export function parentOf(doc: BoardDocument, id: string): DocumentNode | undefined {
  return Object.values(doc.nodes).find((node) => node.children?.includes(id))
}
export function descendants(doc: BoardDocument, id: string): string[] {
  return [id, ...(doc.nodes[id]?.children || []).flatMap((child) => descendants(doc, child))]
}
const finite = (v: unknown, min: number, max: number) =>
  typeof v === 'number' && Number.isFinite(v) && v >= min && v <= max
const assert = (condition: unknown, message: string): void => {
  if (!condition) throw new Error(message)
}

export function validateDocument(value: unknown): asserts value is BoardDocument {
  assert(value && typeof value === 'object', '文档格式不正确')
  const doc = value as BoardDocument
  assert(JSON.stringify(doc).length <= 2 * 1024 * 1024, '文档不能超过 2 MB')
  assert(doc.schemaVersion === 2, '不支持的文档版本')
  assert(
    doc.page && finite(doc.page.width, 1, 16384) && finite(doc.page.height, 1, 16384),
    '画布尺寸不合法'
  )
  assert(['fixed', 'responsive'].includes(doc.page.sizing), '页面尺寸模式不合法')
  assert(
    doc.nodes && !Array.isArray(doc.nodes) && Object.keys(doc.nodes).length <= 200,
    '最多支持 200 个节点'
  )
  assert(doc.nodes[doc.rootId]?.kind === 'container', '页面必须有根容器')
  assert(
    doc.bindings &&
      !Array.isArray(doc.bindings) &&
      typeof doc.bindings === 'object' &&
      doc.parameters &&
      !Array.isArray(doc.parameters) &&
      typeof doc.parameters === 'object',
    '文档缺少绑定或参数定义'
  )
  const visited = new Set<string>()
  function visit(id: string, depth: number) {
    assert(depth <= 12, '容器嵌套不能超过 12 层')
    assert(!visited.has(id), '节点不能循环引用或属于多个容器')
    visited.add(id)
    const node = doc.nodes[id]
    assert(node && node.id === id && /^[A-Za-z0-9_-]{1,80}$/.test(id), '节点 ID 不合法')
    assert(['container', 'widget'].includes(node.kind), '节点类型不合法')
    assert(typeof node.name === 'string' && node.name.length <= 120, '节点名称不合法')
    assert(typeof node.hidden === 'boolean' && typeof node.locked === 'boolean', '节点状态不合法')
    const p = node.placement
    assert(p && finite(p.x, -100000, 100000) && finite(p.y, -100000, 100000), '节点位置不合法')
    assert(finite(p.width, 1, 16384) && finite(p.height, 1, 16384), '节点尺寸不合法')
    assert(
      finite(p.zIndex, 0, 100000) && finite(p.grow, 0, 100) && finite(p.shrink, 0, 100),
      '节点层级或占比不合法'
    )
    assert(p.basis === 'auto' || finite(p.basis, 0, 16384), '弹性基准尺寸不合法')
    assert(
      Number.isInteger(p.columnSpan) &&
        finite(p.columnSpan, 1, 24) &&
        Number.isInteger(p.rowSpan) &&
        finite(p.rowSpan, 1, 100),
      '网格跨度不合法'
    )
    const appearance = node.appearance
    assert(
      appearance &&
        finite(appearance.opacity, 0, 1) &&
        finite(appearance.borderWidth, 0, 100) &&
        finite(appearance.borderRadius, 0, 1000),
      '节点外观不合法'
    )
    for (const key of ['background', 'color', 'borderColor'] as const)
      assert(
        typeof appearance[key] === 'string' &&
          appearance[key].length <= 128 &&
          !/url\s*\(|[;{}]/i.test(appearance[key]),
        '颜色格式不合法'
      )
    if (node.kind === 'container') {
      const l = node.layout
      assert(
        l && ['free', 'flex', 'grid'].includes(l.mode) && Array.isArray(node.children),
        '容器布局不合法'
      )
      assert(!node.widget && !node.binding, '容器不能包含图表配置或数据绑定')
      assert(
        ['row', 'column'].includes(l!.direction) &&
          ['start', 'center', 'end', 'stretch'].includes(l!.align) &&
          ['start', 'center', 'end', 'space-between'].includes(l!.justify),
        '容器对齐不合法'
      )
      assert(
        finite(l!.gap, 0, 1000) &&
          finite(l!.padding, 0, 1000) &&
          Number.isInteger(l!.columns) &&
          finite(l!.columns, 1, 24) &&
          finite(l!.rowHeight, 1, 16384) &&
          typeof l!.wrap === 'boolean',
        '容器间距或网格不合法'
      )
      for (const child of node.children!) visit(child, depth + 1)
    } else {
      assert(
        !node.children &&
          !node.layout &&
          node.widget &&
          typeof node.widget.type === 'string' &&
          node.widget.version === 1,
        '组件定义不合法'
      )
      assert(
        node.widget!.props &&
          typeof node.widget!.props === 'object' &&
          !Array.isArray(node.widget!.props),
        '组件属性不合法'
      )
      if (node.binding) assert(doc.bindings[node.binding.instanceId], '组件引用了不存在的数据绑定')
    }
  }
  visit(doc.rootId, 0)
  assert(visited.size === Object.keys(doc.nodes).length, '文档包含游离节点')
  assert(Object.keys(doc.parameters).length <= 32, '最多支持 32 个页面参数')
  for (const [name, parameter] of Object.entries(doc.parameters)) {
    assert(
      /^[A-Za-z][A-Za-z0-9_]{0,39}$/.test(name) &&
        parameter &&
        typeof parameter === 'object' &&
        ['string', 'number', 'boolean'].includes(parameter.type),
      '页面参数定义不合法'
    )
    assert(
      typeof parameter.default === parameter.type &&
        (parameter.type !== 'number' || Number.isFinite(parameter.default)) &&
        (parameter.type !== 'string' || String(parameter.default).length <= 1000),
      '页面参数默认值不合法'
    )
  }
  for (const [id, binding] of Object.entries(doc.bindings)) {
    assert(
      binding.id === id &&
        /^[A-Za-z0-9_-]{1,80}$/.test(id) &&
        Number.isInteger(binding.datasetId) &&
        binding.datasetId > 0,
      '数据绑定不合法'
    )
    assert(
      binding.params && typeof binding.params === 'object' && !Array.isArray(binding.params),
      '绑定参数不合法'
    )
    for (const value of Object.values(binding.params)) {
      if (value && typeof value === 'object')
        assert(
          !Array.isArray(value) &&
            Object.keys(value).length === 1 &&
            doc.parameters[(value as { pageParameter: string }).pageParameter],
          '绑定页面参数引用不合法'
        )
    }
    assert(
      binding.refresh &&
        ['manual', 'interval'].includes(binding.refresh.mode) &&
        finite(binding.refresh.seconds, 10, 3600),
      '刷新策略不合法'
    )
  }
}

/** Explicit import: legacy data is copied, never edited in place. */
export function importLegacyLayout(layout: BoardLayout): BoardDocument {
  const doc = createDocument(layout.canvas.width, layout.canvas.height)
  doc.page.background = layout.bg_config?.color || doc.page.background
  const instances = new Map<string, string>()
  for (const component of layout.components) {
    if (component.id === doc.rootId || doc.nodes[component.id])
      throw new Error('旧布局的组件 ID 冲突')
    const node = createNode(component.id, 'widget', component.type)
    node.name = component.title || component.type
    node.placement = {
      ...node.placement,
      x: component.rect.x,
      y: component.rect.y,
      width: component.rect.w,
      height: component.rect.h,
      zIndex: component.rect.z
    }
    node.widget!.props = clone(component.option || {})
    const dataset = component.dataset
    if (dataset?.queryTemplateId) {
      const params = clone(dataset.params || {})
      const key = JSON.stringify([dataset.queryTemplateId, params, dataset.refresh || 30])
      let instanceId = instances.get(key)
      if (!instanceId) {
        instanceId = `binding_${instances.size + 1}`
        instances.set(key, instanceId)
        doc.bindings[instanceId] = {
          id: instanceId,
          datasetId: dataset.queryTemplateId,
          params,
          refresh: {
            mode: 'interval',
            seconds: Math.min(3600, Math.max(10, dataset.refresh || 30))
          }
        }
      }
      node.binding = { instanceId, mapping: clone(dataset.mapping || {}) }
    }
    doc.nodes[node.id] = node
    doc.nodes[doc.rootId].children!.push(node.id)
  }
  validateDocument(doc)
  return doc
}
