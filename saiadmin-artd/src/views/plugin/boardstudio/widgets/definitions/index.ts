import { widgetRegistry } from '../registry'

export interface PropertyDefinition {
  key: string
  label: string
  type: 'string' | 'number' | 'boolean' | 'select'
  min?: number
  max?: number
  options?: Array<{ label: string; value: string }>
}
export interface WidgetDefinition {
  type: string
  version: number
  name: string
  defaults: Record<string, unknown>
  properties: PropertyDefinition[]
}
const labels: Record<string, string> = {
  showLegend: '显示图例',
  showAreaColor: '填充面积',
  positiveField: '正向字段',
  negativeField: '负向字段',
  positiveName: '正向名称',
  negativeName: '负向名称',
  showDataLabel: '显示数值',
  barWidth: '柱宽',
  timeField: '时间字段',
  openField: '开盘字段',
  closeField: '收盘字段',
  highField: '最高字段',
  lowField: '最低字段',
  showDataZoom: '显示缩放',
  dataZoomStart: '缩放起点',
  dataZoomEnd: '缩放终点',
  name: '显示名称',
  min: '最小值',
  max: '最大值',
  unit: '单位',
  decimals: '小数位数',
  showPointer: '显示指针',
  showProgress: '显示进度',
  showLabel: '显示标签',
  legendPosition: '图例位置',
  sort: '排序',
  minSize: '最小尺寸',
  maxSize: '最大尺寸',
  gap: '间隔',
  xField: 'X 字段',
  yField: 'Y 字段',
  showVisualMap: '显示色标',
  showAxisLabel: '显示坐标标签',
  showAxisLine: '显示坐标轴',
  showSplitLine: '显示分隔线',
  prefix: '前缀',
  showIndex: '显示序号',
  rowStripe: '隔行底色',
  maxRows: '最大行数',
  labelField: '名称字段',
  valueField: '数值字段',
  targetField: '目标字段',
  statusField: '状态字段',
  sortOrder: '排序',
  showRank: '显示排名',
  showValue: '显示数值',
  accent: '主题色',
  groupField: '分组字段',
  columns: '列数',
  showStatus: '显示状态',
  imageField: '图片字段',
  titleField: '标题字段',
  interval: '轮播间隔（毫秒）',
  imageFit: '图片适配',
  showDots: '显示指示点',
  contentField: '内容字段',
  showTime: '显示时间',
  showContent: '显示内容',
  levelField: '级别字段',
  lngField: '经度字段',
  latField: '纬度字段',
  nameField: '名称字段',
  region: '地图区域',
  pointSize: '点位大小',
  borderStyle: '边框样式',
  opacity: '透明度',
  variant: '样式',
  secondary: '辅助色',
  speed: '动画周期（秒）',
  thickness: '线宽',
  glow: '发光',
  direction: '方向',
  text: '标题文字',
  subtitle: '副标题',
  align: '文字对齐'
}
const choices: Record<string, string[]> = {
  legendPosition: ['top', 'bottom', 'left', 'right'],
  sort: ['ascending', 'descending', 'none'],
  sortOrder: ['asc', 'desc'],
  imageFit: ['cover', 'contain', 'fill'],
  direction: ['horizontal', 'vertical'],
  align: ['left', 'center', 'right'],
  borderStyle: ['corner', 'solid', 'dash'],
  region: ['china']
}
const bounds: Record<string, [number, number]> = {
  opacity: [0, 1],
  decimals: [0, 8],
  dataZoomStart: [0, 100],
  dataZoomEnd: [0, 100],
  columns: [1, 20],
  interval: [500, 3600000],
  maxRows: [0, 5000],
  speed: [0.1, 3600],
  thickness: [1, 50],
  pointSize: [1, 100],
  barWidth: [1, 300]
}

// Pure widget metadata shared by property editing and validation.
// Rendering remains in WidgetRenderer until each renderer migrates independently.
export const widgetDefinitions: WidgetDefinition[] = widgetRegistry.map((meta) => ({
  type: meta.type,
  version: 1,
  name: meta.name,
  defaults: { ...meta.defaultOption },
  properties: Object.entries(meta.defaultOption || {}).map(([key, value]) => ({
    key,
    label: labels[key] || key,
    type: choices[key]
      ? 'select'
      : typeof value === 'boolean'
        ? 'boolean'
        : typeof value === 'number' || value === undefined
          ? 'number'
          : 'string',
    ...(choices[key] ? { options: choices[key].map((value) => ({ label: value, value })) } : {}),
    ...(bounds[key] ? { min: bounds[key][0], max: bounds[key][1] } : {})
  }))
}))
export const widgetDefinition = (type: string) =>
  widgetDefinitions.find((item) => item.type === type)
export function validateWidgetProperties(type: string, props: Record<string, unknown>) {
  const definition = widgetDefinition(type)
  if (!definition) throw new Error('未知组件类型')
  for (const property of definition.properties) {
    const value = props[property.key]
    if (value === undefined || value === null) continue
    if (
      property.type === 'number' &&
      (typeof value !== 'number' ||
        !Number.isFinite(value) ||
        (property.min !== undefined && value < property.min) ||
        (property.max !== undefined && value > property.max))
    )
      throw new Error(`${property.label}数值不合法`)
    if (property.type === 'boolean' && typeof value !== 'boolean')
      throw new Error(`${property.label}必须是开关值`)
    if (property.type === 'string' && (typeof value !== 'string' || value.length > 2000))
      throw new Error(`${property.label}内容不合法`)
    if (property.type === 'select' && !property.options?.some((option) => option.value === value))
      throw new Error(`${property.label}选项不合法`)
  }
}
