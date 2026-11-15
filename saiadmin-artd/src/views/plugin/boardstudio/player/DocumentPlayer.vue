<template>
  <div class="document-player" ref="viewport">
    <div v-if="error" class="player-message"
      ><h2>{{ error }}</h2
      ><ElButton @click="load">重新加载</ElButton></div
    >
    <div v-else-if="!document" class="player-message">正在加载大屏…</div>
    <ParameterFilters v-if="document" :document="document" @apply="applyParameters" />
    <div
      v-if="document && !error"
      :style="{
        ...pageStyle(document),
        width: document.page.sizing === 'responsive' ? '100%' : document.page.width + 'px',
        transform:
          document.page.sizing === 'fixed'
            ? `translate(${offsetX}px, ${offsetY}px) scale(${scale})`
            : undefined,
        transformOrigin: 'top left'
      }"
    >
      <DocumentNode :document="document" :node-id="document.rootId" :results="results" />
    </div>
    <div v-if="lastError" class="data-warning"
      >{{ lastError }} <button @click="runtime.refresh()">重试</button
      ><button @click="load">重新加载发布版本</button></div
    >
  </div>
</template>
<script setup lang="ts">
  import { nextTick, computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef } from 'vue'
  import ParameterFilters from '../designer/ParameterFilters.vue'
  import { resolveDocumentBindings } from '../document/parameters'
  import DocumentNode from '../designer/DocumentNode.vue'
  import { createDataRuntime, type BindingResult } from '../document/data-runtime'
  import { pageStyle } from '../document/layout'
  import { validateDocument } from '../document/model'
  import type { BoardDocument } from '../document/types'
  import api from '../api/runtime'
  const props = defineProps<{ code: string; token?: string; authorization?: string }>()
  const document = shallowRef<BoardDocument>(),
    error = ref(''),
    viewport = ref<HTMLElement>(),
    results = reactive<Record<string, BindingResult>>({})
  const parameterValues = ref<Record<string, unknown>>({})
  const scale = ref(1),
    offsetX = ref(0),
    offsetY = ref(0)
  const lastError = computed(() => Object.values(results).find((r) => r.error)?.error || '')
  let releaseId = 0,
    observer: ResizeObserver | undefined,
    controller: AbortController | undefined
  const credentials = () => ({
    token: props.token || '',
    admin_authorization: props.authorization || ''
  })
  const runtime = createDataRuntime(
    async (binding, signal) =>
      api.request(
        '/app/boardstudio/api/document-data',
        {
          ...credentials(),
          code: props.code,
          releaseId,
          bindingId: binding.id,
          parameters: parameterValues.value
        },
        signal
      ),
    (id, result) => {
      results[id] = result
    }
  )
  async function load() {
    controller?.abort()
    controller = new AbortController()
    runtime.stop()
    error.value = ''
    try {
      const value = await api.request(
        `/app/boardstudio/api/document/${encodeURIComponent(props.code)}`,
        credentials(),
        controller.signal,
        true
      )
      validateDocument(value.document)
      document.value = value.document
      releaseId = value.releaseId
      runtime.replace(resolveDocumentBindings(value.document, parameterValues.value))
      await nextTick()
      resize()
    } catch (e) {
      error.value = (e as Error).message
    }
  }
  function applyParameters(values: Record<string, unknown>) {
    parameterValues.value = values
    if (document.value) runtime.replace(resolveDocumentBindings(document.value, values))
  }
  function resize() {
    if (!document.value || !viewport.value) return
    const { width, height: totalHeight } = viewport.value.getBoundingClientRect()
    const filterHeight =
      viewport.value.querySelector('.parameter-filters')?.getBoundingClientRect().height || 0
    const height = Math.max(1, totalHeight - filterHeight)
    scale.value = Math.min(width / document.value.page.width, height / document.value.page.height)
    offsetX.value = (width - document.value.page.width * scale.value) / 2
    offsetY.value = (height - document.value.page.height * scale.value) / 2
  }
  const visibility = () => runtime.pause(window.document.hidden)
  onMounted(() => {
    observer = new ResizeObserver(resize)
    if (viewport.value) observer.observe(viewport.value)
    window.document.addEventListener('visibilitychange', visibility)
    void load()
  })
  onBeforeUnmount(() => {
    controller?.abort()
    runtime.stop()
    observer?.disconnect()
    window.document.removeEventListener('visibilitychange', visibility)
  })
</script>
<style scoped>
  .document-player {
    position: fixed;
    inset: 0;
    overflow: hidden;
    background: #07111f;
  }
  .player-message {
    color: #fff;
    text-align: center;
    padding: 15vh 24px;
  }
  .data-warning {
    position: fixed;
    bottom: 12px;
    left: 12px;
    padding: 8px 14px;
    background: #793a22;
    color: white;
    border-radius: 6px;
    font-size: 12px;
  }
  .data-warning button {
    margin-left: 10px;
    color: #111;
  }
</style>
