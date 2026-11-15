<template>
  <div class="runtime-page" :style="{ background: bgColor }">
    <div v-if="loading" class="runtime-state">加载中</div>
    <div v-else-if="error" class="runtime-state">{{ error }}</div>
    <div v-else ref="viewportRef" class="runtime-viewport">
      <div class="runtime-canvas" :style="canvasStyle">
        <div
          v-for="component in layout.components"
          :key="component.id"
          class="runtime-widget"
          :style="{
            left: component.rect.x + 'px',
            top: component.rect.y + 'px',
            width: component.rect.w + 'px',
            height: component.rect.h + 'px',
            zIndex: component.rect.z
          }"
        >
          <WidgetRenderer
            :component="component"
            :rows="dataMap[component.id]?.rows || []"
            :error="dataMap[component.id]?.error || ''"
            runtime
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { RefreshScheduler } from '../core/scheduler'
  import { LatestRequests } from '../core/latest'
  import api from '../api/runtime'
  import WidgetRenderer from '../widgets/WidgetRenderer.vue'
  import { resolveBoardFit } from '../widgets/fit'
  import { decorWidgetTypes } from '../widgets/registry'
  import {
    boardCanvasStyle,
    normalizeBgConfig,
    normalizeFitAlign,
    normalizeFitMode
  } from '../widgets/theme'
  import type { BoardComponent, BoardLayout } from '../widgets/types'

  const route = useRoute()
  const viewportRef = ref<HTMLElement>()
  const loading = ref(true)
  const error = ref('')
  const fit = reactive({ scaleX: 1, scaleY: 1, x: 0, y: 0 })
  const scheduler = new RefreshScheduler(4)
  const requests = new LatestRequests()
  let screenRequest: AbortController | undefined
  const props = defineProps<{
    publicCode?: string
    publicQuery?: Record<string, string>
    authorization?: string
  }>()
  let resizeObserver: ResizeObserver | undefined
  const screen = reactive<any>({ bg_config: normalizeBgConfig() })
  const layout = reactive<BoardLayout>({ canvas: { width: 1920, height: 1080 }, components: [] })
  const dataMap = reactive<Record<string, { rows: Record<string, any>[]; error: string }>>({})
  const minRefreshSeconds = 10
  const maxRefreshSeconds = 3600
  const reservedParamNames = new Set([
    'code',
    'cid',
    'token',
    'admin_preview',
    'draft',
    'admin_authorization'
  ])

  const code = computed(() => String(props.publicCode || route.params.code || ''))
  const query = computed(() => props.publicQuery || route.query)
  const token = computed(() => String(query.value.token || ''))
  const adminPreview = computed(() => query.value.admin_preview === '1')
  const draftPreview = computed(() => query.value.draft === '1')
  const runtimeParams = computed(() => {
    const params: Record<string, any> = {}
    for (const [key, value] of Object.entries(query.value)) {
      if (reservedParamNames.has(key)) continue
      params[key] = value
    }
    return params
  })
  const bgColor = computed(() => screen.bg_config?.color || '#07111f')
  const fitMode = computed(() => normalizeFitMode(screen.bg_config?.fit_mode))
  const fitAlign = computed(() => normalizeFitAlign(screen.bg_config?.fit_align))
  const canvasStyle = computed(() => ({
    ...boardCanvasStyle(screen.bg_config),
    width: layout.canvas.width + 'px',
    height: layout.canvas.height + 'px',
    left: fit.x + 'px',
    top: fit.y + 'px',
    transform: `scale(${fit.scaleX}, ${fit.scaleY})`
  }))
  const decorTypes = decorWidgetTypes
  const componentNeedsData = (component: BoardComponent) => !decorTypes.has(component.type)

  const loadScreen = async () => {
    screenRequest?.abort()
    const current = new AbortController()
    screenRequest = current
    scheduler.stop()
    requests.clear()
    for (const key of Object.keys(dataMap)) delete dataMap[key]
    loading.value = true
    error.value = ''
    try {
      const result = await api.screen(code.value, authParams(), current.signal)
      if (current.signal.aborted) return
      Object.assign(screen, result.screen, {
        bg_config: normalizeBgConfig(result.screen?.bg_config)
      })
      const nextLayout = result.screen.layout || {}
      Object.assign(layout.canvas, {
        width: Number(nextLayout.canvas?.width || result.screen.width || 1920),
        height: Number(nextLayout.canvas?.height || result.screen.height || 1080)
      })
      layout.components.splice(0, layout.components.length, ...(nextLayout.components || []))
      resetPolling()
    } catch (err: any) {
      if (!current.signal.aborted) error.value = err?.message || '大屏加载失败'
    } finally {
      if (!current.signal.aborted) {
        loading.value = false
        await nextTick()
        observeViewport()
        window.requestAnimationFrame(updateScale)
      }
    }
  }

  const loadComponentData = async (component: BoardComponent, signal: AbortSignal) => {
    const request = requests.start(component.id)
    const abort = () => request.signal.aborted || signal.aborted
    const onAbort = () => requests.clear()
    signal.addEventListener('abort', onAbort, { once: true })
    try {
      const result = await api.data(
        {
          ...runtimeParams.value,
          ...componentRuntimeParams(component),
          ...authParams(),
          code: code.value,
          cid: component.id
        },
        request.signal
      )
      if (abort() || !request.current()) return
      dataMap[component.id] = { rows: result.rows || [], error: '' }
    } catch (err: any) {
      if (abort() || !request.current()) return
      dataMap[component.id] = {
        rows: dataMap[component.id]?.rows || [],
        error: err?.message || '取数失败'
      }
      throw err
    } finally {
      signal.removeEventListener('abort', onAbort)
      request.finish()
    }
  }

  const resetPolling = () => {
    scheduler.replace(
      layout.components
        .filter(
          (component) =>
            componentNeedsData(component) && Number(component.dataset?.queryTemplateId) > 0
        )
        .map((component) => ({
          id: component.id,
          interval: normalizeRefresh(component.dataset?.refresh) * 1000,
          run: (signal) => loadComponentData(component, signal)
        }))
    )
    scheduler.pause(document.hidden)
  }
  const visibilityChanged = () => scheduler.pause(document.hidden)

  const authParams = () => ({
    ...(props.authorization ? { admin_authorization: props.authorization } : {}),
    ...(token.value ? { token: token.value } : {}),
    ...(adminPreview.value ? { admin_preview: 1 } : {}),
    ...(adminPreview.value && draftPreview.value ? { draft: 1 } : {})
  })

  const componentRuntimeParams = (component: BoardComponent) => {
    const params = component.dataset?.params
    if (!params || typeof params !== 'object' || Array.isArray(params)) return {}

    const result: Record<string, any> = {}
    for (const [key, value] of Object.entries(params)) {
      if (!reservedParamNames.has(key)) {
        result[key] = value
      }
    }

    return result
  }

  const updateScale = () => {
    const el = viewportRef.value
    if (!el) return
    const canvasWidth = Math.max(1, Number(layout.canvas.width || 1920))
    const canvasHeight = Math.max(1, Number(layout.canvas.height || 1080))
    const nextFit = resolveBoardFit({
      viewportWidth: el.clientWidth,
      viewportHeight: el.clientHeight,
      canvasWidth,
      canvasHeight,
      mode: fitMode.value,
      alignY: fitAlign.value
    })
    Object.assign(fit, nextFit)
  }

  const normalizeRefresh = (refresh: unknown) => {
    const seconds = Number(refresh || 30)
    if (!Number.isFinite(seconds) || seconds <= 0) return 30
    return Math.min(maxRefreshSeconds, Math.max(minRefreshSeconds, Math.round(seconds)))
  }

  const observeViewport = () => {
    resizeObserver?.disconnect()
    if (!viewportRef.value || !('ResizeObserver' in window)) return
    resizeObserver = new ResizeObserver(updateScale)
    resizeObserver.observe(viewportRef.value)
  }

  onMounted(() => {
    window.addEventListener('resize', updateScale)
    document.addEventListener('visibilitychange', visibilityChanged)
  })

  watch([code, token, adminPreview, draftPreview, runtimeParams], loadScreen, { immediate: true })

  watch(fitMode, () => {
    nextTick(updateScale)
  })

  watch(fitAlign, () => {
    nextTick(updateScale)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', updateScale)
    resizeObserver?.disconnect()
    screenRequest?.abort()
    requests.clear()
    scheduler.stop()
    document.removeEventListener('visibilitychange', visibilityChanged)
  })
</script>

<style scoped lang="scss">
  .runtime-page,
  .runtime-viewport {
    overflow: hidden;
  }

  .runtime-page {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
  }

  .runtime-state {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100vw;
    height: 100vh;
    color: #d7e7ff;
    background: #07111f;
  }

  .runtime-viewport {
    position: relative;
    width: 100%;
    height: 100%;
  }

  .runtime-canvas {
    position: absolute;
    overflow: hidden;
    transform-origin: left top;
  }

  .runtime-widget {
    position: absolute;
  }
</style>
