import request from '@/utils/http'
import type { DatasetRecord } from './types'
const root = '/app/boardstudio/admin/Dataset/'
export default {
  list: () => request.get<{ items: DatasetRecord[] }>({ url: root + 'index' }),
  sources: (params: Record<string, unknown> = {}) =>
    request.get<any>({ url: root + 'sources', params }),
  save: (data: Record<string, unknown>) =>
    request.post<{ id: number; revision: number }>({ url: root + 'save', data }),
  preview: (data: Record<string, unknown>) => request.post<any>({ url: root + 'preview', data }),
  execute: (
    id: number,
    params: Record<string, unknown> = {},
    version?: number,
    signal?: AbortSignal
  ) => request.post<any>({ url: root + 'execute', data: { id, params, version }, signal }),
  grants: (resource_type: string, resource_id: number) =>
    request.get<any>({ url: root + 'grants', params: { resource_type, resource_id } }),
  grant: (data: Record<string, unknown>) => request.post<any>({ url: root + 'grant', data })
}
