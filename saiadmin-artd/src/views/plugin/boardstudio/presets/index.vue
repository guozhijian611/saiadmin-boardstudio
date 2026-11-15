<template>
  <ElCard shadow="never">
    <template #header
      ><h2>{{ bt('预设模板') }}</h2></template
    >
    <PresetGallery @select="openPreset" />
  </ElCard>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  import { useRouter } from 'vue-router'
  import { ElMessageBox } from 'element-plus'
  import PresetGallery from './PresetGallery.vue'
  import type { Preset } from '../core/presets'
  const router = useRouter()
  const openPreset = async (preset: Preset) => {
    try {
      await ElMessageBox.confirm(
        bt('进入大屏编辑器，在“{p0}”中选择“{p1}”即可使用。', {
          p0: preset.type === 'screen' ? '预设模板' : '组件预设',
          p1: preset.name
        }),
        bt('使用预设'),
        { confirmButtonText: bt('前往大屏管理'), cancelButtonText: bt('继续浏览') }
      )
    } catch {
      return
    }
    await router.push('/boardstudio/screen')
  }
</script>
