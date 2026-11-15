import { createDocument, createNode } from './model'
import type { BoardDocument } from './types'

/** A nested layout fixture used by the built-in preset and regression tests. */
export function operationsDocument(): BoardDocument {
  const doc = createDocument(1440, 900)
  const root = doc.nodes.root
  root.layout = { ...root.layout!, mode: 'grid', columns: 3, rowHeight: 130, padding: 24, gap: 16 }
  const title = createNode('title', 'widget', 'decor-title')
  title.name = '运营数据驾驶舱'
  title.widget!.props = {
    text: '运营数据驾驶舱',
    subtitle: '订单 · 运输 · 服务状态',
    accent: '#67e8f9'
  }
  title.placement.columnSpan = 3
  const metrics = createNode('metrics', 'container')
  metrics.name = '核心指标区'
  metrics.layout = { ...metrics.layout!, mode: 'flex', padding: 0 }
  metrics.placement.columnSpan = 3
  const trend = createNode('trend', 'widget', 'art-line-chart')
  trend.name = '近七天订单'
  trend.placement.columnSpan = 2
  trend.placement.rowSpan = 3
  trend.widget!.props = { showAreaColor: true }
  const status = createNode('status', 'container')
  status.name = '服务状态面板'
  status.placement.rowSpan = 3
  status.appearance = {
    ...status.appearance,
    borderWidth: 1,
    borderRadius: 12,
    background: '#0d1d31'
  }
  const text = createNode('status_title', 'widget', 'decor-title')
  text.name = '状态标题'
  text.widget!.props = { text: '服务运行状态', subtitle: '服务健康概览', accent: '#67e8f9' }
  text.placement = { ...text.placement, x: 16, y: 16, width: 350, height: 70 }
  const matrix = createNode('status_matrix', 'widget', 'status-matrix')
  matrix.name = '服务健康'
  matrix.placement = { ...matrix.placement, x: 16, y: 100, width: 350, height: 280 }
  status.children = [text.id, matrix.id]
  for (const [index, label] of ['今日订单', '运输中', '已完成'].entries()) {
    const metric = createNode(`metric_${index}`, 'widget', 'stat-number')
    metric.name = label
    metric.placement.grow = 1
    metric.placement.basis = 0
    metric.widget!.props = { unit: '单' }
    metric.appearance = { ...metric.appearance, background: '#0d1d31', borderRadius: 12 }
    doc.nodes[metric.id] = metric
    metrics.children!.push(metric.id)
  }
  for (const node of [title, metrics, trend, status, text, matrix]) doc.nodes[node.id] = node
  root.children = [title.id, metrics.id, trend.id, status.id]
  return doc
}
