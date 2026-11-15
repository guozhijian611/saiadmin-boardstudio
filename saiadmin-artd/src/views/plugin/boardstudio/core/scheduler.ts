export interface RefreshTask {
  id: string
  interval: number
  run: (signal: AbortSignal) => Promise<unknown>
}
interface Job extends RefreshTask {
  due: number
  failures: number
  running: boolean
}

/** One bounded queue per screen; completion schedules the next refresh. */
export class RefreshScheduler {
  private jobs: Job[] = []
  private active = new Set<AbortController>()
  private timer: ReturnType<typeof setTimeout> | undefined
  private epoch = 0
  private paused = false
  constructor(
    private readonly concurrency = 4,
    private readonly now = () => Date.now()
  ) {}

  replace(tasks: RefreshTask[]) {
    this.stop()
    this.jobs = tasks.map((task) => ({ ...task, due: this.now(), failures: 0, running: false }))
    this.pump()
  }
  pause(paused: boolean) {
    this.paused = paused
    if (this.timer) clearTimeout(this.timer)
    this.timer = undefined
    if (!paused) this.pump()
  }
  stop() {
    this.epoch++
    if (this.timer) clearTimeout(this.timer)
    this.timer = undefined
    this.jobs = []
    for (const controller of this.active) controller.abort()
    // Keep physical in-flight requests counted until they settle.
  }
  private pump() {
    if (this.paused) return
    if (this.timer) clearTimeout(this.timer)
    this.timer = undefined
    while (this.active.size < Math.max(1, this.concurrency)) {
      const job = this.jobs.find((item) => !item.running && item.due <= this.now())
      if (!job) break
      const epoch = this.epoch
      const controller = new AbortController()
      this.active.add(controller)
      job.running = true
      void Promise.resolve()
        .then(() => {
          if (!controller.signal.aborted) return job.run(controller.signal)
        })
        .then(
          () => {
            job.failures = 0
          },
          (error) => {
            if (error?.retryable === false) job.interval = Number.POSITIVE_INFINITY
            job.failures++
          }
        )
        .finally(() => {
          this.active.delete(controller)
          if (epoch === this.epoch) {
            job.running = false
            job.due = this.now() + Math.max(1000, job.interval) * Math.min(8, 2 ** job.failures)
          }
          this.pump()
        })
    }
    const pending = this.jobs.filter((item) => !item.running && Number.isFinite(item.due))
    if (pending.length && this.active.size < Math.max(1, this.concurrency)) {
      this.timer = setTimeout(
        () => this.pump(),
        Math.max(1, Math.min(...pending.map((item) => item.due)) - this.now())
      )
    }
  }
}
