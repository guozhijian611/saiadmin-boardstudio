<template>
  <div class="binding-panel">
    <h3>组件数据</h3>
    <p>同一绑定实例由多个组件共享结果；字段映射各自独立。</p>
    <ElForm label-position="top" size="small">
      <ElFormItem label="复用当前页面绑定"
        ><ElSelect v-model="existing" clearable placeholder="选择已有绑定" @change="useExisting"
          ><ElOption
            v-for="binding in Object.values(document.bindings)"
            :key="binding.id"
            :label="`${datasetName(binding.datasetId)} · ${binding.id}`"
            :value="binding.id" /></ElSelect
      ></ElFormItem>
      <ElFormItem label="数据集"
        ><ElSelect v-model="datasetId" filterable @change="selectDataset"
          ><ElOption
            v-for="dataset in datasets.filter((d) => d.status === 1 && d.permissions?.use)"
            :key="dataset.id"
            :label="dataset.name"
            :value="dataset.id" /></ElSelect
      ></ElFormItem>
      <BindingParameters
        :model-value="parameterValues"
        :definitions="datasetDefinition?.parameters || {}"
        :page-parameters="document.parameters"
        @update:model-value="params = JSON.stringify($event, null, 2)"
      />
      <ElCollapse
        ><ElCollapseItem title="高级：参数 JSON" name="json"
          ><ElFormItem label="参数 JSON"
            ><ElInput v-model="params" type="textarea" :rows="3"
          /></ElFormItem>
          <p v-pre>页面参数引用示例：{"customer":{"pageParameter":"customer"}}</p></ElCollapseItem
        ></ElCollapse
      >
      <ElFormItem label="刷新方式"
        ><ElSelect v-model="mode"
          ><ElOption value="interval" label="定时轮询" /><ElOption
            value="manual"
            label="仅首次及手动刷新" /></ElSelect
      ></ElFormItem>
      <ElFormItem v-if="mode === 'interval'" label="刷新秒数"
        ><ElInputNumber v-model="seconds" :min="10" :max="3600"
      /></ElFormItem>
      <ElFormItem label="分类字段"
        ><ElSelect
          v-model="mapping.labelField"
          clearable
          filterable
          :allow-create="!fieldOptions.length"
          default-first-option
          placeholder="选择分类字段"
          ><ElOption
            v-for="field in fieldOptions"
            :key="field.name"
            :value="field.name"
            :label="`${field.label || field.name} (${field.type})`" /></ElSelect
      ></ElFormItem>
      <ElFormItem label="数值字段"
        ><ElSelect
          v-model="mapping.valueField"
          clearable
          filterable
          :allow-create="!fieldOptions.length"
          default-first-option
          placeholder="选择数值字段"
          ><ElOption
            v-for="field in fieldOptions.filter((f) => f.type === 'number')"
            :key="field.name"
            :value="field.name"
            :label="field.label || field.name" /></ElSelect
      ></ElFormItem>
      <ElButton type="primary" :disabled="!datasetId" @click="apply">应用绑定</ElButton>
      <ElButton :disabled="!node.binding" @click="$emit('command', { type: 'unbind', id: node.id })"
        >解除</ElButton
      >
      <ElButton @click="$emit('refresh')">刷新数据</ElButton>
    </ElForm>
    <ElAlert v-if="result?.error" :title="result.error" type="error" :closable="false" />
    <p v-if="result"
      >{{ result.loading ? '加载中' : result.stale ? '上次成功结果（已过期）' : '已更新' }} ·
      {{ result.rows.length }} 行</p
    >
    <p v-if="result?.fetchedAt">数据时间：{{ result.fetchedAt }}</p>
    <ElTable
      v-if="result?.rows.length"
      :data="result.rows.slice(0, 5)"
      size="small"
      max-height="180"
      ><ElTableColumn
        v-for="field in Object.keys(result.rows[0])"
        :key="field"
        :prop="field"
        :label="field"
    /></ElTable>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import { ElMessage } from 'element-plus'
  import type { BoardDocument, DocumentNode } from '../document/types'
  import type { DocumentCommand } from '../document/commands'
  import type { BindingResult } from '../document/data-runtime'
  import type { BoardDataset } from '../widgets/types'
  import api from '../dataset/api'
  import BindingParameters from './BindingParameters.vue'
  import type { DatasetRecord } from '../dataset/types'
  const props = defineProps<{
    document: BoardDocument
    node: DocumentNode
    result?: BindingResult
  }>()
  const emit = defineEmits<{ command: [command: DocumentCommand]; refresh: [] }>()
  const datasets = ref<DatasetRecord[]>([]),
    datasetId = ref(0),
    existing = ref(''),
    params = ref('{}'),
    mode = ref<'manual' | 'interval'>('interval'),
    seconds = ref(30)
  const datasetDefinition = computed(
    () => datasets.value.find((d) => d.id === datasetId.value)?.definition
  )
  const parameterValues = computed(() => {
    try {
      const value = JSON.parse(params.value)
      return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
    } catch {
      return {}
    }
  })
  const fieldOptions = computed(() => {
    const binding = props.node.binding
      ? props.document.bindings[props.node.binding.instanceId]
      : undefined
    if (binding?.datasetId === datasetId.value && props.result?.fields?.length)
      return props.result.fields as Array<{ name: string; type: string; label?: string }>
    return datasetDefinition.value?.fields || []
  })
  const mapping = ref<NonNullable<BoardDataset['mapping']>>({})
  const datasetName = (id: number) =>
    datasets.value.find((d) => d.id === id)?.name || `数据集 ${id}`
  function sync() {
    const binding = props.node.binding
      ? props.document.bindings[props.node.binding.instanceId]
      : undefined
    existing.value = binding?.id || ''
    datasetId.value = binding?.datasetId || 0
    params.value = JSON.stringify(binding?.params || {}, null, 2)
    mode.value = binding?.refresh.mode || 'interval'
    seconds.value = binding?.refresh.seconds || 30
    mapping.value = { ...props.node.binding?.mapping }
  }
  function selectDataset() {
    existing.value = ''
    mapping.value = {}
    const definition = datasets.value.find((d) => d.id === datasetId.value)?.definition
    seconds.value = definition?.refreshSeconds || 30
    params.value = '{}'
  }
  function useExisting() {
    const binding = props.document.bindings[existing.value]
    if (binding) {
      datasetId.value = binding.datasetId
      params.value = JSON.stringify(binding.params, null, 2)
      mode.value = binding.refresh.mode
      seconds.value = binding.refresh.seconds
    }
  }
  function apply() {
    try {
      const values = JSON.parse(params.value)
      if (!values || Array.isArray(values) || typeof values !== 'object')
        throw new Error('参数必须是 JSON 对象')
      for (const [name, value] of Object.entries(values)) {
        const definition = datasetDefinition.value?.parameters[name]
        if (!definition) throw new Error(`未声明的参数：${name}`)
        if (value && typeof value === 'object') {
          const reference = (value as { pageParameter?: string }).pageParameter
          if (
            !reference ||
            Object.keys(value).length !== 1 ||
            props.document.parameters[reference]?.type !== definition.type
          )
            throw new Error(`请选择类型匹配的页面参数：${name}`)
        }
      }
      if (fieldOptions.value.length) {
        for (const name of [mapping.value.labelField, mapping.value.valueField].filter(Boolean)) {
          if (!fieldOptions.value.some((f) => f.name === name))
            throw new Error('映射字段不在当前输出字段中')
        }
        if (
          mapping.value.valueField &&
          !fieldOptions.value.some(
            (f) => f.name === mapping.value.valueField && f.type === 'number'
          )
        )
          throw new Error('请选择数字类型的数值字段')
      }
      emit('command', {
        type: 'bind',
        id: props.node.id,
        binding: {
          id: existing.value || `binding_${crypto.randomUUID().replaceAll('-', '')}`,
          datasetId: datasetId.value,
          ...(existing.value && props.document.bindings[existing.value]?.datasetVersion
            ? { datasetVersion: props.document.bindings[existing.value].datasetVersion }
            : {}),
          params: values,
          refresh: { mode: mode.value, seconds: seconds.value }
        },
        mapping: mapping.value
      })
    } catch (e) {
      ElMessage.error((e as Error).message)
    }
  }
  watch(() => [props.node.id, props.node.binding, props.document.bindings], sync, {
    immediate: true
  })
  onMounted(async () => {
    datasets.value = (await api.list()).items
  })
</script>
<style scoped>
  .binding-panel {
    padding: 14px;
  }
  .binding-panel p {
    font-size: 12px;
    color: #64748b;
  }
  .el-select,
  .el-input-number {
    width: 100%;
  }
  .el-alert {
    margin-top: 12px;
  }
</style>
