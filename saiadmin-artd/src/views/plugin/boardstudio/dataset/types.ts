export interface ParameterDefinition {
  type: 'string' | 'number' | 'boolean'
  required: boolean
  default?: unknown
}
export interface DatasetField {
  name: string
  label: string
  type: 'string' | 'number' | 'boolean' | 'object'
  nullable: boolean
}
export interface DatasetDefinition {
  fields?: DatasetField[]
  scope?: { mode: 'shared' | 'viewer'; identity?: 'user' | 'department'; field?: string }
  mode: 'visual' | 'sql' | 'http' | 'legacy'
  query: Record<string, any>
  parameters: Record<string, ParameterDefinition>
  refreshSeconds: number
  cacheSeconds: number
  limit: number
  timeoutMs: number
}
export interface DatasetRecord {
  id: number
  name: string
  datasource_id: number
  revision: number
  status: number
  permissions?: { use: boolean; edit: boolean; manage: boolean }
  definition: DatasetDefinition
}
export const defaultDefinition = (): DatasetDefinition => ({
  scope: { mode: 'shared' },
  fields: [],
  mode: 'visual',
  query: { table: '', joins: [], columns: [], conditions: [], groupBy: [], orderBy: [] },
  parameters: {},
  refreshSeconds: 30,
  cacheSeconds: 15,
  limit: 500,
  timeoutMs: 5000
})
