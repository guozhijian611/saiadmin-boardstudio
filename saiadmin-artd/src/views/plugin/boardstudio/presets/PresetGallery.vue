<template>
  <section class="preset-gallery">
    <div class="preset-filters">
      <ElSegmented v-model="selectedType" :options="localizedOptions(types)" />
      <ElInput
        v-model="keyword"
        clearable
        :placeholder="bt('搜索预设名称或用途')"
        :aria-label="bt('搜索预设')"
      />
      <ElSelect
        v-model="category"
        clearable
        :placeholder="bt('全部分类')"
        :aria-label="bt('预设分类')"
      >
        <ElOption v-for="name in categories" :key="name" :label="bt(name)" :value="name" />
      </ElSelect>
    </div>
    <p class="preset-hint">{{
      bt('插件内置预设，套用后可自由编辑。示例组件需要绑定你的数据集。')
    }}</p>
    <div v-if="items.length" class="preset-grid">
      <article v-for="item in items" :key="item.id" class="preset-card">
        <div class="preset-preview" aria-hidden="true">
          <div
            v-for="(component, index) in previewComponents(item)"
            :key="index"
            :style="previewStyle(item, component)"
            class="preset-preview-widget"
          />
        </div>
        <div class="preset-card-body">
          <h3>{{ bt(item.name) }}</h3>
          <p>{{ bt(item.description) }}</p>
          <div class="preset-card-footer">
            <ElText type="info" size="small">{{
              bt('{p0} · {p1} 个组件', { p0: bt(item.category), p1: item.component_count })
            }}</ElText>
            <ElButton type="primary" @click="$emit('select', item)">{{
              item.type === 'screen' ? bt('套用预设') : bt('插入组件')
            }}</ElButton>
          </div>
        </div>
      </article>
    </div>
    <ElEmpty v-else :description="bt('没有匹配的预设')" />
  </section>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt, localizedOptions } = useBoardI18n()

  import { computed, ref, watch } from 'vue'
  import { filterPresets, presets, type Preset, type PresetType } from '../core/presets'
  import type { BoardComponent } from '../widgets/types'
  const props = withDefaults(defineProps<{ type?: PresetType }>(), { type: 'screen' })
  defineEmits<{ select: [preset: Preset] }>()
  const selectedType = ref<PresetType>(props.type)
  const keyword = ref('')
  const category = ref('')
  const types = [
    { label: '大屏预设', value: 'screen' },
    { label: '组件预设', value: 'component' }
  ]
  watch(
    () => props.type,
    (value) => {
      selectedType.value = value
    }
  )
  watch(selectedType, () => {
    category.value = ''
  })
  const categories = computed(() => [
    ...new Set(
      presets.filter((item) => item.type === selectedType.value).map((item) => item.category)
    )
  ])
  const items = computed(() => {
    const query = keyword.value.trim().toLowerCase()
    return filterPresets(selectedType.value, '', category.value).filter(
      (item) =>
        !query ||
        [
          item.name,
          item.description,
          item.category,
          bt(item.name),
          bt(item.description),
          bt(item.category)
        ].some((value) => value.toLowerCase().includes(query))
    )
  })
  const previewComponents = (item: Preset) =>
    item.content.layout?.components || item.content.components || []
  const previewStyle = (item: Preset, component: BoardComponent) => {
    const components = previewComponents(item)
    const width =
      item.content.layout?.canvas.width ||
      Math.max(1, ...components.map((c) => c.rect.x + c.rect.w))
    const height =
      item.content.layout?.canvas.height ||
      Math.max(1, ...components.map((c) => c.rect.y + c.rect.h))
    return {
      left: `${(component.rect.x / width) * 100}%`,
      top: `${(component.rect.y / height) * 100}%`,
      width: `${(component.rect.w / width) * 100}%`,
      height: `${(component.rect.h / height) * 100}%`
    }
  }
</script>
<style scoped lang="scss">
  .preset-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
  }
  .preset-filters :deep(.el-input),
  .preset-filters :deep(.el-select) {
    width: 220px;
  }
  .preset-hint {
    margin: 16px 0;
    color: var(--el-text-color-secondary);
  }
  .preset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr));
    gap: 18px;
  }
  .preset-card {
    overflow: hidden;
    border: 1px solid var(--el-border-color);
    border-radius: 10px;
  }
  .preset-preview {
    position: relative;
    height: 160px;
    margin: 16px;
    overflow: hidden;
    background: #07111f;
    border-radius: 6px;
  }
  .preset-preview-widget {
    position: absolute;
    background: #163b5b;
    border: 2px solid #69b7ff;
    box-sizing: border-box;
  }
  .preset-card-body {
    padding: 0 16px 16px;
  }
  h3 {
    margin: 0 0 8px;
    font-size: 16px;
  }
  .preset-card-body p {
    min-height: 44px;
    color: var(--el-text-color-secondary);
  }
  .preset-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
  }
</style>
