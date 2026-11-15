import { createApp, h, computed } from 'vue'
import { createI18n } from 'vue-i18n'
import zh from 'element-plus/es/locale/lang/zh-cn'
import en from 'element-plus/es/locale/lang/en'
import { ElConfigProvider } from 'element-plus'
import { createRouter, createWebHashHistory } from 'vue-router'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import 'element-plus/theme-chalk/dark/css-vars.css'
import Player from '../runtime/[code].vue'
import DocumentPlayer from './DocumentPlayer.vue'

const query = Object.fromEntries(new URLSearchParams(location.search))
const fragment = new URLSearchParams(location.hash.slice(1))
const authorization = fragment.get('authorization') || ''
// Remove preview credentials before initializing the router or rendering links.
history.replaceState(null, '', location.pathname + location.search)
const router = createRouter({ history: createWebHashHistory(), routes: [] })
const i18n = createI18n({
  legacy: false,
  locale: query.lang === 'en' ? 'en' : 'zh',
  fallbackLocale: 'zh',
  messages: { zh: {}, en: {} }
})
document.documentElement.classList.toggle('dark', query.theme === 'dark')
document.documentElement.lang = i18n.global.locale.value
const Component = query.v === '2' ? DocumentPlayer : Player
const playerProps =
  query.v === '2'
    ? { code: query.code || '', token: fragment.get('token') || query.token || '', authorization }
    : { publicCode: query.code || '', publicQuery: query, authorization }
createApp({
  setup() {
    const componentLocale = computed(() => (i18n.global.locale.value === 'en' ? en : zh))
    return () =>
      h(ElConfigProvider, { locale: componentLocale.value }, () => h(Component as any, playerProps))
  }
})
  .use(router)
  .use(ElementPlus)
  .use(i18n)
  .mount('#app')
