<template>
  <div class="visual-query">
    <ElAlert v-if="error" :title="bt(error)" type="error" :closable="false" />
    <ElFormItem :label="bt('主表')"
      ><ElSelect v-model="value.table" filterable
        ><ElOption
          v-for="table in tables"
          :key="table"
          :label="bt(table)"
          :value="table" /></ElSelect
      ><span>{{ bt('别名 t0') }}</span></ElFormItem
    >
    <h4
      >{{ bt('关联表')
      }}<ElButton size="small" :disabled="value.joins.length >= 5" @click="addJoin">{{
        bt('添加关联')
      }}</ElButton></h4
    >
    <div v-for="(join, index) in value.joins" :key="index" class="query-row">
      <ElSelect v-model="join.type"
        ><ElOption label="LEFT JOIN" value="LEFT" /><ElOption label="INNER JOIN" value="INNER"
      /></ElSelect>
      <ElSelect v-model="join.table" filterable
        ><ElOption v-for="table in tables" :key="table" :label="bt(table)" :value="table"
      /></ElSelect>
      <span>t{{ index + 1 }}</span>
      <ElSelect v-model="join.left" filterable :placeholder="bt('左字段')"
        ><ElOption
          v-for="field in fields"
          :key="field"
          :label="bt(field)"
          :value="field" /></ElSelect
      ><span>=</span>
      <ElSelect v-model="join.right" filterable :placeholder="bt('右字段')"
        ><ElOption v-for="field in fields" :key="field" :label="bt(field)" :value="field"
      /></ElSelect>
      <ElButton @click="value.joins.splice(index, 1)">{{ bt('删除') }}</ElButton>
    </div>
    <ElAlert
      v-if="value.joins.length"
      :title="bt('一对多关联可能放大统计值，请核对聚合口径。')"
      type="warning"
      :closable="false"
    />
    <h4
      >{{ bt('输出字段')
      }}<ElButton
        size="small"
        @click="value.columns.push({ field: '', alias: '', aggregate: '' })"
        >{{ bt('添加字段') }}</ElButton
      ></h4
    >
    <div v-for="(column, index) in value.columns" :key="index" class="query-row">
      <ElSelect v-model="column.field" filterable :placeholder="bt('字段')"
        ><ElOption :label="bt('*（仅用于 COUNT）')" value="*" /><ElOption
          v-for="field in fields"
          :key="field"
          :label="bt(field)"
          :value="field"
      /></ElSelect>
      <ElSelect v-model="column.aggregate" :placeholder="bt('统计')"
        ><ElOption :label="bt('原值')" value="" /><ElOption
          v-for="fn in ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX']"
          :key="fn"
          :label="bt(fn)"
          :value="fn"
      /></ElSelect>
      <ElInput v-model="column.alias" :placeholder="bt('输出别名，如 total')" />
      <ElButton @click="value.columns.splice(index, 1)">{{ bt('删除') }}</ElButton>
    </div>
    <h4
      >{{ bt('筛选')
      }}<ElButton
        size="small"
        @click="value.conditions.push({ field: '', operator: '=', value: '' })"
        >{{ bt('添加条件') }}</ElButton
      ></h4
    >
    <div v-for="(condition, index) in value.conditions" :key="index" class="query-row">
      <ElSelect v-model="condition.field" filterable :placeholder="bt('字段')"
        ><ElOption v-for="field in fields" :key="field" :label="bt(field)" :value="field"
      /></ElSelect>
      <ElSelect v-model="condition.operator"
        ><ElOption
          v-for="operator in ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'IS NULL', 'IS NOT NULL']"
          :key="operator"
          :label="bt(operator)"
          :value="operator"
      /></ElSelect>
      <ElSelect v-model="condition.parameter" clearable :placeholder="bt('使用参数（可选）')"
        ><ElOption v-for="name in parameters" :key="name" :label="bt(name)" :value="name"
      /></ElSelect>
      <ElInput v-if="!condition.parameter" v-model="condition.value" :placeholder="bt('固定值')" />
      <ElButton @click="value.conditions.splice(index, 1)">{{ bt('删除') }}</ElButton>
    </div>
    <ElFormItem :label="bt('分组字段')"
      ><ElSelect v-model="value.groupBy" multiple filterable
        ><ElOption
          v-for="field in fields"
          :key="field"
          :label="bt(field)"
          :value="field" /></ElSelect
    ></ElFormItem>
    <h4
      >{{ bt('排序')
      }}<ElButton size="small" @click="value.orderBy.push({ field: '', direction: 'ASC' })">{{
        bt('添加排序')
      }}</ElButton></h4
    >
    <div v-for="(order, index) in value.orderBy" :key="index" class="query-row">
      <ElSelect v-model="order.field" :placeholder="bt('输出别名')"
        ><ElOption
          v-for="column in value.columns.filter((c: any) => c.alias)"
          :key="column.alias"
          :label="bt(column.alias)"
          :value="column.alias"
      /></ElSelect>
      <ElSelect v-model="order.direction"
        ><ElOption :label="bt('升序')" value="ASC" /><ElOption :label="bt('降序')" value="DESC"
      /></ElSelect>
      <ElButton @click="value.orderBy.splice(index, 1)">{{ bt('删除') }}</ElButton>
    </div>
  </div>
</template>
<script setup lang="ts">
  import { useBoardI18n } from '../i18n'
  const { bt } = useBoardI18n()

  import { computed, ref, watch } from 'vue'
  import api from './api'
  const props = defineProps<{
    modelValue: Record<string, any>
    sourceId: number
    parameters: string[]
  }>()
  const value = computed(() => props.modelValue)
  const error = ref('')
  const tables = ref<string[]>([])
  const schema = ref<Record<string, string[]>>({})
  let generation = 0
  const fields = computed(() =>
    [value.value.table, ...value.value.joins.map((j: any) => j.table)].flatMap((table, index) =>
      (schema.value[table] || []).map((field) => `t${index}.${field}`)
    )
  )
  async function loadSchema() {
    const current = ++generation
    error.value = ''
    if (!props.sourceId) {
      tables.value = []
      schema.value = {}
      return
    }
    try {
      const result = await api.sources({ id: props.sourceId })
      if (current !== generation) return
      tables.value = result.tables.map((t: any) => t.name)
      const results = await Promise.all(
        [...new Set([value.value.table, ...value.value.joins.map((j: any) => j.table)])]
          .filter(Boolean)
          .map(async (table) => ({
            table,
            result: await api.sources({ id: props.sourceId, table })
          }))
      )
      if (current !== generation) return
      schema.value = Object.fromEntries(
        results.map(({ table, result }) => [table, result.columns.map((c: any) => c.name)])
      )
    } catch (e) {
      if (current === generation) {
        schema.value = {}
        error.value = (e as Error).message
      }
    }
  }
  const addJoin = () => value.value.joins.push({ type: 'LEFT', table: '', left: '', right: '' })
  watch(
    () =>
      JSON.stringify([
        props.sourceId,
        value.value.table,
        value.value.joins.map((j: any) => j.table)
      ]),
    () => {
      schema.value = {}
      void loadSchema()
    },
    { immediate: true }
  )
</script>
<style scoped>
  .query-row {
    display: flex;
    gap: 8px;
    margin: 8px 0;
    align-items: center;
  }
  .query-row :deep(.el-select),
  .query-row :deep(.el-input) {
    flex: 1;
    min-width: 100px;
  }
  h4 {
    margin: 18px 0 8px;
  }
</style>
