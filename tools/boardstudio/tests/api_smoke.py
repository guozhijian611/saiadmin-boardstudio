#!/usr/bin/env python3
"""Disposable-environment smoke. Requires a normal admin JWT in a local file."""
import argparse, json, uuid, urllib.request, urllib.error
p = argparse.ArgumentParser()
p.add_argument('--url', required=True)
p.add_argument('--token-file', required=True)
a = p.parse_args()
with open(a.token_file) as f: token = f.read().strip()
def request(path, body=None, auth=True, method=None):
    headers = {'Content-Type':'application/json'}
    if auth: headers['Authorization'] = 'Bearer ' + token
    req = urllib.request.Request(a.url.rstrip('/') + path, headers=headers,
        data=json.dumps(body).encode() if body is not None else None, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as response: return json.load(response)
    except urllib.error.HTTPError as e:
        try: return json.load(e)
        except ValueError: raise RuntimeError(f'HTTP {e.code} from {path}') from None
code = 'test_' + uuid.uuid4().hex[:20]
root = '/app/boardstudio/admin/Screen/'
assert request(root+'save', {'name': '临时验收 '+code, 'code': code, 'width':1920,'height':1080,'is_public':1})['code']==200
rows=request(root+'index?code='+code)['data']['data']; assert len(rows)==1
screen_id=rows[0]['id']
layout={'canvas':{'width':1920,'height':1080},'components':[
    {'id':'test_title','type':'decor-title','title':'Board Studio','rect':{'x':40,'y':40,'w':1840,'h':110,'z':1},'dataset':{},'option':{'text':'独立插件测试'}}
]}
def save(revision, value=layout): return request(root+'saveLayout', {'id':screen_id,'revision':revision,'layout':value})
assert save(0)['data']['revision']==1
assert save(0)['code']==409
assert request(root+'publish', {'id':screen_id,'revision':0})['code']==409
assert request(root+'publish', {'id':screen_id,'revision':1})['code']==200
assert request('/app/boardstudio/api/screen/'+code, auth=False)['code']==200
assert save(1, {**layout,'components':layout['components']*2})['code']!=200
assert request(root+f'read?id={screen_id}')['data']['revision']==1
assert request(root+f'read?id={screen_id}',auth=False)['code']!=200
versions=request(root+f'versions?id={screen_id}')
assert versions['code']==200
print(json.dumps({'passed':True,'screen_id':screen_id,'code':code,'checks':['create','save','conflict','publish conflict','public read','invalid layout rollback','unauthorized read','versions']},ensure_ascii=False))
# Keep the screen as browser acceptance evidence; caller owns cleanup of disposable lab.
