#!/usr/bin/env python3
"""Validate distribution contents without a SaiAdmin host."""
from pathlib import Path
import argparse, configparser, hashlib, re, zipfile
p=argparse.ArgumentParser(description=__doc__);p.add_argument('zip',type=Path);a=p.parse_args()
version=(Path(__file__).resolve().parents[2]/'VERSION').read_text().strip()
with zipfile.ZipFile(a.zip) as z:
    assert z.testzip() is None, 'Corrupt archive'
    names=z.namelist();assert len(names)==len(set(names)), 'Duplicate ZIP paths'
    for n in names:
        assert n in {'info.ini','config.json'} or n.startswith(('plugin/boardstudio/','saiadmin-artd/src/views/plugin/boardstudio/')), n
        assert not n.startswith('/') and '..' not in Path(n).parts, n
        assert not any(x in Path(n).parts for x in ['node_modules','.git','vendor','__pycache__']), n
        assert not Path(n).name.startswith('.env'), n
        assert not n.startswith('plugin/boardstudio/runtime/'), n
        assert not re.search(rb'(?:ghp_|gho_|github_pat_)[A-Za-z0-9_]{20,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',z.read(n)), n
    info=configparser.ConfigParser();info.read_string('[package]\n'+z.read('info.ini').decode());assert info['package']['version']==version
    assert info['package']['support']=='6.x'
    for n in ['plugin/boardstudio/PROJECT-LICENSE','plugin/boardstudio/FRONTEND-LICENSE','plugin/boardstudio/public/player/THIRD-PARTY-LICENSES.txt','plugin/boardstudio/public/player/index.html']:
        assert n in names,n
    html=z.read('plugin/boardstudio/public/player/index.html').decode()
    for asset in re.findall(r'(?:src|href)="\./([^\"]+)"',html):assert 'plugin/boardstudio/public/player/'+asset in names,asset
print('PASS package paths, version, licenses, asset references and sensitive-file exclusions')
print(hashlib.sha256(a.zip.read_bytes()).hexdigest(),a.zip.name)
