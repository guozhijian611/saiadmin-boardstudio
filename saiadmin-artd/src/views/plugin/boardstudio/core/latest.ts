/** Abort superseded work and prevent stale results from becoming visible. */
export class LatestRequests {
  private requests = new Map<string, AbortController>()
  start(key: string) {
    this.requests.get(key)?.abort()
    const controller = new AbortController()
    this.requests.set(key, controller)
    return {
      signal: controller.signal,
      current: () => !controller.signal.aborted && this.requests.get(key) === controller,
      finish: () => {
        if (this.requests.get(key) === controller) this.requests.delete(key)
      }
    }
  }
  clear() {
    for (const controller of this.requests.values()) controller.abort()
    this.requests.clear()
  }
}
