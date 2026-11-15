import assert from 'node:assert/strict'
import { test } from 'node:test'
import fs from 'node:fs'
import path from 'node:path'
import { createSSRApp, h, effect, stop } from '../../../saiadmin-artd/node_modules/vue'
import { renderToString } from '../../../saiadmin-artd/node_modules/vue/server-renderer'
import { createI18n } from '../../../saiadmin-artd/node_modules/vue-i18n'
import { parse, compileTemplate } from '../../../saiadmin-artd/node_modules/@vue/compiler-sfc'
import { boardTranslator, useBoardI18n } from '../../../saiadmin-artd/src/views/plugin/boardstudio/i18n'
import catalog from '../../../saiadmin-artd/src/views/plugin/boardstudio/i18n/catalog.json'

const host = () => createI18n({ legacy: false, locale: 'zh', fallbackLocale: 'zh', messages: { zh: { host: '原词条' }, en: { host: 'Host message' } } })
test('plugin messages merge into the host without changing locale or existing messages', () => {
  const i18n = host(), bt = boardTranslator(i18n.global as any)
  assert.equal(i18n.global.locale.value, 'zh')
  assert.equal(i18n.global.t('host'), '原词条')
  assert.equal(bt('保存文档'), '保存文档')
  i18n.global.locale.value = 'en'
  assert.equal(i18n.global.t('host'), 'Host message')
  assert.equal(bt('保存文档'), 'Save document')
  assert.equal(bt('组件仍使用示例数据：My custom title'), 'Widget uses sample data: My custom title')
  assert.equal(i18n.global.t('menus.boardstudio.datasets'), 'Datasets')
  assert.equal(bt('已选择 {p0} 个节点', { p0: 3 }), '3 nodes selected')
  assert.equal(bt('例如 {"Authorization":"Bearer xxx"}'), 'E.g. {"Authorization":"Bearer xxx"}')
})
test('language changes update reactive labels while business documents and identities stay intact', () => {
  const i18n = host(), bt = boardTranslator(i18n.global as any)
  const document = { nodes: { root: { name: '我的大屏', appearance: { background: '#07111f' } } }, parameters: { region: '中国' } }
  const saved = JSON.stringify(document)
  let label = '', renders = 0
  const runner = effect(() => { label = bt('名称'); renders++ })
  assert.equal(label, '名称')
  i18n.global.locale.value = 'en'
  assert.equal(label, 'Name'); assert.ok(renders > 1)
  assert.equal(JSON.stringify(document), saved)
  assert.equal(bt('customer_id'), 'customer_id')
  assert.equal(bt(42), 42)
  stop(runner)
})
test('independent apps have independent language state', () => {
  const a = host(), b = host(), at = boardTranslator(a.global as any), bt = boardTranslator(b.global as any)
  a.global.locale.value = 'en'
  assert.equal(at('数据源'), 'Data source'); assert.equal(bt('数据源'), '数据源')
})
test('the actual component hook uses its host composer', async () => {
  const i18n = host()
  const component = { setup() { const { bt } = useBoardI18n(); return () => h('button', bt('导出文档')) } }
  assert.match(await renderToString(createSSRApp(component).use(i18n)), /导出文档/)
  i18n.global.locale.value = 'en'
  assert.match(await renderToString(createSSRApp(component).use(i18n)), /Export document/)
})
test('every catalog translation retains exactly the named interpolation slots', () => {
  for (const [zh, en] of Object.entries(catalog)) {
    assert.ok(en.trim(), zh)
    assert.deepEqual(zh.match(/\{p\d+\}/g)?.sort() ?? [], en.match(/\{p\d+\}/g)?.sort() ?? [], zh)
  }
})
test('every shipped Vue template compiles and has no untranslated static Chinese UI', () => {
  const root = path.resolve('saiadmin-artd/src/views/plugin/boardstudio')
  const walk = (dir: string): string[] => fs.readdirSync(dir).flatMap(name => { const p = path.join(dir, name); return fs.statSync(p).isDirectory() ? walk(p) : [p] })
  for (const file of walk(root).filter(p => p.endsWith('.vue'))) {
    const source = fs.readFileSync(file, 'utf8'), { descriptor, errors } = parse(source, { filename: file })
    assert.deepEqual(errors, [], file)
    if (!descriptor.template || !descriptor.scriptSetup) continue
    const result = compileTemplate({ source: descriptor.template.content, filename: file, id: file })
    assert.deepEqual(result.errors, [], file)
    const visit = (node: any) => {
      if (node.type === 2) assert.ok(!/[\u4e00-\u9fff]/u.test(node.content), file + ': ' + node.content)
      if (node.type === 1) for (const prop of node.props) if (prop.type === 6 && ['label','placeholder','title','description','aria-label'].includes(prop.name)) assert.ok(!/[\u4e00-\u9fff]/u.test(prop.value?.content ?? ''), file)
      for (const child of node.children ?? []) visit(child)
    }
    visit(descriptor.template.ast)
  }
})
