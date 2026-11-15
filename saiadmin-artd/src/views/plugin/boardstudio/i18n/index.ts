import { useI18n, type Composer } from 'vue-i18n'
import catalog from './catalog.json'

const translations: Record<string, string> = catalog
const registered = new WeakSet<object>()
const keyOf = (source: string) => {
  let hash = 2166136261
  for (const char of source) hash = Math.imul(hash ^ char.charCodeAt(0), 16777619)
  return `m${(hash >>> 0).toString(16)}`
}
const parameterized = Object.keys(translations)
  .filter((source) => /\{p\d+\}/.test(source))
  .map((source) => {
    const slots: string[] = []
    const parts = source.split(/(\{p\d+\})/g)
    const pattern = parts
      .map((part) => {
        if (/^\{p\d+\}$/.test(part)) {
          slots.push(part.slice(1, -1))
          return '(.*?)'
        }
        return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
      })
      .join('')
    return { source, slots, pattern: new RegExp('^' + pattern + '$', 's') }
  })
export function registerBoardMessages(composer: Composer) {
  if (registered.has(composer)) return
  const keys = new Set<string>()
  for (const locale of ['zh', 'en']) {
    const messages: Record<string, any> = {}
    for (const [source, english] of Object.entries(translations)) {
      const key = keyOf(source)
      if (locale === 'zh') {
        if (keys.has(key)) throw new Error('Board Studio translation key collision')
        keys.add(key)
      }
      const value = locale === 'zh' ? source : english
      // Functions keep JSON examples literal; only named pN slots interpolate.
      messages[key] = (ctx: { named: (name: string) => unknown }) =>
        value.replace(/\{(p\d+)\}/g, (_match, name) => String(ctx.named(name) ?? ''))
    }
    composer.mergeLocaleMessage(locale, {
      boardstudio: messages,
      menus: {
        boardstudio:
          locale === 'zh'
            ? {
                root: 'Board Studio',
                screens: '大屏管理',
                presets: '预设模板',
                designer: 'V2 设计器',
                datasets: '数据集',
                sources: '数据源管理',
                queries: '查询模板',
                editor: '大屏编辑器'
              }
            : {
                root: 'Board Studio',
                screens: 'Screens',
                presets: 'Presets',
                designer: 'V2 designer',
                datasets: 'Datasets',
                sources: 'Data sources',
                queries: 'Query templates',
                editor: 'Screen editor'
              }
      }
    })
  }
  registered.add(composer)
}
export function boardTranslator(composer: Composer) {
  registerBoardMessages(composer)
  return (source: any, params: Record<string, unknown> = {}): any => {
    // Read locale during rendering, including labels stored in metadata objects.
    void composer.locale.value
    if (typeof source !== 'string') return source
    if (!(source in translations)) {
      for (const entry of parameterized) {
        const match = source.match(entry.pattern)
        if (match) {
          const values = Object.fromEntries(entry.slots.map((slot, i) => [slot, match[i + 1]]))
          return composer.t(`boardstudio.${keyOf(entry.source)}`, values, {
            locale: composer.locale.value
          })
        }
      }
      return source
    }
    return composer.t(`boardstudio.${keyOf(source)}`, params, { locale: composer.locale.value })
  }
}
export function useBoardI18n() {
  const composer = useI18n({ useScope: 'global' })
  const bt = boardTranslator(composer)
  const localizedOptions = (options: any[]) =>
    options.map((option) =>
      typeof option === 'string' ? bt(option) : { ...option, label: bt(option.label) }
    )
  const dateCell = (_row: unknown, _column: unknown, value: unknown) => {
    if (typeof value !== 'string' || !value) return value ? String(value) : '—'
    const date = new Date(value.replace(' ', 'T'))
    return Number.isNaN(date.getTime())
      ? value
      : new Intl.DateTimeFormat(composer.locale.value, {
          dateStyle: 'medium',
          timeStyle: 'short'
        }).format(date)
  }
  return {
    bt,
    localizedOptions,
    dateCell,
    locale: composer.locale,
    number: composer.n,
    date: composer.d
  }
}
