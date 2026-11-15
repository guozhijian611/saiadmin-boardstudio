export interface HistoryState {
  past: string[]
  future: string[]
  current: string
}

/** History operations are synchronous and independent of network requests. */
export function createHistory(state: HistoryState, limit = 50) {
  const reset = (snapshot: string) => {
    state.past.splice(0)
    state.future.splice(0)
    state.current = snapshot
  }
  const record = (snapshot: string) => {
    if (snapshot === state.current) return
    if (state.current) state.past.push(state.current)
    if (state.past.length > limit) state.past.shift()
    state.current = snapshot
    state.future.splice(0)
  }
  return {
    reset,
    record,
    undo(snapshot: string) {
      record(snapshot)
      const previous = state.past.pop()
      if (previous === undefined) return undefined
      state.future.push(state.current)
      state.current = previous
      return previous
    },
    redo(snapshot: string) {
      // An unrecorded edit invalidates the redo branch too.
      record(snapshot)
      const next = state.future.pop()
      if (next === undefined) return undefined
      state.past.push(state.current)
      state.current = next
      return next
    }
  }
}
