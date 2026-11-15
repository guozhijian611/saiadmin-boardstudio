import fs from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'
import { createRequire } from 'node:module'
const require = createRequire(new URL('../../../saiadmin-artd/package.json', import.meta.url))
const { build } = await import(require.resolve('vite'))
const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'boardstudio-bootstrap-'))
const resultCode = (result) =>
  (Array.isArray(result) ? result : [result])
    .flatMap((output) => output.output)
    .filter((chunk) => chunk.type === 'chunk')
    .map((chunk) => chunk.code)
    .join('\n')
try {
  const main = path.join(dir, 'main.ts')
  const registration = path.join(dir, 'views/plugin/boardstudio/i18n/host.ts')
  await fs.mkdir(path.dirname(registration), { recursive: true })
  await fs.writeFile(main, "import.meta.glob('./views/plugin/boardstudio/i18n/host.ts', { eager: true }); export const host = 1")
  await fs.writeFile(registration, "console.log('BOARDSTUDIO_REGISTERED')")
  const compile = () => build({ configFile: false, root: dir, logLevel: 'silent', build: { write: false, lib: { entry: main, formats: ['es'] } } })
  if (!resultCode(await compile()).includes('BOARDSTUDIO_REGISTERED'))
    throw new Error('Plugin registration not bundled')
  await fs.unlink(registration)
  if (resultCode(await compile()).includes('BOARDSTUDIO_REGISTERED'))
    throw new Error('Uninstalled plugin still referenced')
  console.log('PASS optional host bootstrap builds before and after plugin removal')
} finally {
  await fs.rm(dir, { recursive: true, force: true })
}
