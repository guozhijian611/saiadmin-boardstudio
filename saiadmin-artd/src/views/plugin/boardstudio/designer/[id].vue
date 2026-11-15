<template>
  <div v-if="loading" v-loading="loading" style="height: 80vh" />
  <ElResult v-else-if="error" icon="error" title="文档加载失败" :sub-title="error" />
  <div v-else-if="document && !converted" class="conversion">
    <h2>转换为 V2 文档</h2>
    <p>将旧布局复制为嵌套文档。保存 V2 文档之前，不会修改服务器数据。</p>
    <ElButton type="primary" @click="converted = true">使用现有布局</ElButton>
    <ElButton @click="useExample">使用嵌套布局预设</ElButton>
  </div>
  <DocumentEditor
    v-else-if="document"
    ref="editor"
    :initial-document="document"
    :initially-dirty="initiallyDirty"
    :name="name"
    :on-save="save"
    ><template #publish="{ dirty }"
      ><PublishPanel
        :screen-id="id"
        :code="code"
        :dirty="dirty"
        :revision="revision"
        @revision="revision = $event" /></template
  ></DocumentEditor>
</template>
<script setup lang="ts">
  import { onMounted, ref, shallowRef, watch } from 'vue'
  import { useRoute, onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
  import { ElMessageBox } from 'element-plus'
  import request from '@/utils/http'
  import DocumentEditor from './DocumentEditor.vue'
  import PublishPanel from './PublishPanel.vue'
  import { importLegacyLayout, validateDocument } from '../document/model'
  import { operationsDocument } from '../document/example'
  import type { BoardDocument } from '../document/types'
  const route = useRoute()
  const document = shallowRef<BoardDocument>()
  const editor = ref<InstanceType<typeof DocumentEditor>>()
  const converted = ref(false)
  const initiallyDirty = ref(false)
  const loading = ref(true)
  const error = ref('')
  const name = ref('')
  const revision = ref(0)
  const code = ref('')
  let id = Number(route.params.id)
  async function load() {
    loading.value = true
    error.value = ''
    id = Number(route.params.id)
    try {
      const result = await request.get<any>({
        url: '/app/boardstudio/admin/Document/read',
        params: { id }
      })
      name.value = result.name
      revision.value = result.revision
      code.value = result.code
      converted.value = Boolean(result.document)
      initiallyDirty.value = !result.document
      const next = result.document || importLegacyLayout(result.legacy_layout)
      validateDocument(next)
      document.value = next
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }
  const useExample = () => {
    document.value = operationsDocument()
    converted.value = true
  }
  async function save(value: BoardDocument) {
    const result = await request.post<any>({
      url: '/app/boardstudio/admin/Document/save',
      data: { id, revision: revision.value, document: value }
    })
    revision.value = result.revision
  }
  async function canLeave() {
    if (!editor.value?.dirty) return true
    try {
      await ElMessageBox.confirm('文档尚未保存，确定离开？', '未保存的修改', { type: 'warning' })
      return true
    } catch {
      return false
    }
  }
  onBeforeRouteLeave(canLeave)
  onBeforeRouteUpdate(async () => {
    if (!(await canLeave())) return false
  })
  watch(
    () => route.params.id,
    () => {
      void load()
    }
  )
  onMounted(load)
</script>
<style scoped>
  .conversion {
    max-width: 620px;
    margin: 80px auto;
    padding: 32px;
    background: white;
    border-radius: 12px;
  }
</style>
