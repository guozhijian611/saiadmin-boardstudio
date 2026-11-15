import { RefreshScheduler } from '../core/scheduler'
import type { BoardDocument, DatasetBinding } from './types'

export interface BindingResult {
  rows: Record<string, unknown>[]
  fields?: Array<{ name: string; type: string }>
  error: string
  stale: boolean
  fetchedAt?: string
  loading: boolean
}
const stable = (value: unknown): string => {
  if (Array.isArray(value)) return `[${value.map(stable).join(',')}]`
  if (value && typeof value === 'object')
    return `{${Object.keys(value)
      .sort()
      .map((key) => `${JSON.stringify(key)}:${stable((value as Record<string, unknown>)[key])}`)
      .join(',')}}`
  return JSON.stringify(value)
}
export function bindingGroups(document: BoardDocument) {
  const used = new Set<string>()
  const visit = (id: string) => {
    const node = document.nodes[id]
    if (node.hidden) return
    if (node.binding) used.add(node.binding.instanceId)
    for (const child of node.children || []) visit(child)
  }
  visit(document.rootId)
  const groups = new Map<string, DatasetBinding[]>()
  for (const binding of Object.values(document.bindings)) {
    if (!used.has(binding.id)) continue
    const key = stable([binding.datasetId, binding.datasetVersion ?? null, binding.params])
    groups.set(key, [...(groups.get(key) || []), binding])
  }
  return groups
}
export function createDataRuntime(
  execute: (
    binding: DatasetBinding,
    signal: AbortSignal
  ) => Promise<Omit<BindingResult, 'loading' | 'error' | 'stale'>>,
  update: (id: string, result: BindingResult) => void
) {
  const scheduler = new RefreshScheduler(4)
  let generation = 0
  let current: BoardDocument
  const values = new Map<string, BindingResult>()
  const identities = new Map<string, string>()
  function replace(document: BoardDocument) {
    current = document
    const epoch = ++generation
    const groups = bindingGroups(document)
    const active = new Set<string>()
    for (const [key, bindings] of groups)
      for (const binding of bindings) {
        active.add(binding.id)
        if (identities.get(binding.id) !== key) {
          const empty = { rows: [], error: '', stale: false, loading: true }
          values.set(binding.id, empty)
          update(binding.id, empty)
        }
        identities.set(binding.id, key)
      }
    for (const id of identities.keys())
      if (!active.has(id)) {
        identities.delete(id)
        values.delete(id)
        update(id, { rows: [], error: '', stale: false, loading: false })
      }
    scheduler.replace(
      [...groups].map(([key, bindings]) => ({
        id: key,
        interval: bindings.some((b) => b.refresh.mode === 'interval')
          ? Math.min(
              ...bindings.filter((b) => b.refresh.mode === 'interval').map((b) => b.refresh.seconds)
            ) * 1000
          : Number.POSITIVE_INFINITY,
        run: async (signal) => {
          const publish = (result: BindingResult) => {
            for (const binding of bindings) {
              values.set(binding.id, result)
              update(binding.id, result)
            }
          }
          publish({
            ...values.get(bindings[0].id),
            rows: values.get(bindings[0].id)?.rows || [],
            error: '',
            stale: values.get(bindings[0].id)?.stale || false,
            loading: true
          })
          try {
            const result = await execute(bindings[0], signal)
            if (signal.aborted || epoch !== generation) return
            publish({ ...result, error: '', stale: false, loading: false })
          } catch (error) {
            if (signal.aborted || epoch !== generation) return
            const code = Number(
              (error as { code?: number; status?: number })?.code ||
                (error as { status?: number })?.status
            )
            const revoked = [401, 403, 404, 409].includes(code)
            if (revoked) Object.assign(error as object, { retryable: false })
            publish({
              ...values.get(bindings[0].id),
              rows: revoked ? [] : values.get(bindings[0].id)?.rows || [],
              error: (error as Error).message,
              stale: !revoked,
              loading: false
            })
            throw error
          }
        }
      }))
    )
  }
  return {
    replace,
    refresh: () => replace(current),
    pause: (paused: boolean) => scheduler.pause(paused),
    stop: () => {
      generation++
      scheduler.stop()
    }
  }
}
