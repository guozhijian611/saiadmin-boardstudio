<template>
  <div class="inspector">
    <h3>{{
      node.id === document.rootId ? '页面' : node.kind === 'container' ? '容器' : '组件'
    }}</h3>
    <ElForm label-position="top" size="small">
      <ElFormItem label="名称"
        ><ElInput :model-value="node.name" @change="patch({ name: $event })"
      /></ElFormItem>
      <template v-if="node.id === document.rootId">
        <ElFormItem label="页面宽度"
          ><ElInputNumber
            :model-value="document.page.width"
            :min="320"
            :max="16384"
            @change="page('width', $event)"
        /></ElFormItem>
        <ElFormItem label="页面高度"
          ><ElInputNumber
            :model-value="document.page.height"
            :min="240"
            :max="16384"
            @change="page('height', $event)"
        /></ElFormItem>
        <ElFormItem label="尺寸策略"
          ><ElSelect
            :model-value="document.page.sizing"
            @change="$emit('command', { type: 'page', patch: { sizing: $event } })"
            ><ElOption label="固定画布" value="fixed" /><ElOption
              label="响应容器宽度"
              value="responsive" /></ElSelect
        ></ElFormItem>
      </template>
      <ElTabs v-model="tab">
        <ElTabPane label="布局" name="layout">
          <template v-if="node.layout">
            <ElFormItem label="容器排版"
              ><ElSelect
                :model-value="node.layout.mode"
                @change="$emit('command', { type: 'layout', id: node.id, mode: $event })"
                ><ElOption label="自由布局" value="free" /><ElOption
                  label="Flex 弹性布局"
                  value="flex" /><ElOption label="Grid 网格布局" value="grid" /></ElSelect
            ></ElFormItem>
            <p class="hint">切换后按新规则排版，可撤销；自由布局坐标会保留。</p>
            <template v-if="node.layout.mode === 'flex'">
              <ElFormItem label="排列方向"
                ><ElSelect
                  :model-value="node.layout.direction"
                  @change="container('direction', $event)"
                  ><ElOption label="横向" value="row" /><ElOption
                    label="纵向"
                    value="column" /></ElSelect
              ></ElFormItem>
              <ElFormItem label="换行"
                ><ElSwitch :model-value="node.layout.wrap" @change="container('wrap', $event)"
              /></ElFormItem>
              <ElFormItem label="主轴对齐"
                ><ElSelect :model-value="node.layout.justify" @change="container('justify', $event)"
                  ><ElOption
                    v-for="(label, value) in justify"
                    :key="value"
                    :label="label"
                    :value="value" /></ElSelect
              ></ElFormItem>
            </template>
            <ElFormItem v-if="node.layout.mode !== 'free'" label="交叉轴对齐"
              ><ElSelect :model-value="node.layout.align" @change="container('align', $event)"
                ><ElOption
                  v-for="(label, value) in align"
                  :key="value"
                  :label="label"
                  :value="value" /></ElSelect
            ></ElFormItem>
            <template v-if="node.layout.mode === 'grid'">
              <ElFormItem label="列数"
                ><ElInputNumber
                  :model-value="node.layout.columns"
                  :min="1"
                  :max="24"
                  @change="container('columns', $event)"
              /></ElFormItem>
              <ElFormItem label="行高"
                ><ElInputNumber
                  :model-value="node.layout.rowHeight"
                  :min="1"
                  :max="16384"
                  @change="container('rowHeight', $event)"
              /></ElFormItem>
            </template>
            <ElFormItem label="内边距"
              ><ElInputNumber
                :model-value="node.layout.padding"
                :min="0"
                :max="1000"
                @change="container('padding', $event)"
            /></ElFormItem>
            <ElFormItem label="子节点间距"
              ><ElInputNumber
                :model-value="node.layout.gap"
                :min="0"
                :max="1000"
                @change="container('gap', $event)"
            /></ElFormItem>
          </template>
          <template v-if="parent">
            <ElFormItem label="所属容器"
              ><ElSelect
                :model-value="parent.id"
                @change="$emit('command', { type: 'move', id: node.id, parentId: $event })"
                ><ElOption
                  v-for="target in parents"
                  :key="target.id"
                  :label="target.name"
                  :value="target.id" /></ElSelect
            ></ElFormItem>
            <template v-if="parent.layout?.mode === 'free'">
              <ElFormItem label="X"
                ><ElInputNumber :model-value="node.placement.x" @change="position('x', $event)"
              /></ElFormItem>
              <ElFormItem label="Y"
                ><ElInputNumber :model-value="node.placement.y" @change="position('y', $event)"
              /></ElFormItem>
            </template>
            <template v-if="parent.layout?.mode !== 'grid'">
              <ElFormItem label="宽度"
                ><ElInputNumber
                  :model-value="node.placement.width"
                  :min="1"
                  :max="16384"
                  @change="position('width', $event)"
              /></ElFormItem>
              <ElFormItem label="高度"
                ><ElInputNumber
                  :model-value="node.placement.height"
                  :min="1"
                  :max="16384"
                  @change="position('height', $event)"
              /></ElFormItem>
            </template>
            <template v-if="parent.layout?.mode === 'flex'">
              <ElFormItem label="伸展比例"
                ><ElInputNumber
                  :model-value="node.placement.grow"
                  :min="0"
                  :max="100"
                  @change="position('grow', $event)"
              /></ElFormItem>
              <ElFormItem label="收缩比例"
                ><ElInputNumber
                  :model-value="node.placement.shrink"
                  :min="0"
                  :max="100"
                  @change="position('shrink', $event)"
              /></ElFormItem>
            </template>
            <template v-if="parent.layout?.mode === 'grid'">
              <ElFormItem label="跨列"
                ><ElInputNumber
                  :model-value="node.placement.columnSpan"
                  :min="1"
                  :max="24"
                  @change="position('columnSpan', $event)"
              /></ElFormItem>
              <ElFormItem label="跨行"
                ><ElInputNumber
                  :model-value="node.placement.rowSpan"
                  :min="1"
                  :max="100"
                  @change="position('rowSpan', $event)"
              /></ElFormItem>
            </template>
            <ElFormItem label="层级"
              ><ElInputNumber
                :model-value="node.placement.zIndex"
                :min="0"
                @change="position('zIndex', $event)"
            /></ElFormItem>
          </template>
        </ElTabPane>
        <ElTabPane label="外观" name="appearance">
          <ElFormItem label="背景"
            ><ElInput
              :model-value="node.appearance.background"
              @change="appearance('background', $event)"
          /></ElFormItem>
          <ElFormItem label="边框颜色"
            ><ElInput
              :model-value="node.appearance.borderColor"
              @change="appearance('borderColor', $event)"
          /></ElFormItem>
          <ElFormItem label="边框宽度"
            ><ElInputNumber
              :model-value="node.appearance.borderWidth"
              :min="0"
              :max="100"
              @change="appearance('borderWidth', $event)"
          /></ElFormItem>
          <ElFormItem label="圆角"
            ><ElInputNumber
              :model-value="node.appearance.borderRadius"
              :min="0"
              :max="1000"
              @change="appearance('borderRadius', $event)"
          /></ElFormItem>
          <ElFormItem label="透明度"
            ><ElInputNumber
              :model-value="node.appearance.opacity"
              :min="0"
              :max="1"
              :step="0.1"
              @change="appearance('opacity', $event)"
          /></ElFormItem>
          <WidgetProperties v-if="node.widget" :node="node" @command="$emit('command', $event)" />
        </ElTabPane>
      </ElTabs>
      <template v-if="parent">
        <ElFormItem label="隐藏"
          ><ElSwitch :model-value="node.hidden" @change="patch({ hidden: Boolean($event) })"
        /></ElFormItem>
        <ElFormItem label="锁定"
          ><ElSwitch :model-value="node.locked" @change="patch({ locked: Boolean($event) })"
        /></ElFormItem>
      </template>
    </ElForm>
  </div>
</template>
<script setup lang="ts">
  import { computed, ref } from 'vue'
  import WidgetProperties from './WidgetProperties.vue'
  import { descendants, parentOf } from '../document/model'
  import type {
    BoardDocument,
    ContainerLayout,
    NodeAppearance,
    NodePlacement
  } from '../document/types'
  import type { DocumentCommand } from '../document/commands'
  const props = defineProps<{ document: BoardDocument; nodeId: string }>()
  const emit = defineEmits<{ command: [command: DocumentCommand] }>()
  const tab = ref('layout')
  const node = computed(() => props.document.nodes[props.nodeId])
  const parent = computed(() => parentOf(props.document, props.nodeId))
  const parents = computed(() => {
    const excluded = new Set(descendants(props.document, props.nodeId))
    return Object.values(props.document.nodes).filter(
      (n) => n.kind === 'container' && !excluded.has(n.id)
    )
  })
  const justify = { start: '起始', center: '居中', end: '末尾', 'space-between': '两端分布' }
  const align = { start: '起始', center: '居中', end: '末尾', stretch: '拉伸' }
  const patch = (patch: Extract<DocumentCommand, { type: 'patch' }>['patch']) =>
    emit('command', { type: 'patch', id: node.value.id, patch })
  const position = (key: keyof NodePlacement, value: number | undefined) => {
    if (value !== undefined) patch({ placement: { ...node.value.placement, [key]: value } })
  }
  const container = (key: keyof ContainerLayout, value: unknown) => {
    if (value !== undefined)
      emit('command', { type: 'container', id: node.value.id, patch: { [key]: value } })
  }
  const appearance = (key: keyof NodeAppearance, value: unknown) => {
    if (value !== undefined) patch({ appearance: { ...node.value.appearance, [key]: value } })
  }
  const page = (key: 'width' | 'height', value: number | undefined) => {
    if (value !== undefined) emit('command', { type: 'page', patch: { [key]: value } })
  }
</script>
<style scoped>
  .inspector {
    padding: 14px;
  }
  .hint {
    font-size: 12px;
    color: #64748b;
  }
  :deep(.el-input-number),
  :deep(.el-select) {
    width: 100%;
  }
</style>
