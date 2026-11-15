#!/usr/bin/env python3
"""Create deterministic SaiAdmin install and optional source ZIPs from this checkout."""
from pathlib import Path
import argparse
import hashlib
import json
import os
import re
import subprocess
import time
import zipfile

ROOT = Path(__file__).resolve().parents[2]
VERSION = (ROOT / 'VERSION').read_text().strip()
if not re.fullmatch(r'\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?', VERSION):
    raise SystemExit('VERSION must be a semantic version')
p = argparse.ArgumentParser(description=__doc__)
p.add_argument('--output', type=Path, required=True)
p.add_argument('--source-output', type=Path)
p.add_argument('--skip-build', action='store_true')
a = p.parse_args()
if not a.skip_build:
    subprocess.run(['node', str(ROOT / 'tools/boardstudio/build-player.mjs')], check=True, cwd=ROOT)
backend = ROOT / 'server/plugin/boardstudio'
frontend = ROOT / 'saiadmin-artd/src/views/plugin/boardstudio'
if not (backend / 'public/player/index.html').is_file():
    raise SystemExit('Missing player build')
# Stable ZIP metadata: reproducible for the same file bytes and source epoch.
epoch = max(int(os.environ.get('SOURCE_DATE_EPOCH', '315532800')), 315532800)
date = time.gmtime(epoch)[:6]
def write(z, name, data):
    info = zipfile.ZipInfo(name, date)
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = 0o100644 << 16
    z.writestr(info, data)
def allowed(path):
    return (path.is_file() and not path.is_symlink() and path.name != '.DS_Store'
            and '__pycache__' not in path.parts and path.suffix not in {'.pyc', '.map'})
install = {}
for base, prefix in [(backend, 'plugin/boardstudio'), (frontend, 'saiadmin-artd/src/views/plugin/boardstudio')]:
    for path in sorted(base.rglob('*')):
        if allowed(path):
            install[str(Path(prefix) / path.relative_to(base))] = path.read_bytes()
install['plugin/boardstudio/PROJECT-LICENSE'] = (ROOT / 'LICENSE').read_bytes()
install['plugin/boardstudio/THIRD_PARTY_NOTICES.md'] = (ROOT / 'THIRD_PARTY_NOTICES.md').read_bytes()
install['info.ini'] = (f'app = boardstudio\ntitle = Board Studio\nabout = 独立大屏编辑与播放插件\nauthor = guozhijian611\nwebsite = https://github.com/guozhijian611/saiadmin-boardstudio\nversion = {VERSION}\nsupport = 6.x\n').encode()
install['config.json'] = json.dumps({'dependencies': {'echarts': '^6.0.0', 'vue3-draggable-resizable': '^1.6.5', 'vue-i18n': '^9.14.0'}}, indent=2).encode()
a.output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(a.output, 'w') as z:
    for name, data in sorted(install.items()):
        write(z, name, data)
print(a.output.resolve())
print('SHA256', hashlib.sha256(a.output.read_bytes()).hexdigest())
if a.source_output:
    # Explicit source roots prevent local configuration, keys and build cache leakage.
    paths = []
    for folder in ['server/plugin/boardstudio', 'saiadmin-artd/src/views/plugin/boardstudio', 'tools/boardstudio', 'docs', '.github']:
        paths.extend(x for x in (ROOT / folder).rglob('*') if allowed(x))
    files = ['VERSION', 'README.md', 'LICENSE', 'THIRD_PARTY_NOTICES.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'SECURITY.md', 'AGENTS.md', '.gitignore', '.gitattributes', 'saiadmin-artd/package.json', 'saiadmin-artd/pnpm-lock.yaml', 'saiadmin-artd/eslint.config.mjs', 'saiadmin-artd/.auto-import.json', 'saiadmin-artd/.prettierrc', 'saiadmin-artd/.prettierignore']
    paths.extend(ROOT / name for name in files)
    a.source_output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(a.source_output, 'w') as z:
        for path in sorted(set(paths)):
            write(z, f'boardstudio-{VERSION}/{path.relative_to(ROOT)}', path.read_bytes())
    print(a.source_output.resolve())
    print('SOURCE SHA256', hashlib.sha256(a.source_output.read_bytes()).hexdigest())
