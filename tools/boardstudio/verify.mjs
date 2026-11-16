import { spawnSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
for (const [command, args] of [
  [path.join(root, 'saiadmin-artd/node_modules/.bin/tsx'), ['--test', 'tools/boardstudio/tests/core.test.ts', 'tools/boardstudio/tests/document.test.ts', 'tools/boardstudio/tests/i18n.test.ts']],
  ['python3', ['tools/boardstudio/tests/integration_cli.py']],
  [process.execPath, ['tools/boardstudio/tests/bootstrap.mjs']],
  ['php', ['tools/boardstudio/tests/frontend_integration.php']],
  ['php', ['-d', 'zend.assertions=1', 'tools/boardstudio/tests/layout.php']],
  ['php', ['-d', 'zend.assertions=1', 'tools/boardstudio/tests/document.php']],
  ['php', ['-d', 'zend.assertions=1', 'tools/boardstudio/tests/dataset.php']],
  ['php', ['-d', 'zend.assertions=1', 'tools/boardstudio/tests/credentials.php']],
  ['php', ['tools/boardstudio/tests/permissions.php']],
  [path.join(root, 'saiadmin-artd/node_modules/.bin/eslint'), ['src/views/plugin/boardstudio']]
]) {
  const cwd = command.endsWith('/eslint') ? path.join(root, 'saiadmin-artd') : root
  const result = spawnSync(command, args, {cwd, stdio:'inherit'})
  if (result.status !== 0) process.exit(result.status || 1)
}
