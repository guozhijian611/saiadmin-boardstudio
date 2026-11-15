<template>
  <ElCard shadow="never">
    <template #header><h2>预设模板</h2></template>
    <PresetGallery @select="openPreset" />
  </ElCard>
</template>
<script setup lang="ts">
  import { useRouter } from 'vue-router'
  import { ElMessageBox } from 'element-plus'
  import PresetGallery from './PresetGallery.vue'
  import type { Preset } from '../core/presets'
  const router = useRouter()
  const openPreset = async (preset: Preset) => {
    try {
      await ElMessageBox.confirm(
        `进入大屏编辑器，在“${preset.type === 'screen' ? '预设模板' : '组件预设'}”中选择“${preset.name}”即可使用。`,
        '使用预设',
        { confirmButtonText: '前往大屏管理', cancelButtonText: '继续浏览' }
      )
    } catch {
      return
    }
    await router.push('/boardstudio/screen')
  }
</script>
