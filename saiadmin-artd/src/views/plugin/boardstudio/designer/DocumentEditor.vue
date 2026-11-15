<template>
  <section class="document-editor">
    <header>
      <slot name="publish" :dirty="dirty" />
      <PageParameters :document="document" @command="execute" />
      <ElButton size="small" @click="presetsVisible = true">{{ bt('预设模板') }}</ElButton>
      <ElButton size="small" @click="exportVisible = true">{{ bt('导出文档') }}</ElButton>
      <ElButton size="small" @click="importVisible = true">{{ bt('导入文档') }}</ElButton>
      <ElDialog v-model="exportVisible" :title="bt('导出 V2 文档')" width="720px">
        <p>{{ bt('包含布局和数据集引用，不含连接凭据。跨环境导入后需重新核对数据集 ID。') }}</p>
        <ElInput
          :model-value="JSON.stringify(document, null, 2)"
          type="textarea"
          :rows="16"
          readonly
          :aria-label="bt('导出的 V2 文档')"
        />
        <template #footer
          ><ElButton @click="exportDocument">{{ bt('下载 JSON') }}</ElButton></template
        >
      </ElDialog>
      <ElDialog v-model="importVisible" :title="bt('导入 V2 文档')" width="720px">
        <p>{{ bt('粘贴文档 JSON 或选择本地文件；应用前校验，替换后可以撤销。') }}</p>
        <ElInput
          v-model="importText"
          type="textarea"
          :rows="16"
          :aria-label="bt('待导入的 V2 文档')"
        />
        <template #footer
          ><ElButton @click="importInput?.click()">{{ bt('选择 JSON 文件') }}</ElButton
          ><ElButton type="primary" @click="applyImport(importText)">{{
            bt('校验并导入')
          }}</ElButton></template
        >
      </ElDialog>
      <input
        ref="importInput"
        type="file"
        accept="application/json,.json"
        hidden
        @change="importDocument"
      />
      <ElDialog v-model="presetsVisible" :title="bt('V2 预设模板')" width="min(1100px,95vw)"
        ><PresetGallery @select="applyPreset"
      /></ElDialog>
      <strong>{{ name || 'Board Studio V2' }}</strong>
      <ElTag size="small" :type="dirty ? 'warning' : 'success'">{{
        dirty ? bt('未保存') : bt('已保存')
      }}</ElTag>
      <ElButton size="small" :disabled="!canUndo" @click="travel('undo')">{{
        bt('撤销')
      }}</ElButton>
      <ElButton size="small" :disabled="!canRedo" @click="travel('redo')">{{
        bt('重做')
      }}</ElButton>
      <ElButton size="small" :disabled="isRoot" @click="duplicate">{{ bt('复制节点') }}</ElButton>
      <ElButton size="small" :disabled="isRoot" @click="removeSelected">{{ bt('删除') }}</ElButton>
      <label class="zoom"
        >{{ bt('缩放')
        }}<ElInputNumber v-model="zoom" :min="25" :max="150" :step="10" size="small" /> %</label
      >
      <ElButton size="small" @click="preview = !preview">{{
        preview ? bt('返回设计') : bt('预览布局')
      }}</ElButton>
      <ElButton size="small" :loading="saving" type="primary" @click="save">{{
        bt('保存文档')
      }}</ElButton>
    </header>
    <div v-if="!preview" class="selection-tools">
      <span v-if="selectedIds.length > 1">{{
        bt('已选择 {p0} 个节点', { p0: selectedIds.length })
      }}</span>
      <span v-else>{{ bt('Shift 多选 · 容器空白处拖动框选') }}</span>
      <ElButton
        v-for="(label, mode) in alignments"
        :key="mode"
        :disabled="selectedIds.length < 2"
        size="small"
        @click="align(mode)"
        >{{ bt(label) }}</ElButton
      >
    </div>
    <ParameterFilters :document="document" @apply="pageValues = $event" />
    <div class="editor-body">
      <aside v-if="!preview" class="left-panel">
        <h3>{{ bt('添加内容') }}</h3>
        <div class="insert-buttons"
          ><ElButton
            v-for="(label, mode) in modes"
            :key="mode"
            size="small"
            @click="insertContainer(mode)"
            >{{ bt(label) }}</ElButton
          ></div
        >
        <ElSelect
          v-model="widgetType"
          :placeholder="bt('选择组件')"
          :aria-label="bt('组件类型')"
          size="small"
          filterable
        >
          <ElOption
            v-for="widget in widgetRegistry"
            :key="widget.type"
            :label="bt(widget.name)"
            :value="widget.type"
          />
        </ElSelect>
        <ElButton size="small" @click="insertWidget">{{ bt('插入组件') }}</ElButton>
        <p>{{ bt('选中容器后插入其内部；选中组件则插入其所在容器。') }}</p>
        <h3>{{ bt('页面结构') }}</h3>
        <NodeTree
          :document="document"
          :node-id="document.rootId"
          :selected-id="selectedId"
          :selected-ids="selectedIds"
          @select="selectNode"
          @move="(id, parentId, index) => execute({ type: 'move', id, parentId, index })"
        />
      </aside>
      <main ref="viewport" class="canvas-viewport" @pointerdown.self="selectNode(document.rootId)">
        <div
          class="canvas-size"
          :style="{
            width: document.page.width * scale + 'px',
            height: document.page.height * scale + 'px'
          }"
        >
          <div
            class="canvas-page"
            :style="{
              ...pageStyle(document),
              transform: `scale(${scale})`,
              width:
                document.page.sizing === 'responsive'
                  ? availableWidth / scale + 'px'
                  : document.page.width + 'px'
            }"
          >
            <DocumentNode
              :document="document"
              :node-id="document.rootId"
              :selected-id="selectedId"
              :selected-ids="selectedIds"
              :editable="!preview"
              :results="results"
              @select="selectNode"
              @node-pointer="startDrag"
              @node-resize="startResize"
              @container-pointer="startMarquee"
            />
          </div>
        </div>
        <div
          v-if="marquee"
          class="marquee"
          :style="{
            left: marquee.x + 'px',
            top: marquee.y + 'px',
            width: marquee.width + 'px',
            height: marquee.height + 'px'
          }"
        />
      </main>
      <aside v-if="!preview" class="right-panel"
        ><section v-if="selectedIds.length > 1" class="multi-inspector">
          <h3>{{ bt('批量操作 · {p0} 个节点', { p0: selectedIds.length }) }}</h3>
          <p>{{
            bt('对齐和等距适用于同一个自由布局容器；复制、删除、隐藏和锁定可以跨容器操作。')
          }}</p>
          <ElButton size="small" @click="batchFlag('hidden', true)">{{ bt('隐藏所选') }}</ElButton>
          <ElButton size="small" @click="batchFlag('hidden', false)">{{ bt('显示所选') }}</ElButton>
          <ElButton size="small" @click="batchFlag('locked', true)">{{ bt('锁定所选') }}</ElButton>
          <ElButton size="small" @click="batchFlag('locked', false)">{{
            bt('解锁所选')
          }}</ElButton> </section
        ><DocumentInspector
          v-else
          :document="document"
          :node-id="selectedId"
          @command="execute" /><DataBindingPanel
          v-if="selectedIds.length < 2 && selected.kind === 'widget'"
          :document="document"
          :node="selected"
          :result="selected.binding ? results[selected.binding.instanceId] : undefined"
          @command="execute"
          @refresh="dataRuntime.refresh()"
      /></aside>
    </div>
    <footer
      >{{
        bt('{p0} 个节点 · {p1} × {p2} · {p3}', {
          p0: nodeCount,
          p1: document.page.width,
          p2: document.page.height,
          p3: selected.name
        })
      }}<span v-if="preview">{{ bt('预览使用与设计区相同的布局和渲染器') }}</span></footer
    >
  </section>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch, reactive } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { BoardDocument, LayoutMode } from '../document/types'
  import { createNode, descendants, parentOf } from '../document/model'
  import { createDocumentSession, type DocumentCommand } from '../document/commands'
  import { selectionRoots, alignSelection, type Alignment } from '../document/selection'
  import { pageStyle } from '../document/layout'
  import { widgetRegistry, createDefaultComponent } from '../widgets/registry'
  import DocumentNode from './DocumentNode.vue'
  import DocumentInspector from './DocumentInspector.vue'
  import NodeTree from './NodeTree.vue'
  import PageParameters from './PageParameters.vue'
  import ParameterFilters from './ParameterFilters.vue'
  import { resolveDocumentBindings } from '../document/parameters'
  import PresetGallery from '../presets/PresetGallery.vue'
  import type { Preset } from '../core/presets'
  import { presetDocument, presetInsertion } from '../document/presets'
  import { validateDocument } from '../document/model'
  import DataBindingPanel from './DataBindingPanel.vue'
  import datasetApi from '../dataset/api'
  import { createDataRuntime, bindingGroups, type BindingResult } from '../document/data-runtime'
  const props = defineProps<{
    initialDocument: BoardDocument
    name?: string
    initiallyDirty?: boolean
    onSave: (document: BoardDocument) => Promise<void>
  }>()
  const exportVisible = ref(false),
    importVisible = ref(false),
    importText = ref('')
  const presetsVisible = ref(false),
    importInput = ref<HTMLInputElement>()
  async function applyPreset(preset: Preset) {
    try {
      if (preset.type === 'screen')
        await ElMessageBox.confirm(
          bt('套用预设会替换当前文档，可通过撤销恢复。'),
          bt('套用大屏预设')
        )
      const parent =
        selected.value.kind === 'container'
          ? selected.value.id
          : parentOf(document.value, selected.value.id)!.id
      execute(
        preset.type === 'screen'
          ? { type: 'replace', document: presetDocument(preset) }
          : presetInsertion(
              document.value,
              parent,
              preset,
              `preset_${crypto.randomUUID().replaceAll('-', '')}`
            )
      )
      presetsVisible.value = false
    } catch (e) {
      if (e instanceof Error) ElMessage.error(bt(e.message))
    }
  }
  function exportDocument() {
    const url = URL.createObjectURL(
      new Blob([JSON.stringify(document.value, null, 2)], { type: 'application/json' })
    )
    const link = window.document.createElement('a')
    link.href = url
    link.download = 'boardstudio-v2.json'
    window.document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  async function applyImport(text: string) {
    try {
      const value = JSON.parse(text)
      validateDocument(value)
      await ElMessageBox.confirm(
        bt('导入将替换当前文档，可撤销。数据绑定需在当前环境重新核对。'),
        bt('导入 V2 文档')
      )
      execute({ type: 'replace', document: value })
      importVisible.value = false
    } catch (e) {
      if (e instanceof Error) ElMessage.error(bt(e.message))
    }
  }
  async function importDocument(event: Event) {
    const input = event.target as HTMLInputElement,
      file = input.files?.[0]
    input.value = ''
    if (!file) return
    if (file.size > 2 * 1024 * 1024) {
      ElMessage.error(bt('文档不能超过 2 MB'))
      return
    }
    importText.value = await file.text()
    await applyImport(importText.value)
  }
  const session = createDocumentSession(props.initialDocument)
  const document = shallowRef(session.document)
  const savedSnapshot = ref(props.initiallyDirty ? '' : JSON.stringify(document.value))
  const pageValues = ref<Record<string, unknown>>({})
  watch(
    () => JSON.stringify(document.value.parameters),
    () => {
      pageValues.value = {}
    },
    { flush: 'sync' }
  )
  const resolvedDocument = computed(() => resolveDocumentBindings(document.value, pageValues.value))
  const results = reactive<Record<string, BindingResult>>({})
  const dataRuntime = createDataRuntime(
    (binding, signal) =>
      datasetApi.execute(binding.datasetId, binding.params, binding.datasetVersion, signal),
    (id, result) => {
      results[id] = result
    }
  )
  const bindingSignature = computed(() =>
    JSON.stringify(
      [...bindingGroups(resolvedDocument.value)].map(([key, bindings]) => [
        key,
        bindings.map((b) => [b.id, b.refresh])
      ])
    )
  )
  watch(bindingSignature, () => dataRuntime.replace(resolvedDocument.value), { immediate: true })
  const visibility = () => dataRuntime.pause(window.document.hidden)
  const selectedIds = ref<string[]>([])
  const selectedId = ref(document.value.rootId)
  const alignments: Record<Alignment, string> = {
    left: '左对齐',
    center: '水平居中',
    right: '右对齐',
    top: '顶对齐',
    middle: '垂直居中',
    bottom: '底对齐',
    horizontal: '水平等距',
    vertical: '垂直等距'
  }
  function selectNode(id: string, event?: MouseEvent) {
    if (suppressClick) return
    if (event?.shiftKey) {
      selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((item) => item !== id)
        : [...selectedIds.value, id]
      selectedId.value = selectedIds.value.at(-1) || document.value.rootId
    } else {
      selectedId.value = id
      selectedIds.value = id === document.value.rootId ? [] : [id]
    }
  }
  function align(mode: Alignment) {
    try {
      execute(alignSelection(document.value, selectedIds.value, mode))
    } catch (e) {
      ElMessage.error(bt((e as Error).message))
    }
  }
  function batchFlag(key: 'hidden' | 'locked', value: boolean) {
    execute({
      type: 'batch',
      commands: selectionRoots(document.value, selectedIds.value).map((id) => ({
        type: 'patch',
        id,
        patch: { [key]: value }
      }))
    })
  }
  function removeSelected() {
    const ids = selectionRoots(
      document.value,
      selectedIds.value.length ? selectedIds.value : [selectedId.value]
    )
    execute({ type: 'batch', commands: ids.map((id) => ({ type: 'remove', id })) })
  }
  const marquee = ref<{ x: number; y: number; width: number; height: number }>()
  function startMarquee(event: PointerEvent, id: string) {
    if (preview.value || event.button !== 0) return
    const container = event.currentTarget as HTMLElement
    const children = Array.from(container.children).filter((el) =>
      el.hasAttribute('data-node-id')
    ) as HTMLElement[]
    const origin = { x: event.clientX, y: event.clientY },
      viewportRect = viewport.value!.getBoundingClientRect()
    const base = event.shiftKey ? selectedIds.value : []
    let moved = false
    const move = (pointer: PointerEvent) => {
      const left = Math.min(origin.x, pointer.clientX),
        top = Math.min(origin.y, pointer.clientY),
        right = Math.max(origin.x, pointer.clientX),
        bottom = Math.max(origin.y, pointer.clientY)
      moved = Math.abs(pointer.clientX - origin.x) + Math.abs(pointer.clientY - origin.y) > 4
      marquee.value = {
        x: left - viewportRect.left + viewport.value!.scrollLeft,
        y: top - viewportRect.top + viewport.value!.scrollTop,
        width: right - left,
        height: bottom - top
      }
      selectedIds.value = [
        ...new Set([
          ...base,
          ...children
            .filter((el) => {
              const r = el.getBoundingClientRect()
              return r.left >= left && r.right <= right && r.top >= top && r.bottom <= bottom
            })
            .map((el) => el.dataset.nodeId!)
        ])
      ]
      selectedId.value = selectedIds.value.at(-1) || id
    }
    const end = () => {
      cleanup()
      if (!moved) selectNode(id, event)
      suppressClick = true
      setTimeout(() => (suppressClick = false), 0)
    }
    const cleanup = () => {
      marquee.value = undefined
      window.removeEventListener('pointermove', move)
      window.removeEventListener('pointerup', end)
      window.removeEventListener('pointercancel', cleanup)
      removeDragListeners = undefined
    }
    removeDragListeners?.()
    removeDragListeners = cleanup
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', end, { once: true })
    window.addEventListener('pointercancel', cleanup, { once: true })
  }
  let suppressClick = false
  const selected = computed(() => document.value.nodes[selectedId.value])
  const isRoot = computed(() => selectedId.value === document.value.rootId)
  const canUndo = ref(false)
  const canRedo = ref(false)
  const dirty = computed(() => JSON.stringify(document.value) !== savedSnapshot.value)
  const saving = ref(false)
  const preview = ref(false)
  const zoom = ref(65)
  const scale = computed(() => zoom.value / 100)
  const viewport = ref<HTMLElement>()
  const availableWidth = ref(1000)
  const widgetType = ref('stat-number')
  const modes = { free: '自由容器', flex: 'Flex 容器', grid: 'Grid 容器' }
  const nodeCount = computed(() => Object.keys(document.value.nodes).length)
  let observer: ResizeObserver | undefined
  let removeDragListeners: (() => void) | undefined
  const sync = (next: BoardDocument) => {
    document.value = next
    canUndo.value = session.canUndo
    canRedo.value = session.canRedo
    selectedIds.value = selectedIds.value.filter((id) => next.nodes[id])
    if (!next.nodes[selectedId.value]) selectedId.value = next.rootId
  }
  const execute = (command: DocumentCommand) => {
    try {
      sync(session.execute(command))
    } catch (error) {
      ElMessage.error(bt((error as Error).message))
    }
  }
  const travel = (direction: 'undo' | 'redo') => sync(session[direction]())
  const newId = () => `node_${crypto.randomUUID().replaceAll('-', '')}`
  const insertionParent = () =>
    selected.value.kind === 'container'
      ? selectedId.value
      : parentOf(document.value, selectedId.value)!.id
  function insertContainer(mode: LayoutMode) {
    const node = createNode(newId(), 'container')
    node.name = modes[mode]
    node.layout!.mode = mode
    node.placement.width = 640
    node.placement.height = 360
    node.appearance.borderWidth = 1
    execute({ type: 'insert', parentId: insertionParent(), node })
    if (document.value.nodes[node.id]) selectNode(node.id)
  }
  function insertWidget() {
    const source = createDefaultComponent(
      widgetType.value,
      Object.keys(document.value.nodes).length
    )
    const node = createNode(newId(), 'widget', widgetType.value)
    node.name = source.title
    node.widget!.props = source.option || {}
    node.placement.width = source.rect.w
    node.placement.height = source.rect.h
    execute({ type: 'insert', parentId: insertionParent(), node })
    if (document.value.nodes[node.id]) selectNode(node.id)
  }
  function duplicate() {
    const roots = selectionRoots(
      document.value,
      selectedIds.value.length ? selectedIds.value : [selectedId.value]
    )
    const mapping = Object.fromEntries(
      roots.flatMap((id) => descendants(document.value, id)).map((id) => [id, newId()])
    )
    execute({
      type: 'batch',
      commands: roots.map((id) => ({ type: 'duplicate', id, ids: mapping }))
    })
    selectedIds.value = roots.map((id) => mapping[id]).filter((id) => document.value.nodes[id])
    selectedId.value = selectedIds.value.at(-1) || document.value.rootId
  }
  async function save() {
    if (saving.value) return
    saving.value = true
    const snapshot = session.document
    try {
      await props.onSave(snapshot)
      savedSnapshot.value = JSON.stringify(snapshot)
      ElMessage.success(bt('文档已保存'))
    } catch (error) {
      ElMessage.error((error as Error).message || bt('保存失败'))
    } finally {
      saving.value = false
    }
  }
  function startDrag(event: PointerEvent, id: string) {
    if (preview.value || event.button !== 0) return
    if (event.shiftKey) return
    if (!selectedIds.value.includes(id)) selectNode(id)
    const node = document.value.nodes[id]
    const parent = parentOf(document.value, id)
    if (!parent || parent.layout?.mode !== 'free' || node.locked) return
    let ancestor: typeof parent | undefined = parent
    while (ancestor) {
      if (ancestor.locked) return
      ancestor = parentOf(document.value, ancestor.id)
    }
    const ids = selectionRoots(document.value, selectedIds.value).filter(
      (key) => parentOf(document.value, key)?.id === parent.id
    )
    if (ids.length !== selectedIds.value.length) return
    if (ids.some((key) => document.value.nodes[key].locked)) return
    const entries = ids.map((key) => ({
      id: key,
      origin: { ...document.value.nodes[key].placement },
      target: viewport.value!.querySelector(`[data-node-id="${key}"]`) as HTMLElement
    }))
    const origin = { ...node.placement }
    const start = { x: event.clientX, y: event.clientY }
    let x = origin.x,
      y = origin.y
    const move = (pointer: PointerEvent) => {
      x = Math.round((origin.x + (pointer.clientX - start.x) / scale.value) / 4) * 4
      y = Math.round((origin.y + (pointer.clientY - start.y) / scale.value) / 4) * 4
      for (const item of entries) {
        item.target.style.left = `${item.origin.x + x - origin.x}px`
        item.target.style.top = `${item.origin.y + y - origin.y}px`
      }
    }
    const end = () => {
      cleanup()
      suppressClick = true
      setTimeout(() => (suppressClick = false), 0)
      for (const item of entries) {
        item.target.style.left = `${item.origin.x}px`
        item.target.style.top = `${item.origin.y}px`
      }
      if (x !== origin.x || y !== origin.y)
        execute({
          type: 'batch',
          commands: entries.map((item) => ({
            type: 'patch',
            id: item.id,
            patch: {
              placement: {
                ...item.origin,
                x: item.origin.x + x - origin.x,
                y: item.origin.y + y - origin.y
              }
            }
          }))
        })
    }
    const cancel = () => {
      cleanup()
      for (const item of entries) {
        item.target.style.left = `${item.origin.x}px`
        item.target.style.top = `${item.origin.y}px`
      }
    }
    const cleanup = () => {
      window.removeEventListener('pointermove', move)
      window.removeEventListener('pointerup', end)
      window.removeEventListener('pointercancel', cancel)
      removeDragListeners = undefined
    }
    removeDragListeners?.()
    removeDragListeners = cancel
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', end, { once: true })
    window.addEventListener('pointercancel', cancel, { once: true })
  }
  function startResize(event: PointerEvent, id: string) {
    if (preview.value || event.button !== 0) return
    const node = document.value.nodes[id],
      parent = parentOf(document.value, id)
    if (!parent || parent.layout?.mode !== 'free' || node.locked) return
    let ancestor: typeof parent | undefined = parent
    while (ancestor) {
      if (ancestor.locked) return
      ancestor = parentOf(document.value, ancestor.id)
    }
    event.preventDefault()
    const target = (event.currentTarget as HTMLElement).parentElement!
    const origin = { ...node.placement },
      start = { x: event.clientX, y: event.clientY }
    let width = origin.width,
      height = origin.height
    const move = (pointer: PointerEvent) => {
      width = Math.max(20, Math.round(origin.width + (pointer.clientX - start.x) / scale.value))
      height = Math.max(20, Math.round(origin.height + (pointer.clientY - start.y) / scale.value))
      target.style.width = `${width}px`
      target.style.height = `${height}px`
    }
    const cleanup = () => {
      window.removeEventListener('pointermove', move)
      window.removeEventListener('pointerup', end)
      window.removeEventListener('pointercancel', cancel)
      removeDragListeners = undefined
    }
    const end = () => {
      cleanup()
      target.style.width = `${origin.width}px`
      target.style.height = `${origin.height}px`
      execute({ type: 'patch', id, patch: { placement: { ...origin, width, height } } })
    }
    const cancel = () => {
      cleanup()
      target.style.width = `${origin.width}px`
      target.style.height = `${origin.height}px`
    }
    removeDragListeners?.()
    removeDragListeners = cancel
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', end, { once: true })
    window.addEventListener('pointercancel', cancel, { once: true })
  }
  function keyboard(event: KeyboardEvent) {
    if (
      preview.value ||
      (event.target as HTMLElement)?.closest(
        'input, textarea, [contenteditable="true"], [role="dialog"]'
      )
    )
      return
    if (event.key === 'Delete' || event.key === 'Backspace') {
      event.preventDefault()
      removeSelected()
      return
    }
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'd') {
      event.preventDefault()
      duplicate()
      return
    }
    if (!(event.metaKey || event.ctrlKey)) return
    if (event.key.toLowerCase() === 'z') {
      event.preventDefault()
      travel(event.shiftKey ? 'redo' : 'undo')
    }
    if (event.key.toLowerCase() === 's') {
      event.preventDefault()
      void save()
    }
  }
  function beforeUnload(event: BeforeUnloadEvent) {
    if (dirty.value) event.preventDefault()
  }
  watch(preview, () => removeDragListeners?.())
  onMounted(() => {
    window.document.addEventListener('visibilitychange', visibility)
    window.addEventListener('keydown', keyboard)
    window.addEventListener('beforeunload', beforeUnload)
    observer = new ResizeObserver((entries) => {
      availableWidth.value = entries[0].contentRect.width - 48
    })
    if (viewport.value) observer.observe(viewport.value)
  })
  onBeforeUnmount(() => {
    dataRuntime.stop()
    window.document.removeEventListener('visibilitychange', visibility)
    observer?.disconnect()
    removeDragListeners?.()
    window.removeEventListener('keydown', keyboard)
    window.removeEventListener('beforeunload', beforeUnload)
  })
  defineExpose({ dirty })
</script>
<style scoped>
  .document-editor {
    height: calc(100vh - 100px);
    min-height: 600px;
    display: flex;
    flex-direction: column;
    background: var(--el-bg-color-page);
    color: var(--el-text-color-primary);
  }
  header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: var(--el-bg-color);
    border-bottom: 1px solid var(--el-border-color);
    flex-wrap: wrap;
  }
  .zoom {
    display: flex;
    gap: 6px;
    align-items: center;
    margin-left: auto;
    font-size: 12px;
  }
  .zoom :deep(.el-input-number) {
    width: 100px;
  }
  .editor-body {
    flex: 1;
    display: flex;
    min-height: 0;
  }
  aside {
    background: var(--el-bg-color);
    overflow: auto;
    flex-shrink: 0;
  }
  .left-panel {
    width: 230px;
    padding: 12px;
    border-right: 1px solid var(--el-border-color);
  }
  .left-panel p {
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }
  .left-panel h3 {
    font-size: 13px;
    margin: 14px 0;
  }
  .insert-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
  }
  .insert-buttons :deep(.el-button) {
    margin: 0;
  }
  .multi-inspector {
    padding: 16px;
  }
  .multi-inspector p {
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }
  .multi-inspector :deep(.el-button) {
    margin: 4px;
  }
  .right-panel {
    width: 280px;
    border-left: 1px solid var(--el-border-color);
  }
  .selection-tools {
    display: flex;
    gap: 6px;
    align-items: center;
    padding: 8px 16px;
    background: var(--el-bg-color);
  }
  .selection-tools span {
    font-size: 12px;
  }
  .marquee {
    position: absolute;
    border: 1px solid var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    pointer-events: none;
    z-index: 999999;
  }
  .canvas-viewport {
    position: relative;
    flex: 1;
    min-width: 0;
    overflow: auto;
    padding: 24px;
    background-color: var(--el-border-color);
    background-image: radial-gradient(var(--el-border-color-darker) 1px, transparent 1px);
    background-size: 16px 16px;
  }
  .canvas-page {
    transform-origin: top left;
    box-shadow: var(--el-box-shadow-light);
  }
  footer {
    padding: 8px 16px;
    background: var(--el-bg-color);
    font-size: 12px;
    border-top: 1px solid var(--el-border-color);
  }
  footer span {
    float: right;
  }
</style>
