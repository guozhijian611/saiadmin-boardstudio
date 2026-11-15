import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<any>({ url: '/app/boardstudio/admin/Screen/index', params })
  },
  read(id: number | string) {
    return request.get<any>({ url: '/app/boardstudio/admin/Screen/read', params: { id } })
  },
  save(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/save', data })
  },
  update(data: Record<string, any>) {
    return request.put<any>({ url: '/app/boardstudio/admin/Screen/update', data })
  },
  delete(data: Record<string, any>) {
    return request.del<any>({ url: '/app/boardstudio/admin/Screen/destroy', data })
  },
  changeStatus(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/changeStatus', data })
  },
  saveLayout(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/saveLayout', data })
  },
  publish(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/publish', data })
  },
  copy(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/copy', data })
  },
  generateFromTable(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/generateFromTable', data })
  },
  versions(params: Record<string, any>) {
    return request.get<any>({ url: '/app/boardstudio/admin/Screen/versions', params })
  },
  restoreVersion(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/restoreVersion', data })
  },
  deleteVersion(data: Record<string, any>) {
    return request.del<any>({ url: '/app/boardstudio/admin/Screen/deleteVersion', data })
  },
  tokens(params: Record<string, any>) {
    return request.get<any>({ url: '/app/boardstudio/admin/Screen/tokens', params })
  },
  createToken(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/createToken', data })
  },
  resetToken(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/resetToken', data })
  },
  changeTokenStatus(data: Record<string, any>) {
    return request.post<any>({ url: '/app/boardstudio/admin/Screen/changeTokenStatus', data })
  },
  deleteToken(data: Record<string, any>) {
    return request.del<any>({ url: '/app/boardstudio/admin/Screen/deleteToken', data })
  },
  runtimeMetrics(params: Record<string, any> = {}) {
    return request.get<any>({ url: '/app/boardstudio/admin/Screen/runtimeMetrics', params })
  }
}
