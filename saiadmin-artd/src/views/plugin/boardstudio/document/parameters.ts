import type { BoardDocument, DatasetBinding } from './types'

export function resolvePageParameters(
  document: BoardDocument,
  input: Record<string, unknown> = {}
) {
  for (const name of Object.keys(input))
    if (!Object.hasOwn(document.parameters, name)) throw new Error(`未声明的页面参数 ${name}`)
  const values: Record<string, unknown> = {}
  for (const [name, definition] of Object.entries(document.parameters)) {
    let value = Object.hasOwn(input, name) ? input[name] : definition.default
    if (definition.type === 'number') {
      if (
        !['number', 'string'].includes(typeof value) ||
        String(value).trim() === '' ||
        !Number.isFinite(Number(value))
      )
        throw new Error(`页面参数 ${name} 必须为数字`)
      value = Number(value)
    } else if (definition.type === 'boolean') {
      if (![true, false, 'true', 'false', 1, 0, '1', '0'].includes(value as string))
        throw new Error(`页面参数 ${name} 必须为布尔值`)
      value = [true, 'true', 1, '1'].includes(value as string)
    } else {
      if (typeof value !== 'string' || value.length > 1000)
        throw new Error(`页面参数 ${name} 必须为文本`)
    }
    values[name] = value
  }
  return values
}
export function resolveBinding(
  binding: DatasetBinding,
  values: Record<string, unknown>
): DatasetBinding {
  return {
    ...binding,
    params: Object.fromEntries(
      Object.entries(binding.params).map(([key, value]) => {
        if (
          value &&
          typeof value === 'object' &&
          !Array.isArray(value) &&
          Object.hasOwn(value, 'pageParameter')
        ) {
          const name = (value as { pageParameter: string }).pageParameter
          if (!Object.hasOwn(values, name)) throw new Error(`绑定引用了未定义页面参数 ${name}`)
          return [key, values[name]]
        }
        return [key, value]
      })
    )
  }
}
export function resolveDocumentBindings(
  document: BoardDocument,
  input: Record<string, unknown> = {}
): BoardDocument {
  const values = resolvePageParameters(document, input)
  return {
    ...document,
    bindings: Object.fromEntries(
      Object.entries(document.bindings).map(([id, binding]) => [
        id,
        resolveBinding(binding, values)
      ])
    )
  }
}
