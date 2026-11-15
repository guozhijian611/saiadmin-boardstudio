import axios from 'axios'

export default {
  async screen(code: string, params: Record<string, unknown> = {}, signal?: AbortSignal) {
    return this.request(
      `/app/boardstudio/api/screen/${encodeURIComponent(code)}`,
      params,
      signal,
      true
    )
  },
  async data(params: Record<string, unknown>, signal?: AbortSignal) {
    return this.request('/app/boardstudio/api/data', params, signal)
  },
  async request(url: string, data: Record<string, unknown>, signal?: AbortSignal, get = false) {
    const { admin_authorization, ...params } = data
    const response = await axios.request({
      url,
      method: get ? 'GET' : 'POST',
      ...(get ? { params } : { data: params }),
      baseURL: window.location.pathname.split('/app/boardstudio/player')[0] || '/',
      timeout: 15000,
      signal,
      validateStatus: () => true,
      headers: admin_authorization ? { Authorization: String(admin_authorization) } : {}
    })
    const body = response.data
    if (response.status !== 200 || body.code !== 200) {
      const error = Object.assign(new Error(body.message || body.msg || '请求失败'), {
        code: Number(body.code || response.status)
      })
      throw error
    }
    return body.data
  }
}
