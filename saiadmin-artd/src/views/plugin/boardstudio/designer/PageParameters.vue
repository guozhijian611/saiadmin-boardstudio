<template>
  <ElButton size="small" @click="open">页面参数</ElButton>
  <ElDialog v-model="visible" title="页面参数与联动" width="740px">
    <p>参数在发布大屏显示为筛选项。数据绑定可引用参数，同一参数会同时刷新关联组件。</p>
    <div class="parameter-row" v-for="(row, index) in rows" :key="index">
      <ElInput v-model="row.name" placeholder="参数名" />
      <ElSelect v-model="row.type"
        ><ElOption value="string" label="文本" /><ElOption value="number" label="数字" /><ElOption
          value="boolean"
          label="布尔"
      /></ElSelect>
      <ElInput v-model="row.default" placeholder="默认值" />
      <ElButton @click="rows.splice(index, 1)">删除</ElButton>
    </div>
    <ElButton @click="rows.push({ name: '', type: 'string', default: '' })">添加参数</ElButton>
    <template #footer><ElButton type="primary" @click="apply">应用参数定义</ElButton></template>
  </ElDialog>
</template>
<script setup lang="ts">
  import { ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import type { BoardDocument } from '../document/types'
  import type { DocumentCommand } from '../document/commands'
  import { resolvePageParameters } from '../document/parameters'
  const props = defineProps<{ document: BoardDocument }>()
  const emit = defineEmits<{ command: [command: DocumentCommand] }>()
  const visible = ref(false),
    rows = ref<Array<{ name: string; type: 'string' | 'number' | 'boolean'; default: string }>>([])
  function open() {
    rows.value = Object.entries(props.document.parameters).map(([name, p]) => ({
      name,
      type: p.type,
      default: String(p.default)
    }))
    visible.value = true
  }
  function apply() {
    try {
      const parameters: BoardDocument['parameters'] = {}
      for (const row of rows.value) {
        if (!/^[A-Za-z][A-Za-z0-9_]{0,39}$/.test(row.name) || Object.hasOwn(parameters, row.name))
          throw new Error('参数名必须是唯一的英文字母、数字或下划线')
        parameters[row.name] = { type: row.type, default: row.default }
      }
      const values = resolvePageParameters({ ...props.document, parameters })
      for (const name of Object.keys(parameters)) parameters[name].default = values[name]
      emit('command', { type: 'parameters', parameters })
      visible.value = false
    } catch (e) {
      ElMessage.error((e as Error).message)
    }
  }
</script>
<style scoped>
  .parameter-row {
    display: flex;
    gap: 8px;
    margin: 12px 0;
  }
  .parameter-row .el-input,
  .parameter-row .el-select {
    flex: 1;
  }
</style>
