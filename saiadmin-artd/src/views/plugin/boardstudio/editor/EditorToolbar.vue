<template>
  <div class="editor-toolbar">
    <ElSpace>
      <ElButton @click="back">返回</ElButton>
      <ElDivider direction="vertical" />
      <strong>{{ screen.name || '大屏编辑器' }}</strong>
      <ElTag v-if="screen.status === 1" type="success">已发布</ElTag>
      <ElTag v-else>草稿</ElTag>
      <ElTag :type="dirty ? 'warning' : 'info'">{{ dirty ? '有未保存更改' : '已保存' }}</ElTag>
    </ElSpace>
    <ElSpace>
      <ElSelect v-model="zoom" style="width: 130px">
        <ElOption label="适应窗口" value="auto" />
        <ElOption label="50%" :value="0.5" />
        <ElOption label="75%" :value="0.75" />
        <ElOption label="100%" :value="1" />
      </ElSelect>
      <ElDivider direction="vertical" />
      <ElButton @click="exportLayout">导出布局</ElButton>
      <ElButton :disabled="busy" @click="importLayout">导入布局</ElButton>
      <ElButton :disabled="!canUndo" @click="undoLayout">撤销</ElButton>
      <ElButton :disabled="!canRedo" @click="redoLayout">重做</ElButton>
      <ElButton :disabled="!canCopy" @click="copySelected">复制</ElButton>
      <ElButton :disabled="!canPaste" @click="pasteCopied">粘贴</ElButton>
      <ElButton :disabled="!canGroup" @click="groupSelected">组合</ElButton>
      <ElButton :disabled="!canUngroup" @click="ungroupSelected">取消组合</ElButton>
      <ElButton @click="openPresets('screen')">预设模板</ElButton>
      <ElButton
        v-permission="'boardstudio:screen:saveLayout'"
        type="primary"
        :loading="busy"
        @click="saveLayout"
      >
        保存
      </ElButton>
      <ElButton
        v-permission="'boardstudio:screen:read'"
        :disabled="busy || !screen.code"
        @click="openDraftPreview"
      >
        预览草稿
      </ElButton>
      <ElButton
        v-permission="'boardstudio:screen:publish'"
        type="success"
        :disabled="busy"
        @click="publish"
      >
        发布
      </ElButton>
    </ElSpace>
  </div>
</template>

<script setup lang="ts">
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
