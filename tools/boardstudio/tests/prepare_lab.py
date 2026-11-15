#!/usr/bin/env python3
"""Initialize a newly created, disposable saiadmin6-plugin lab and mint a test JWT.
Requires a clean official baseline; no production URL option is accepted.
"""
from pathlib import Path
import argparse,json,subprocess,time,urllib.request,urllib.parse
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--lab',type=Path,required=True)
p.add_argument('--lab-script',type=Path,required=True)
a=p.parse_args();lab=a.lab.resolve();meta=json.loads((lab/'lab.json').read_text())
assert lab.name==meta['project'] and lab.name.startswith('sai6lab-')
def compose(*args):return subprocess.check_output(['python3',str(a.lab_script),'compose',str(lab),*args],text=True)
url='http://'+compose('port','backend','8787').strip()
for _ in range(40):
 try:urllib.request.urlopen(url+'/core/install',timeout=2);break
 except Exception:time.sleep(.3)
else:raise RuntimeError('Lab install page did not start')
db=meta['db']
params={'username':'postgres' if db=='pgsql' else 'root','pass'+'word':(lab/'db-secret').read_text().strip(),'database':'saiadmin','host':'db','port':5432 if db=='pgsql' else 3306,'dbType':db,'dataType':'pure'}
result=json.load(urllib.request.urlopen(url+'/core/install/install',urllib.parse.urlencode(params).encode(),timeout=90))
assert result['code']==200, result.get('message')
(lab/'url').write_text(url)
server=lab/'app'/meta['backend']
(server/'test-token.php').write_text("<?php require __DIR__.'/vendor/autoload.php';require __DIR__.'/support/bootstrap.php';file_put_contents(__DIR__.'/test-token.json',json_encode(\\Tinywan\\Jwt\\JwtToken::generateToken(['id'=>1,'plat'=>'saiadmin','username'=>'admin'])));")
compose('exec','-T','backend','php','test-token.php')
(lab/'http-token').write_text(json.loads((server/'test-token.json').read_text())['access_token']);(lab/'http-token').chmod(0o600)
print('PASS core initialized',db,url)
