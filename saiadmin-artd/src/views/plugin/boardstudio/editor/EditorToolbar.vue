<template>
  <div class="editor-toolbar">
    <ElSpace>
      <ElButton @click="back">{{ bt('返回') }}</ElButton>
      <ElDivider direction="vertical" />
      <strong>{{ screen.name || bt('大屏编辑器') }}</strong>
      <ElTag v-if="screen.status === 1" type="success">{{ bt('已发布') }}</ElTag>
      <ElTag v-else>{{ bt('草稿') }}</ElTag>
      <ElTag :type="dirty ? 'warning' : 'info'">{{
        dirty ? bt('有未保存更改') : bt('已保存')
      }}</ElTag>
    </ElSpace>
    <ElSpace>
      <ElSelect v-model="zoom" style="width: 130px">
        <ElOption :label="bt('适应窗口')" value="auto" />
        <ElOption label="50%" :value="0.5" />
        <ElOption label="75%" :value="0.75" />
        <ElOption label="100%" :value="1" />
      </ElSelect>
      <ElDivider direction="vertical" />
      <ElButton @click="exportLayout">{{ bt('导出布局') }}</ElButton>
      <ElButton :disabled="busy" @click="importLayout">{{ bt('导入布局') }}</ElButton>
      <ElButton :disabled="!canUndo" @click="undoLayout">{{ bt('撤销') }}</ElButton>
      <ElButton :disabled="!canRedo" @click="redoLayout">{{ bt('重做') }}</ElButton>
      <ElButton :disabled="!canCopy" @click="copySelected">{{ bt('复制') }}</ElButton>
      <ElButton :disabled="!canPaste" @click="pasteCopied">{{ bt('粘贴') }}</ElButton>
      <ElButton :disabled="!canGroup" @click="groupSelected">{{ bt('组合') }}</ElButton>
      <ElButton :disabled="!canUngroup" @click="ungroupSelected">{{ bt('取消组合') }}</ElButton>
      <ElButton @click="openPresets('screen')">{{ bt('预设模板') }}</ElButton>
      <ElButton
        v-if="can('boardstudio:screen:saveLayout')"
        type="primary"
        :loading="busy"
        @click="saveLayout"
        >{{ bt('保存') }}</ElButton
      >
      <ElButton
        v-if="can('boardstudio:screen:read')"
        :disabled="busy || !screen.code"
        @click="openDraftPreview"
        >{{ bt('预览草稿') }}</ElButton
      >
      <ElButton
        v-if="can('boardstudio:screen:publish')"
        type="success"
        :disabled="busy"
        @click="publish"
        >{{ bt('发布') }}</ElButton
      >
    </ElSpace>
  </div>
</template>

<script setup lang="ts">
  import { useBoardPermissions } from '../core/permissions'
  const { can } = useBoardPermissions()
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  const zoom = defineModel<number | 'auto'>('zoom', { required: true })
  defineProps<{
    screen: { name: string; status: number; code?: string }
    dirty: boolean
    busy: boolean
    canUndo: boolean
    canRedo: boolean
    canCopy: boolean
    canPaste: boolean
    canGroup: boolean
    canUngroup: boolean
    exportLayout: () => unknown
    importLayout: () => unknown
    back: () => unknown
    undoLayout: () => unknown
    redoLayout: () => unknown
    copySelected: () => unknown
    pasteCopied: () => unknown
    groupSelected: () => unknown
    ungroupSelected: () => unknown
    saveLayout: () => unknown
    openDraftPreview: () => unknown
    publish: () => unknown
    openPresets: (type: 'screen' | 'component') => unknown
  }>()
</script>

<style scoped lang="scss">
  .editor-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 56px;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 16px;
    background: var(--default-box-color);
    border-bottom: 1px solid var(--default-border);
  }
  .editor-toolbar > :deep(.el-space) {
    flex-wrap: wrap;
  }
</style>
