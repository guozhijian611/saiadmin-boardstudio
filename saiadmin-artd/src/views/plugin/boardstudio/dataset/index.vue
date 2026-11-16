<template>
  <div class="datasets">
    <div class="dataset-heading"
      ><div
        ><h2>{{ bt('数据集') }}</h2
        ><p>{{ bt('保存可复用的查询定义，让多个组件使用同一批结果。') }}</p></div
      ><ElButton
        v-if="can('boardstudio:dataset:save') && can('boardstudio:dataset:sources')"
        type="primary"
        @click="create"
        >{{ bt('新建数据集') }}</ElButton
      ></div
    >
    <ElTable :data="items" v-loading="loading">
      <ElTableColumn prop="name" :label="bt('名称')" /><ElTableColumn
        prop="id"
        label="ID"
        width="80"
      />
      <ElTableColumn :label="bt('模式')"
        ><template #default="{ row }">{{
          bt(labels[row.definition.mode as keyof typeof labels])
        }}</template></ElTableColumn
      >
      <ElTableColumn prop="revision" :label="bt('版本')" width="80" />
      <ElTableColumn :label="bt('刷新 / 缓存')"
        ><template #default="{ row }"
          >{{ row.definition.refreshSeconds }}s / {{ row.definition.cacheSeconds }}s</template
        ></ElTableColumn
      >
      <ElTableColumn :label="bt('操作')" width="220"
        ><template #default="{ row }"
          ><ElButton
            v-if="
              can('boardstudio:dataset:update') &&
              can('boardstudio:dataset:sources') &&
              (row.permissions?.edit || row.permissions?.manage)
            "
            size="small"
            @click="edit(row as DatasetRecord)"
            >{{ bt('编辑') }}</ElButton
          ><ResourceGrants
            v-if="row.permissions?.manage"
            resource-type="dataset"
            :resource-id="row.id" /></template
      ></ElTableColumn>
    </ElTable>
    <ElDialog
      v-model="visible"
      :title="form.id ? bt('编辑数据集') : bt('新建数据集')"
      width="min(1050px,95vw)"
      destroy-on-close
    >
      <ElForm label-position="top">
        <div class="query-row"
          ><ElFormItem :label="bt('名称')"
            ><ElInput v-model="form.name" maxlength="60" /></ElFormItem
          ><ElFormItem :label="bt('数据源')"
            ><ElSelect v-model="form.datasource_id" @change="changeSource"
              ><ElOption
                v-for="source in sources"
                :key="source.id"
                :label="bt(source.name)"
                :value="source.id" /></ElSelect></ElFormItem
          ><ElFormItem :label="bt('查询方式')"
            ><ElSelect v-model="form.definition.mode" @change="changeMode"
              ><ElOption
                v-for="(label, mode) in labels"
                v-show="mode !== 'legacy'"
                :key="mode"
                :label="bt(label)"
                :value="mode" /></ElSelect></ElFormItem
        ></div>
        <ElTabs v-model="tab">
          <ElTabPane :label="bt('取数配置')" name="query">
            <VisualQuery
              v-if="form.definition.mode === 'visual'"
              :model-value="form.definition.query"
              :source-id="form.datasource_id"
              :parameters="parameterRows.map((parameter) => parameter.name).filter(Boolean)"
            />
            <template v-else-if="form.definition.mode === 'sql'"
              ><ElAlert
                :title="bt('只读参数化 SELECT；参数使用 :name。关联查询须核对统计口径。')"
                :closable="false" /><ElInput
                v-model="form.definition.query.sql"
                type="textarea"
                :rows="12"
                placeholder="SELECT COUNT(*) AS total FROM orders WHERE region = :region"
            /></template>
            <template v-else-if="form.definition.mode === 'http'">
              <ElFormItem :label="bt('接口路径')"
                ><ElInput v-model="form.definition.query.path" placeholder="/statistics/orders"
              /></ElFormItem>
              <ElFormItem :label="bt('请求方法')"
                ><ElSelect v-model="form.definition.query.method"
                  ><ElOption value="GET" /><ElOption value="POST" /></ElSelect
              ></ElFormItem>
              <ElFormItem :label="bt('查询参数 JSON')"
                ><ElInput
                  v-model="httpParams"
                  type="textarea"
                  :rows="3"
                  placeholder='{"region":":region"}'
              /></ElFormItem>
              <ElFormItem v-if="form.definition.query.method === 'POST'" :label="bt('请求体 JSON')"
                ><ElInput v-model="httpBody" type="textarea" :rows="3"
              /></ElFormItem>
              <ElFormItem :label="bt('结果数组路径')"
                ><ElInput v-model="form.definition.query.response_path" placeholder="data.records"
              /></ElFormItem>
            </template>
            <ElAlert
              v-else
              :title="bt('这是导入的旧版定义，可执行使用；需要修改时新建 V2 数据集。')"
              type="info"
              :closable="false"
            />
          </ElTabPane>
          <ElTabPane :label="bt('输出字段')" name="fields">
            <ElAlert
              :title="
                bt(
                  '声明字段会随数据集版本保存；执行时检查缺失、空值与类型，并只输出声明字段。留空则兼容自动推断。'
                )
              "
              :closable="false"
            />
            <div class="query-row" v-for="(field, index) in form.definition.fields" :key="index">
              <ElInput
                v-model="field.name"
                :placeholder="bt('字段名，如 total')"
                :aria-label="bt('输出字段名')"
              />
              <ElInput
                v-model="field.label"
                :placeholder="bt('显示名称')"
                :aria-label="bt('输出字段标题')"
              />
              <ElSelect v-model="field.type" :aria-label="bt('输出字段类型')"
                ><ElOption value="string" :label="bt('文本')" /><ElOption
                  value="number"
                  :label="bt('数字')" /><ElOption value="boolean" :label="bt('布尔')" /><ElOption
                  value="object"
                  :label="bt('对象或数组')"
              /></ElSelect>
              <ElCheckbox v-model="field.nullable">{{ bt('允许为空') }}</ElCheckbox>
              <ElButton @click="form.definition.fields?.splice(index, 1)">{{
                bt('删除字段')
              }}</ElButton>
            </div>
            <ElButton @click="addField">{{ bt('添加字段') }}</ElButton>
            <ElButton :disabled="!result?.fields?.length" @click="captureFields">{{
              bt('使用预览字段')
            }}</ElButton>
            <p>{{
              bt(
                '空结果仍会返回声明的字段。数据库 DECIMAL 数字字符串保留原精度；订单号等标识字段请选择文本。'
              )
            }}</p>
          </ElTabPane>
          <ElTabPane :label="bt('参数')" name="params">
            <div class="query-row" v-for="(parameter, index) in parameterRows" :key="index"
              ><ElInput v-model="parameter.name" :placeholder="bt('参数名')" /><ElSelect
                v-model="parameter.type"
                ><ElOption value="string" :label="bt('文本')" /><ElOption
                  value="number"
                  :label="bt('数字')" /><ElOption value="boolean" :label="bt('布尔')" /></ElSelect
              ><ElInput v-model="parameter.default" :placeholder="bt('默认值')" /><ElCheckbox
                v-model="parameter.required"
                >{{ bt('必填') }}</ElCheckbox
              ><ElButton @click="parameterRows.splice(index, 1)">{{ bt('删除') }}</ElButton></div
            >
            <ElButton
              @click="
                parameterRows.push({ name: '', type: 'string', default: '', required: false })
              "
              >{{ bt('添加参数') }}</ElButton
            >
          </ElTabPane>
          <ElTabPane :label="bt('数据范围')" name="scope">
            <ElFormItem :label="bt('范围策略')"
              ><ElSelect v-model="scope.mode"
                ><ElOption value="shared" :label="bt('所有获授权观看者共享结果')" /><ElOption
                  value="viewer"
                  :label="bt('按登录观看者筛选行')" /></ElSelect
            ></ElFormItem>
            <template v-if="scope.mode === 'viewer'">
              <ElAlert
                :title="
                  bt(
                    '服务器会在聚合前强制叠加范围条件；普通筛选不能覆盖。此数据集只能用于登录大屏。'
                  )
                "
                :closable="false"
              />
              <ElFormItem :label="bt('观看者身份')"
                ><ElSelect v-model="scope.identity"
                  ><ElOption value="user" :label="bt('当前用户 ID')" /><ElOption
                    value="department"
                    :label="bt('当前部门 ID')" /></ElSelect
              ></ElFormItem>
              <ElFormItem :label="bt('范围字段')"
                ><ElInput
                  v-model="scope.field"
                  :placeholder="bt('t0.owner_id 或 SQL 表别名.owner_id')"
              /></ElFormItem>
            </template>
            <p>{{
              bt('HTTP 上游的行级隔离需由上游实施，暂不提供此模式。租户映射不从页面参数推断。')
            }}</p>
          </ElTabPane>
          <ElTabPane :label="bt('刷新与限制')" name="refresh">
            <ElFormItem :label="bt('默认刷新秒数')"
              ><ElInputNumber v-model="form.definition.refreshSeconds" :min="10" :max="3600"
            /></ElFormItem>
            <ElFormItem :label="bt('缓存秒数')"
              ><ElInputNumber v-model="form.definition.cacheSeconds" :min="0" :max="3600"
            /></ElFormItem>
            <ElFormItem :label="bt('最大结果行数')"
              ><ElInputNumber v-model="form.definition.limit" :min="1" :max="5000"
            /></ElFormItem>
            <ElFormItem :label="bt('查询超时（毫秒）')"
              ><ElInputNumber v-model="form.definition.timeoutMs" :min="100" :max="10000"
            /></ElFormItem>
            <ElAlert
              v-if="form.definition.cacheSeconds > form.definition.refreshSeconds"
              :title="bt('缓存时间长于刷新间隔，重复请求可能得到同一批结果。')"
              type="warning"
              :closable="false"
            />
          </ElTabPane>
          <ElTabPane :label="bt('结果预览')" name="result">
            <ElInput
              v-model="previewParams"
              type="textarea"
              :rows="2"
              :placeholder="bt('预览参数 JSON，如 {&quot;region&quot;:&quot;east&quot;}')"
            />
            <ElButton
              v-if="can('boardstudio:dataset:preview')"
              :loading="previewing"
              @click="preview"
              >{{ bt('执行预览') }}</ElButton
            >
            <ElAlert v-if="queryError" :title="bt(queryError)" type="error" :closable="false" />
            <p v-if="result"
              >{{ bt('{p0} 行 · {p1}ms', { p0: result.total, p1: result.durationMs })
              }}<span v-if="result.truncated">{{ bt('· 已截断') }}</span></p
            >
            <ElTable v-if="result" :data="result.rows" max-height="300"
              ><ElTableColumn
                v-for="field in result.fields"
                :key="field.name"
                :prop="field.name"
                :label="bt(`${field.name} (${field.type})`)"
            /></ElTable>
          </ElTabPane>
        </ElTabs>
      </ElForm>
      <template #footer
        ><ElButton @click="visible = false">{{ bt('取消') }}</ElButton
        ><ElButton
          v-if="can(form.id ? 'boardstudio:dataset:update' : 'boardstudio:dataset:save')"
          type="primary"
          :loading="saving"
          :disabled="form.definition.mode === 'legacy'"
          @click="save"
          >{{ bt('保存新版本') }}</ElButton
        ></template
      >
    </ElDialog>
  </div>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()
  import { useBoardPermissions } from '../core/permissions'
  const { can } = useBoardPermissions()

  import { onMounted, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import api from './api'
  import { defaultDefinition, type DatasetRecord } from './types'
  import ResourceGrants from '../components/ResourceGrants.vue'
  import VisualQuery from './VisualQuery.vue'
  const labels = { visual: '可视化查询', sql: 'SQL 查询', http: 'HTTP 查询', legacy: '旧版查询' }
  const items = ref<DatasetRecord[]>([]),
    sources = ref<any[]>([]),
    loading = ref(false),
    visible = ref(false),
    saving = ref(false),
    previewing = ref(false)
  const blank = () => ({
    id: 0,
    name: '',
    datasource_id: 0,
    revision: 0,
    status: 1,
    definition: defaultDefinition()
  })
  const scope = ref<{ mode: 'shared' | 'viewer'; identity: 'user' | 'department'; field: string }>({
    mode: 'shared',
    identity: 'user',
    field: ''
  })
  const form = ref(blank()),
    tab = ref('query'),
    httpParams = ref('{}'),
    httpBody = ref('{}'),
    previewParams = ref('{}'),
    queryError = ref(''),
    result = ref<any>()
  const parameterRows = ref<
    Array<{ name: string; type: 'string' | 'number' | 'boolean'; default: any; required: boolean }>
  >([])
  async function load() {
    loading.value = true
    try {
      const [list, connections] = await Promise.all([
        api.list(),
        can('boardstudio:dataset:sources') ? api.sources() : Promise.resolve({ items: [] })
      ])
      items.value = list.items
      sources.value = connections.items
    } finally {
      loading.value = false
    }
  }
  function addField() {
    ;(form.value.definition.fields ||= []).push({
      name: '',
      label: '',
      type: 'string',
      nullable: true
    })
  }
  function captureFields() {
    form.value.definition.fields = result.value.fields.map((f: any) => ({
      name: f.name,
      label: f.label || f.name,
      type: ['string', 'number', 'boolean', 'object'].includes(f.type) ? f.type : 'string',
      nullable: true
    }))
    ElMessage.success(bt('已采用预览字段，请核对字段类型后保存'))
  }
  function create() {
    scope.value = { mode: 'shared', identity: 'user', field: '' }
    form.value = blank()
    parameterRows.value = []
    visible.value = true
    tab.value = 'query'
    httpParams.value = '{}'
    httpBody.value = '{}'
    previewParams.value = '{}'
    result.value = undefined
    queryError.value = ''
  }
  function edit(row: DatasetRecord) {
    scope.value = { mode: 'shared', identity: 'user', field: '', ...row.definition.scope }
    form.value = JSON.parse(JSON.stringify(row))
    parameterRows.value = Object.entries(row.definition.parameters).map(([name, p]) => ({
      name,
      required: Boolean(p.required),
      type: p.type,
      default: p.default ?? ''
    }))
    tab.value = 'query'
    previewParams.value = '{}'
    httpParams.value = JSON.stringify(row.definition.query.params || {}, null, 2)
    httpBody.value = JSON.stringify(row.definition.query.body || {}, null, 2)
    visible.value = true
    result.value = undefined
    queryError.value = ''
  }
  function changeMode() {
    form.value.definition.query =
      form.value.definition.mode === 'visual'
        ? defaultDefinition().query
        : form.value.definition.mode === 'sql'
          ? { sql: '' }
          : { path: '', method: 'GET', response_path: '' }
    httpParams.value = '{}'
    httpBody.value = '{}'
  }
  function changeSource() {
    const source = sources.value.find((s) => s.id === form.value.datasource_id)
    form.value.definition.mode = source?.type === 'http' ? 'http' : 'visual'
    changeMode()
  }
  function payload() {
    const value = JSON.parse(JSON.stringify(form.value))
    value.definition.scope = { ...scope.value }
    value.definition.parameters = {}
    for (const p of parameterRows.value) {
      if (!p.name || Object.hasOwn(value.definition.parameters, p.name))
        throw new Error(bt('参数名称不能为空或重复'))
      value.definition.parameters[p.name] = {
        type: p.type,
        required: p.required,
        default: p.default === '' ? null : p.default
      }
    }
    if (value.definition.mode === 'http') {
      value.definition.query.params = JSON.parse(httpParams.value)
      value.definition.query.body = JSON.parse(httpBody.value)
    }
    return value
  }
  async function save() {
    saving.value = true
    try {
      await api.save(payload())
      visible.value = false
      await load()
      ElMessage.success(bt('数据集版本已保存'))
    } catch (e) {
      ElMessage.error(bt((e as Error).message))
    } finally {
      saving.value = false
    }
  }
  async function preview() {
    previewing.value = true
    queryError.value = ''
    try {
      result.value = await api.preview({ ...payload(), params: JSON.parse(previewParams.value) })
    } catch (e) {
      queryError.value = (e as Error).message
      result.value = undefined
    } finally {
      previewing.value = false
    }
  }
  onMounted(load)
</script>
<style scoped>
  .datasets {
    padding: 20px;
  }
  .dataset-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .dataset-heading p {
    color: var(--el-text-color-secondary);
  }
  .query-row {
    display: flex;
    gap: 12px;
    margin: 12px 0;
    align-items: center;
  }
  .query-row > * {
    flex: 1;
    min-width: 100px;
  }
  .query-row .el-button {
    flex: 0;
  }
  .el-alert {
    margin: 12px 0;
  }
</style>
