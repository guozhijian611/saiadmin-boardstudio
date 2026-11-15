<template>
  <div class="binding-parameters">
    <p v-if="!Object.keys(definitions).length">{{ bt('此数据集没有查询参数。') }}</p>
    <div v-for="(definition, name) in definitions" :key="name" class="parameter">
      <label
        >{{ name }}
        <small>{{ definition.type }}{{ definition.required ? bt(' · 必填') : '' }}</small></label
      >
      <ElSelect
        :model-value="mode(name)"
        :aria-label="bt('{p0} 参数来源', { p0: name })"
        @change="setMode(name, $event)"
      >
        <ElOption value="default" :label="bt('数据集默认值')" /><ElOption
          value="literal"
          :label="bt('固定值')"
        /><ElOption value="page" :label="bt('页面参数')" />
      </ElSelect>
      <ElSelect
        v-if="mode(name) === 'page'"
        :model-value="reference(name)"
        :aria-label="bt('{p0} 页面参数', { p0: name })"
        @change="set(name, { pageParameter: $event })"
      >
        <ElOption
          v-for="(p, key) in compatible(definition.type)"
          :key="key"
          :value="key"
          :label="bt(key)"
        />
      </ElSelect>
      <template v-else-if="mode(name) === 'literal'">
        <input
          v-if="definition.type === 'number'"
          type="number"
          :value="modelValue[name] ?? ''"
          :aria-label="bt('{p0} 固定值', { p0: name })"
          @input="setNumber(name, $event)"
        />
        <ElSwitch
          v-else-if="definition.type === 'boolean'"
          :model-value="modelValue[name] === true"
          :aria-label="bt('{p0} 固定值', { p0: name })"
          @update:model-value="set(name, $event)"
        />
        <ElInput
          v-else
          :model-value="String(modelValue[name] ?? '')"
          :aria-label="bt('{p0} 固定值', { p0: name })"
          @update:model-value="set(name, $event)"
        />
      </template>
      <small v-else>{{ bt('默认：{p0}', { p0: definition.default ?? '未设置' }) }}</small>
    </div>
  </div>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  import type { ParameterDefinition } from '../dataset/types'
  import type { BoardDocument } from '../document/types'
  const props = defineProps<{
    modelValue: Record<string, unknown>
    definitions: Record<string, ParameterDefinition>
    pageParameters: BoardDocument['parameters']
  }>()
  const emit = defineEmits<{ 'update:modelValue': [value: Record<string, unknown>] }>()
  const compatible = (type: string) =>
    Object.fromEntries(Object.entries(props.pageParameters).filter(([, p]) => p.type === type))
  const mode = (name: string) =>
    !Object.hasOwn(props.modelValue, name)
      ? 'default'
      : props.modelValue[name] && typeof props.modelValue[name] === 'object'
        ? 'page'
        : 'literal'
  function reference(name: string) {
    const value = props.modelValue[name]
    return value && typeof value === 'object'
      ? (value as { pageParameter?: string }).pageParameter
      : ''
  }
  function setNumber(name: string, event: Event) {
    const input = event.target as HTMLInputElement
    set(name, input.value === '' ? null : input.valueAsNumber)
  }
  function set(name: string, value: unknown) {
    emit('update:modelValue', { ...props.modelValue, [name]: value })
  }
  function setMode(name: string, mode: string) {
    if (mode === 'default') {
      const value = { ...props.modelValue }
      delete value[name]
      emit('update:modelValue', value)
    } else if (mode === 'page')
      set(name, { pageParameter: Object.keys(compatible(props.definitions[name].type))[0] || '' })
    else {
      const p = props.definitions[name]
      set(name, p.default ?? (p.type === 'number' ? 0 : p.type === 'boolean' ? false : ''))
    }
  }
</script>
<style scoped>
  .parameter {
    display: grid;
    gap: 6px;
    margin: 12px 0;
  }
  .parameter small {
    color: var(--el-text-color-secondary);
  }
  .parameter label {
    font-size: 12px;
  }
  .parameter input {
    border: 1px solid var(--el-border-color);
    border-radius: 4px;
    padding: 6px;
    color: var(--el-text-color-primary);
    background: var(--el-bg-color);
    width: 100%;
    box-sizing: border-box;
  }
  .parameter :deep(.el-input-number) {
    width: 100%;
  }
</style>
