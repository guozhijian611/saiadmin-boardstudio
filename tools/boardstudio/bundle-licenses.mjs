// Collect notices from packages actually included in Rollup's emitted chunks.
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
const licenseDir = fileURLToPath(new URL('./licenses/', import.meta.url))
export function bundleLicenses() {
  return {
    name: 'boardstudio-bundle-licenses',
    generateBundle(_options, bundle) {
      const roots = new Map()
      for (const chunk of Object.values(bundle)) {
        if (chunk.type !== 'chunk') continue
        for (const id of Object.keys(chunk.modules)) {
          if (!id.includes('node_modules')) continue
          let dir = path.dirname(id.replace(/^\0/, '').split('?')[0])
          while (dir !== path.dirname(dir)) {
            const manifest = path.join(dir, 'package.json')
            if (fs.existsSync(manifest)) {
              const pkg = JSON.parse(fs.readFileSync(manifest, 'utf8'))
              if (pkg.name && pkg.version) {
                roots.set(`${pkg.name}@${pkg.version}`, {dir, pkg})
                break
              }
            }
            dir = path.dirname(dir)
          }
        }
      }
      const sections = []
      for (const [id, {dir, pkg}] of [...roots.entries()].sort()) {
        const files = fs.readdirSync(dir).filter(n => /^(licen[cs]e|copying|notice)([.-]|$)/i.test(n) && fs.statSync(path.join(dir, n)).isFile())
        if (!files.length) {
          const fallback = path.join(licenseDir, `${pkg.name.replaceAll('/', '__').replace('@', '')}-${pkg.version}.txt`)
          // A fallback is version-specific; upgrades fail until its notice is reviewed.
          if (!fs.existsSync(fallback)) throw new Error(`No license file for bundled dependency: ${id}`)
          sections.push(`${id}\nLicense: ${pkg.license ?? 'See notice'}\n${fs.readFileSync(fallback, 'utf8')}`)
          continue
        }
        sections.push(`${id}\nLicense: ${pkg.license ?? 'See notice'}\n${files.sort().map(n => `${n}\n${fs.readFileSync(path.join(dir, n), 'utf8')}`).join('\n')}`)
      }
      this.emitFile({type: 'asset', fileName: 'THIRD-PARTY-LICENSES.txt', source: sections.join('\n\n' + '='.repeat(72) + '\n\n')})
    }
  }
}
