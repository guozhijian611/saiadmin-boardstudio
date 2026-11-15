<template>
  <ElButton type="success" @click="open">{{ bt('发布与访问') }}</ElButton>
  <ElDialog v-model="visible" :title="bt('发布与访问')" width="720px">
    <ElAlert
      v-if="dirty"
      :title="bt('请先保存当前文档，再发布。')"
      type="warning"
      :closable="false"
    />
    <ElForm label-position="top">
      <ElFormItem :label="bt('访问策略')"
        ><ElRadioGroup v-model="mode"
          ><ElRadio value="public">{{ bt('公开') }}</ElRadio
          ><ElRadio value="login">{{ bt('登录并获授权') }}</ElRadio
          ><ElRadio value="token">{{ bt('访问令牌') }}</ElRadio></ElRadioGroup
        ></ElFormItem
      >
      <ElAlert
        v-for="message in check.errors"
        :key="message"
        :title="bt(message)"
        type="error"
        :closable="false"
      />
      <ElAlert
        v-for="message in check.warnings"
        :key="message"
        :title="bt(message)"
        type="warning"
        :closable="false"
      />
      <ElCheckbox v-if="check.warnings.length" v-model="acceptWarnings">{{
        bt('我确认发布以上示例数据组件')
      }}</ElCheckbox>
      <ElButton
        type="primary"
        :loading="busy"
        :disabled="
          busy ||
          loading ||
          dirty ||
          check.errors.length > 0 ||
          (check.warnings.length > 0 && !acceptWarnings)
        "
        @click="publish"
        >{{ bt('发布已保存版本') }}</ElButton
      >
    </ElForm>
    <p
      >{{ bt('播放地址：')
      }}<a :href="url.toString()" target="_blank" rel="noopener">{{ bt('打开发布大屏') }}</a></p
    >
    <template v-if="mode === 'token'">
      <h4>{{ bt('访问令牌') }}</h4>
      <ElInput v-model="tokenName" :placeholder="bt('令牌名称')" /><ElButton @click="createToken">{{
        bt('创建令牌')
      }}</ElButton>
      <p v-if="shareLink"
        >{{ bt('请保存本次链接，完整令牌只显示一次：') }}<ElInput :model-value="shareLink" readonly
      /></p>
      <ElTable :data="tokens" size="small"
        ><ElTableColumn prop="name" :label="bt('名称')" /><ElTableColumn
          prop="token_prefix"
          :label="bt('前缀')"
        /><ElTableColumn prop="status" :label="bt('状态')" /><ElTableColumn :label="bt('操作')"
          ><template #default="{ row }"
            ><ElButton size="small" @click="disableToken(row.id)">{{
              bt('停用')
            }}</ElButton></template
          ></ElTableColumn
        ></ElTable
      >
    </template>
    <ResourceGrants v-if="mode === 'login'" resource-type="screen" :resource-id="screenId" />
    <h4>{{ bt('发布历史') }}</h4>
    <ElTable :data="releases" size="small"
      ><ElTableColumn prop="id" :label="bt('版本 ID')" /><ElTableColumn
        prop="create_time"
        :formatter="dateCell"
        :label="bt('发布时间')"
      /><ElTableColumn :label="bt('操作')"
        ><template #default="{ row }"
          ><ElButton size="small" :disabled="dirty" @click="activate(row.id)">{{
            bt('切换到此版本')
          }}</ElButton></template
        ></ElTableColumn
      ></ElTable
    >
    <p>{{ bt('切换发布版本不覆盖编辑草稿。公开播放不会返回 SQL、连接密码或 HTTP 密钥。') }}</p>
  </ElDialog>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt, locale, dateCell } = useBoardI18n()

  import { computed, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import request from '@/utils/http'
  import ResourceGrants from '../components/ResourceGrants.vue'
  import screenApi from '../api/screen'
  import { playerUrl } from '../core/player-url'
  const props = defineProps<{ screenId: number; code: string; dirty: boolean; revision: number }>()
  const emit = defineEmits<{ revision: [value: number] }>()
  const visible = ref(false),
    busy = ref(false),
    loading = ref(false),
    mode = ref('token'),
    acceptWarnings = ref(false),
    check = ref<{ errors: string[]; warnings: string[] }>({ errors: [], warnings: [] })
  const releases = ref<any[]>([]),
    tokens = ref<any[]>([]),
    tokenName = ref('大屏展示'),
    shareLink = ref('')
  const url = computed(() => {
    const value = playerUrl(props.code)
    value.searchParams.set('v', '2')
    value.searchParams.set('lang', locale.value)
    value.searchParams.set(
      'theme',
      document.documentElement.classList.contains('dark') ? 'dark' : 'light'
    )
    return value
  })
  async function refresh() {
    const [history, list] = await Promise.all([
      request.get<any>({
        url: '/app/boardstudio/admin/Document/releases',
        params: { id: props.screenId }
      }),
      screenApi.tokens({ id: props.screenId })
    ])
    releases.value = history.items
    tokens.value = list
  }
  async function open() {
    visible.value = true
    acceptWarnings.value = false
    loading.value = true
    check.value = { errors: [], warnings: [] }
    try {
      const current = await request.get<{ accessMode: string }>({
        url: '/app/boardstudio/admin/Document/read',
        params: { id: props.screenId }
      })
      mode.value = current.accessMode || 'token'
      check.value = await request.get<any>({
        url: '/app/boardstudio/admin/Document/check',
        params: { id: props.screenId }
      })
      await refresh()
    } catch (e) {
      check.value = { errors: [(e as Error).message], warnings: [] }
    } finally {
      loading.value = false
    }
  }
  async function publish() {
    busy.value = true
    try {
      const result = await request.post<any>({
        url: '/app/boardstudio/admin/Document/publish',
        data: {
          id: props.screenId,
          revision: props.revision,
          accessMode: mode.value,
          acceptWarnings: acceptWarnings.value
        }
      })
      emit('revision', result.revision)
      await refresh()
      ElMessage.success(bt('发布成功'))
    } finally {
      busy.value = false
    }
  }
  async function activate(releaseId: number) {
    const result = await request.post<any>({
      url: '/app/boardstudio/admin/Document/activate',
      data: { id: props.screenId, releaseId, revision: props.revision }
    })
    emit('revision', result.revision)
    ElMessage.success(bt('已切换发布版本'))
  }
  async function createToken() {
    const result = await screenApi.createToken({ id: props.screenId, name: tokenName.value })
    const link = new URL(url.value)
    link.hash = new URLSearchParams({ token: result.token }).toString()
    shareLink.value = link.toString()
    await refresh()
  }
  async function disableToken(tokenId: number) {
    await screenApi.changeTokenStatus({ id: props.screenId, token_id: tokenId, status: 2 })
    await refresh()
    ElMessage.success(bt('令牌已停用'))
  }
</script>
<style scoped>
  .el-alert {
    margin: 8px 0;
  }
  .el-form-item {
    margin-bottom: 12px;
  }
  h4 {
    margin-top: 22px;
  }
</style>
