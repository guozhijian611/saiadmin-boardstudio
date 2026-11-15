<template>
  <div
    :style="containerStyle(node)"
    :data-container-id="node.id"
    @click.stop="$emit('select', node.id, $event)"
    @pointerdown.self.stop="$emit('container-pointer', $event, node.id)"
  >
    <div
      v-for="child in children"
      :key="child.id"
      :style="nodeStyle(child, node)"
      :data-node-id="child.id"
      :class="{
        'document-selected':
          editable && (selectedIds?.includes(child.id) || selectedId === child.id)
      }"
      @click.stop="$emit('select', child.id, $event)"
      @pointerdown.stop="$emit('node-pointer', $event, child.id)"
    >
      <DocumentNode
        v-if="child.kind === 'container'"
        :document="document"
        :node-id="child.id"
        :selected-id="selectedId"
        :selected-ids="selectedIds"
        :editable="editable"
        :results="results"
        @select="(id, event) => $emit('select', id, event)"
        @container-pointer="(event, id) => $emit('container-pointer', event, id)"
        @node-pointer="(event, id) => $emit('node-pointer', event, id)"
        @node-resize="(event, id) => $emit('node-resize', event, id)"
      />
      <WidgetRenderer
        v-else
        :component="widgetComponent(child)"
        :rows="result(child)?.rows || []"
        :error="result(child)?.error || ''"
        :runtime="!editable"
      />
      <button
        v-if="
          editable &&
          (!selectedIds || selectedIds.length < 2) &&
          selectedId === child.id &&
          node.layout?.mode === 'free' &&
          !child.locked
        "
        class="resize-handle"
        :aria-label="`调整 ${child.name} 尺寸`"
        @click.stop
        @pointerdown.stop="$emit('node-resize', $event, child.id)"
      />
      <span v-if="editable && child.locked" class="document-lock">锁定</span>
    </div>
  </div>
</template>
<script setup lang="ts">
  import { computed } from 'vue'
  import { containerStyle, nodeStyle } from '../document/layout'
  import type { BoardDocument, DocumentNode as Node } from '../document/types'
  import type { BoardComponent } from '../widgets/types'
  import WidgetRenderer from '../widgets/WidgetRenderer.vue'
  const props = withDefaults(
    defineProps<{
      document: BoardDocument
      nodeId: string
      selectedIds?: string[]
      selectedId?: string
      editable?: boolean
      results?: Record<string, { rows: Record<string, unknown>[]; error?: string }>
    }>(),
    { selectedId: '', editable: false, results: () => ({}) }
  )
  defineEmits<{
    select: [id: string, event: MouseEvent]
    'container-pointer': [event: PointerEvent, id: string]
    'node-pointer': [event: PointerEvent, id: string]
    'node-resize': [event: PointerEvent, id: string]
  }>()
  const node = computed(() => props.document.nodes[props.nodeId])
  const children = computed(() => (node.value.children || []).map((id) => props.document.nodes[id]))
  const result = (child: Node) =>
    child.binding ? props.results[child.binding.instanceId] : undefined
  const widgetComponent = (child: Node): BoardComponent => ({
    id: child.id,
    type: child.widget!.type,
    title: child.name,
    rect: {
      x: 0,
      y: 0,
      w: child.placement.width,
      h: child.placement.height,
      z: child.placement.zIndex
    },
    option: child.widget!.props,
    dataset: child.binding
      ? {
          queryTemplateId: props.document.bindings[child.binding.instanceId]?.datasetId,
          mapping: child.binding.mapping
        }
      : {}
  })
</script>
<style scoped>
  .resize-handle {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 12px;
    height: 12px;
    background: #409eff;
    border: 1px solid #fff;
    cursor: se-resize;
    z-index: 100001;
  }
  .document-selected {
    outline: 2px solid #409eff;
    outline-offset: -2px;
  }
  .document-lock {
    position: absolute;
    top: 2px;
    right: 2px;
    padding: 2px 5px;
    color: #fff;
    background: #334155;
    font-size: 10px;
  }
</style>
