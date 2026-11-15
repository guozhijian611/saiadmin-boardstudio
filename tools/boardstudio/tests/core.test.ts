import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createHistory } from '../../../saiadmin-artd/src/views/plugin/boardstudio/core/history'
import { LatestRequests } from '../../../saiadmin-artd/src/views/plugin/boardstudio/core/latest'
import { RefreshScheduler } from '../../../saiadmin-artd/src/views/plugin/boardstudio/core/scheduler'

const deferred = () => {
  let resolve!: () => void
  const promise = new Promise<void>(done => { resolve = done })
  return { promise, resolve }
}
const flush = () => new Promise<void>(resolve => setImmediate(resolve))

test('undo records the last unsaved edit and diverging edits invalidate redo', () => {
  const state = { past: [] as string[], future: [] as string[], current: '' }
  const history = createHistory(state, 2)
  history.reset('initial')
  history.record('first')
  assert.equal(history.undo('second'), 'first')
  assert.equal(history.undo('first'), 'initial')
  assert.equal(history.redo('initial'), 'first')
  assert.equal(history.redo('diverged'), undefined)
  assert.equal(state.current, 'diverged')
  history.record('third'); history.record('fourth')
  assert.equal(state.past.length, 2)
})

test('latest request rejects stale success, stale errors and completion after disposal', () => {
  const requests = new LatestRequests()
  const first = requests.start('widget')
  const second = requests.start('widget')
  assert.equal(first.signal.aborted, true)
  first.finish()
  assert.equal(second.current(), true)
  requests.clear()
  assert.equal(second.current(), false)
  assert.equal(second.signal.aborted, true)
})

test('scheduler enforces a global concurrency limit across screen replacement', async () => {
  const scheduler = new RefreshScheduler(2)
  const pending = [deferred(), deferred(), deferred()]
  const started: number[] = []
  const signals: AbortSignal[] = []
  scheduler.replace(pending.map((item, id) => ({ id: String(id), interval: 60000,
    run: async signal => { started.push(id); signals.push(signal); await item.promise }
  })))
  await flush()
  assert.deepEqual(started, [0, 1])
  let replacementRuns = 0
  scheduler.replace([{ id: 'new', interval: 60000, run: async () => { replacementRuns++ } }])
  assert.equal(signals.every(signal => signal.aborted), true)
  await flush()
  assert.equal(replacementRuns, 0, 'old physical requests still count toward concurrency')
  pending[0].resolve()
  await flush()
  assert.equal(replacementRuns, 1)
  assert.deepEqual(started, [0, 1], 'old queued widget must never start')
  pending[1].resolve()
  scheduler.stop()
})

test('paused scheduler starts no queued work and resumes on visibility', async () => {
  const scheduler = new RefreshScheduler(1)
  let runs = 0
  scheduler.pause(true)
  scheduler.replace([{ id: 'a', interval: 60000, run: async () => { runs++ } }])
  await flush()
  assert.equal(runs, 0)
  scheduler.pause(false)
  await flush()
  assert.equal(runs, 1)
  scheduler.stop()
})

test('presets are local, searchable and contain no remote data bindings', async () => {
  const { presets, filterPresets } = await import('../../../saiadmin-artd/src/views/plugin/boardstudio/core/presets')
  assert.ok(filterPresets('screen', '运营').length > 0)
  assert.ok(filterPresets('component').length > 0)
  assert.equal(filterPresets('screen', 'missing-preset').length, 0)
  assert.equal(new Set(presets.map(item => item.id)).size, presets.length)
  for (const preset of presets) {
    const components = preset.content.layout?.components || preset.content.components || []
    assert.equal(components.length, preset.component_count)
    for (const component of components) assert.ok(!component.dataset?.queryTemplateId)
  }
})

test('preset insertion preserves shared offsets, stacking and fits a small canvas', async () => {
  const { presets, placePresetComponents } = await import('../../../saiadmin-artd/src/views/plugin/boardstudio/core/presets')
  const source = presets.find(item => item.name === '服务健康状态矩阵')!.content.components!
  const original = JSON.stringify(source)
  const placed = placePresetComponents(source, {width: 1920, height: 1080}, 12)
  assert.equal(placed[1].rect.x - placed[0].rect.x, 24)
  assert.equal(placed[1].rect.y - placed[0].rect.y, 28)
  assert.equal(placed[1].rect.z - placed[0].rect.z, 1)
  assert.equal(placed[0].rect.z, 13)
  const small = placePresetComponents(source, {width: 280, height: 180}, 0)
  assert.equal(small[1].rect.x - small[0].rect.x, 12)
  assert.equal(small[1].rect.y - small[0].rect.y, 14)
  for (const {rect} of small) {
    assert.ok(rect.x >= 0 && rect.y >= 0)
    assert.ok(rect.x + rect.w <= 280 && rect.y + rect.h <= 180)
  }
  assert.equal(JSON.stringify(source), original)
})

test('scheduler stops terminal failures until explicit refresh',async()=>{
 const scheduler=new RefreshScheduler(1)
 let calls=0
 scheduler.replace([{id:'auth',interval:1,run:async()=>{calls++;throw Object.assign(new Error('forbidden'),{retryable:false})}}])
 await flush()
 assert.equal(calls,1)
 await new Promise(resolve=>setTimeout(resolve,1100))
 assert.equal(calls,1)
 scheduler.replace([{id:'auth',interval:Infinity,run:async()=>{calls++}}])
 await flush();assert.equal(calls,2)
 scheduler.stop()
})


test('scheduler delays a transient retry then resets backoff on recovery',async()=>{
 const scheduler=new RefreshScheduler(1)
 let runs=0
 scheduler.replace([{id:'transient',interval:1000,run:async()=>{runs++;if(runs===1)throw new Error('offline')}}])
 await flush()
 await new Promise(resolve=>setTimeout(resolve,1100))
 assert.equal(runs,1)
 await new Promise(resolve=>setTimeout(resolve,1100))
 assert.equal(runs,2)
 await new Promise(resolve=>setTimeout(resolve,1000))
 assert.equal(runs,3)
 scheduler.stop()
})
