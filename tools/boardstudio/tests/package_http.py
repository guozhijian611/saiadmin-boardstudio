#!/usr/bin/env python3
"""Exercise the real SaiAdmin package upload/install API in a disposable environment.
Dependency installation is intentionally separate; never marks dependencies installed.
"""
from pathlib import Path
import argparse,json,time,uuid,urllib.request,urllib.error,hashlib
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--url',required=True);p.add_argument('--token-file',type=Path,required=True)
p.add_argument('--zip',type=Path,required=True);p.add_argument('--repeat',action='store_true')
p.add_argument('--disposable-fixture',required=True,action='store_true')
a=p.parse_args();token=a.token_file.read_text().strip();payload=a.zip.read_bytes()
def request(path,data=None,upload=False):
 headers={'Authorization':'Bearer '+token}
 if upload:
  boundary='boardstudio-'+uuid.uuid4().hex;headers['Content-Type']='multipart/form-data; boundary='+boundary
  data=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="boardstudio.zip"\r\nContent-Type: application/zip\r\n\r\n').encode()+payload+f'\r\n--{boundary}--\r\n'.encode()
 elif data is not None:headers['Content-Type']='application/json';data=json.dumps(data).encode()
 try:r=urllib.request.urlopen(urllib.request.Request(a.url.rstrip('/')+path,data=data,headers=headers),timeout=45)
 except urllib.error.HTTPError as e:r=e
 return json.load(r)
result=request('/app/saipackage/install/upload',upload=True)
if a.repeat:
 assert result['code']!=200,'Same-version upload unexpectedly accepted'
 print('PASS same-version upload rejected');raise SystemExit
assert result['code']==200,result.get('message')
try:
 result=request('/app/saipackage/install/install',{'appName':'boardstudio'})
 assert result['code']==200,result.get('message')
 print('PASS upload and install response',result['data'].get('state') if isinstance(result.get('data'),dict) else '')
except (urllib.error.URLError,ConnectionError,json.JSONDecodeError) as e:
 print('Install response interrupted by reload; verifying state via API:',type(e).__name__)
for _ in range(50):
 try:
  result=request('/app/boardstudio/admin/Screen/index')
  if result['code']==200:break
 except (urllib.error.URLError,ConnectionError,json.JSONDecodeError):pass
 time.sleep(.3)
else:raise AssertionError('Plugin route did not become available')
print('PASS plugin route active; SHA256',hashlib.sha256(payload).hexdigest())
