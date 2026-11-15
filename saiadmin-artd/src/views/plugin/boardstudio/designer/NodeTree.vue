<template>
  <div class="node-tree">
    <div class="tree-row" @dragover.prevent @drop.stop.prevent="drop($event, 'inside')">
      <button
        type="button"
        :class="{ active: selectedIds?.includes(nodeId) || selectedId === nodeId }"
        :draggable="nodeId !== document.rootId && !node.locked"
        @dragstart="start"
        @click="$emit('select', nodeId, $event)"
      >
        <span>{{ node.kind === 'container' ? '▣' : '◇' }}</span
        >{{ node.name }}<small v-if="node.layout">{{ node.layout.mode }}</small
        ><small v-if="node.hidden">{{ bt('隐藏') }}</small
        ><small v-if="node.locked">{{ bt('锁定') }}</small>
      </button>
      <span
        v-if="nodeId !== document.rootId"
        class="drop-after"
        :title="bt('拖到这里排在本节点之后')"
        @dragover.prevent
        @drop.stop.prevent="drop($event, 'after')"
        >↧</span
      >
    </div>
    <NodeTree
      v-for="child in node.children"
      :key="child"
      :document="document"
      :node-id="child"
      :selected-id="selectedId"
      :selected-ids="selectedIds"
      @select="(id, event) => $emit('select', id, event)"
      @move="(id, parentId, index) => $emit('move', id, parentId, index)"
    />
  </div>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  import { computed } from 'vue'
  import type { BoardDocument } from '../document/types'
  import { parentOf } from '../document/model'
  const props = defineProps<{
    document: BoardDocument
    nodeId: string
    selectedId: string
    selectedIds?: string[]
  }>()
  const emit = defineEmits<{
    select: [id: string, event: MouseEvent]
    move: [id: string, parentId: string, index?: number]
  }>()
  const node = computed(() => props.document.nodes[props.nodeId])
  function start(event: DragEvent) {
    event.dataTransfer?.setData('application/x-boardstudio-node', props.nodeId)
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
  }
  function drop(event: DragEvent, position: 'inside' | 'after') {
    const id = event.dataTransfer?.getData('application/x-boardstudio-node')
    if (!id || !props.document.nodes[id]) return
    if (position === 'inside' && node.value.kind === 'container') emit('move', id, node.value.id)
    else {
      const parent = parentOf(props.document, node.value.id)
      if (parent) emit('move', id, parent.id, parent.children!.indexOf(node.value.id) + 1)
    }
  }
</script>
<style scoped>
  .node-tree .node-tree {
    padding-left: 12px;
  }
  .tree-row {
    display: flex;
    align-items: center;
  }
  .tree-row:hover {
    background: var(--el-fill-color-light);
  }
  button {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px;
    text-align: left;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
  }
  button.active {
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
  }
  small {
    font-size: 10px;
    opacity: 0.6;
  }
  .drop-after {
    cursor: move;
    padding: 8px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }
  .drop-after:hover {
    background: var(--el-color-primary-light-7);
  }
</style>
