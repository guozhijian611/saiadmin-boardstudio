#!/usr/bin/env python3
"""Integrate Board Studio into a supported SaiAdmin 6 frontend. Dry run by default."""
from pathlib import Path
import argparse,difflib,re
p=argparse.ArgumentParser(description=__doc__);p.add_argument('--frontend',required=True,type=Path);p.add_argument('--apply',action='store_true');a=p.parse_args()
root=a.frontend.resolve();main=root/'src/main.ts';header=root/'src/components/core/layouts/art-header-bar/index.vue'
if not main.is_file() or not header.is_file():raise SystemExit('Unsupported host: expected src/main.ts and Art header bar')
original={main:main.read_text(),header:header.read_text()};updated=dict(original)
line="import './views/plugin/boardstudio/i18n/host'\n"
if line.strip() not in original[main]:updated[main]=line+original[main]
s=original[header]
marker="if (!router.currentRoute.value.path.startsWith('/boardstudio/')) reload(50)"
if marker not in s:
 m=re.search(r'(const changeLanguage = .*?\n  \})',s,re.S)
 if not m or m.group().count('reload(50)')!=1 or 'const router = useRouter()' not in s:
  raise SystemExit('Unsupported language handler: review host integration manually; no files changed')
 updated[header]=s[:m.start()]+m.group().replace('reload(50)',marker)+s[m.end():]
for file,new in updated.items():
 if original[file]!=new:print(''.join(difflib.unified_diff(original[file].splitlines(True),new.splitlines(True),fromfile=str(file),tofile=str(file))))
if a.apply:
 for file,new in updated.items():
  if original[file]!=new:
   backup=file.with_name(file.name+'.boardstudio.bak')
   if not backup.exists():backup.write_text(original[file])
   file.write_text(new)
 print('Applied host i18n bootstrap and plugin-only language refresh guard')
else:print('Dry run; pass --apply after reviewing this diff')
