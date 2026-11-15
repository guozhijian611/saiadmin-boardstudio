<template>
  <section v-if="definition">
    <h4>{{ definition.name }}配置</h4>
    <ElFormItem
      v-for="property in definition.properties"
      :key="property.key"
      :label="property.label"
    >
      <ElSwitch
        v-if="property.type === 'boolean'"
        :model-value="Boolean(node.widget?.props[property.key])"
        @change="set(property.key, $event)"
      />
      <ElInputNumber
        v-else-if="property.type === 'number'"
        :model-value="numberValue(property.key)"
        :min="property.min"
        :max="property.max"
        :step="property.key === 'opacity' ? 0.05 : 1"
        clearable
        @change="set(property.key, $event)"
      />
      <ElSelect
        v-else-if="property.type === 'select'"
        :model-value="String(node.widget?.props[property.key] ?? '')"
        @change="set(property.key, $event)"
        ><ElOption
          v-for="option in property.options"
          :key="option.value"
          :label="option.label"
          :value="option.value"
      /></ElSelect>
      <ElInput
        v-else
        :model-value="String(node.widget?.props[property.key] ?? '')"
        @change="set(property.key, $event)"
      />
    </ElFormItem>
  </section>
</template>
<script setup lang="ts">
  import { computed } from 'vue'
  import { ElMessage } from 'element-plus'
  import { widgetDefinition, validateWidgetProperties } from '../widgets/definitions'
  import type { DocumentNode } from '../document/types'
  import type { DocumentCommand } from '../document/commands'
  const props = defineProps<{ node: DocumentNode }>()
  const emit = defineEmits<{ command: [command: DocumentCommand] }>()
  const definition = computed(() => widgetDefinition(props.node.widget?.type || ''))
  const numberValue = (key: string) =>
    typeof props.node.widget?.props[key] === 'number'
      ? (props.node.widget.props[key] as number)
      : undefined
  function set(key: string, value: unknown) {
    const config = { ...props.node.widget!.props }
    if (value === undefined || value === null) delete config[key]
    else config[key] = value
    try {
      validateWidgetProperties(props.node.widget!.type, config)
      emit('command', {
        type: 'patch',
        id: props.node.id,
        patch: { widget: { ...props.node.widget!, props: config } }
      })
    } catch (e) {
      ElMessage.error((e as Error).message)
    }
  }
</script>
