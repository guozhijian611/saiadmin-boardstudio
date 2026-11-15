<template>
  <form
    v-if="Object.keys(document.parameters).length"
    class="parameter-filters"
    @submit.prevent="apply"
  >
    <label v-for="(definition, name) in document.parameters" :key="name"
      >{{ name }}
      <select v-if="definition.type === 'boolean'" v-model="values[name]"
        ><option :value="true">{{ bt('是') }}</option
        ><option :value="false">{{ bt('否') }}</option></select
      >
      <input
        v-else
        v-model="values[name]"
        :type="definition.type === 'number' ? 'number' : 'text'"
        :aria-label="bt('筛选 {p0}', { p0: name })"
      />
    </label>
    <button type="submit">{{ bt('应用筛选') }}</button
    ><span role="alert">{{ bt(error) }}</span>
  </form>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  import { reactive, ref, watch } from 'vue'
  import type { BoardDocument } from '../document/types'
  import { resolvePageParameters } from '../document/parameters'
  const props = defineProps<{ document: BoardDocument }>()
  const emit = defineEmits<{ apply: [values: Record<string, unknown>] }>()
  const values = reactive<Record<string, any>>({}),
    error = ref('')
  watch(
    () => JSON.stringify(props.document.parameters),
    () => {
      for (const key of Object.keys(values)) delete values[key]
      Object.assign(values, resolvePageParameters(props.document))
    },
    { immediate: true }
  )
  function apply() {
    try {
      emit('apply', resolvePageParameters(props.document, values))
      error.value = ''
    } catch (e) {
      error.value = (e as Error).message
    }
  }
</script>
<style scoped>
  .parameter-filters {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: var(--el-bg-color-overlay);
    color: var(--el-text-color-primary);
    font-size: 13px;
    flex-wrap: wrap;
  }
  .parameter-filters label {
    display: flex;
    gap: 6px;
    align-items: center;
  }
  .parameter-filters input,
  .parameter-filters select {
    width: 130px;
    color: var(--el-text-color-primary);
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color);
    border-radius: 4px;
    padding: 4px;
  }
  .parameter-filters button {
    padding: 4px 12px;
    color: var(--el-text-color-primary);
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color);
    border-radius: 4px;
  }
  .parameter-filters span {
    color: var(--el-color-danger);
  }
</style>
