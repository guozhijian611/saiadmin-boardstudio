import { bundleLicenses } from './bundle-licenses.mjs'
import { createRequire } from 'node:module'
const require = createRequire(new URL('../../saiadmin-artd/package.json', import.meta.url))
const { build } = await import(require.resolve('vite'))
const { default: vue } = await import(require.resolve('@vitejs/plugin-vue'))
const { default: AutoImport } = await import(require.resolve('unplugin-auto-import/vite'))
import path from 'node:path'
import { fileURLToPath } from 'node:url'
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
await build({
  configFile: false,
  root: path.join(root, 'saiadmin-artd/src/views/plugin/boardstudio/player'),
  base: './',
  plugins: [bundleLicenses(), vue(), AutoImport({ imports: ['vue', 'vue-router'], dts: false })],
  build: {
    outDir: path.join(root, 'server/plugin/boardstudio/public/player'), emptyOutDir: true,
    chunkSizeWarningLimit: 2500
  },
  define: { 'import.meta.env.VITE_API_URL': JSON.stringify('/') }
})
