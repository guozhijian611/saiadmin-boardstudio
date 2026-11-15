<template>
  <ElButton size="small" @click="open">{{
    resourceType === 'screen' ? bt('观看授权') : bt('授权')
  }}</ElButton>
  <ElDialog v-model="visible" :title="bt(title)" width="660px" append-to-body>
    <p>{{ hint }}</p>
    <ElTable :data="items">
      <ElTableColumn prop="subject_type" :label="bt('对象类型')" /><ElTableColumn
        prop="subject_id"
        :label="bt('对象 ID')"
      />
      <ElTableColumn prop="action" :label="bt('权限')" /><ElTableColumn :label="bt('操作')"
        ><template #default="{ row }"
          ><ElButton size="small" @click="save({ ...row, remove: true })">{{
            bt('撤销')
          }}</ElButton></template
        ></ElTableColumn
      >
    </ElTable>
    <div class="grant-form">
      <ElSelect v-model="form.subject_type" :aria-label="bt('授权对象类型')"
        ><ElOption value="user" :label="bt('用户')" /><ElOption value="role" :label="bt('角色')"
      /></ElSelect>
      <ElInputNumber v-model="form.subject_id" :min="1" :aria-label="bt('用户或角色 ID')" />
      <ElSelect v-model="form.action" :aria-label="bt('资源权限')"
        ><ElOption
          v-for="action in actions"
          :key="action.value"
          :value="action.value"
          :label="bt(action.label)"
      /></ElSelect>
      <ElButton :loading="busy" type="primary" @click="save(form)">{{ bt('添加授权') }}</ElButton>
    </div>
    <p>{{
      bt('这里的资源权限还需要对应的 SaiAdmin 菜单/API 权限。管理权限包含该资源的全部操作。')
    }}</p>
  </ElDialog>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  import { computed, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import api from '../dataset/api'
  const props = defineProps<{
    resourceType: 'screen' | 'dataset' | 'datasource'
    resourceId: number
  }>()
  const visible = ref(false),
    busy = ref(false),
    items = ref<any[]>([])
  const form = ref({
    subject_type: 'user',
    subject_id: 1,
    action: props.resourceType === 'screen' ? 'view' : 'use'
  })
  const title = computed(
    () =>
      ({ screen: '大屏观看授权', dataset: '数据集授权', datasource: '数据源授权' })[
        props.resourceType
      ]
  )
  const hint = computed(
    () =>
      ({
        screen: '观看权限只允许读取已发布大屏，不授予草稿编辑或连接查询权限。',
        dataset: '使用权限可执行已保存数据集，不能读取查询定义；编辑需要对应连接的使用权限。',
        datasource: '使用权限允许创建查询；管理权限允许修改连接和凭据，请只授予可信人员。'
      })[props.resourceType]
  )
  const actions = computed(() => [
    ...(props.resourceType === 'screen'
      ? [{ value: 'view', label: '观看' }]
      : [{ value: 'use', label: '使用' }]),
    ...(props.resourceType === 'dataset' ? [{ value: 'edit', label: '编辑' }] : []),
    { value: 'manage', label: '管理' }
  ])
  async function load() {
    items.value = (await api.grants(props.resourceType, props.resourceId)).items
  }
  async function open() {
    try {
      await load()
      visible.value = true
    } catch (e) {
      ElMessage.error(bt((e as Error).message))
    }
  }
  async function save(value: Record<string, unknown>) {
    busy.value = true
    try {
      await api.grant({
        ...value,
        resource_type: props.resourceType,
        resource_id: props.resourceId
      })
      await load()
      ElMessage.success(bt('授权已更新'))
    } catch (e) {
      ElMessage.error(bt((e as Error).message))
    } finally {
      busy.value = false
    }
  }
</script>
<style scoped>
  .grant-form {
    display: flex;
    gap: 8px;
    margin-top: 16px;
  }
  .grant-form .el-select {
    width: 130px;
  }
  p {
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }
</style>
