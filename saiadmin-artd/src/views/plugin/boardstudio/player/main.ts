import { createApp } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import Player from '../runtime/[code].vue'
import DocumentPlayer from './DocumentPlayer.vue'

const query = Object.fromEntries(new URLSearchParams(location.search))
const fragment = new URLSearchParams(location.hash.slice(1))
const authorization = fragment.get('authorization') || ''
// Remove preview credentials before initializing the router or rendering links.
history.replaceState(null, '', location.pathname + location.search)
const router = createRouter({ history: createWebHashHistory(), routes: [] })
createApp(
  query.v === '2' ? DocumentPlayer : Player,
  query.v === '2'
    ? { code: query.code || '', token: fragment.get('token') || query.token || '', authorization }
    : { publicCode: query.code || '', publicQuery: query, authorization }
)
  .use(router)
  .use(ElementPlus)
  .mount('#app')
