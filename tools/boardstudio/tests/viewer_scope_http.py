#!/usr/bin/env python3
"""Read-only viewer-scope checks against a prepared login-only disposable screen.
Both JWT users must already have screen view permission. Expected results must differ.
"""
import argparse,json,urllib.request,urllib.error,urllib.parse
from pathlib import Path
p=argparse.ArgumentParser(description=__doc__)
for key in ['url','code','binding-id','field','first-token-file','second-token-file']:
    p.add_argument('--'+key,required=True)
p.add_argument('--expected',nargs=2,type=float,required=True)
a=p.parse_args()
assert a.expected[0]!=a.expected[1], 'Fixture must distinguish viewers'
tokens=[Path(a.first_token_file).read_text().strip(),Path(a.second_token_file).read_text().strip()]
def request(path,token=None,data=None):
    r=urllib.request.Request(a.url.rstrip('/')+path,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json',**({'Authorization':'Bearer '+token} if token else {})})
    try:response=urllib.request.urlopen(r,timeout=15)
    except urllib.error.HTTPError as error:response=error
    return json.load(response)
path='/app/boardstudio/api/document/'+urllib.parse.quote(a.code,safe='')
assert request(path)['code']!=200, 'Anonymous viewer accepted'
for index in [0,1,0,1]:
    document=request(path,tokens[index]);assert document['code']==200,document.get('message')
    base={'code':a.code,'releaseId':document['data']['releaseId'],'bindingId':a.binding_id}
    result=request('/app/boardstudio/api/document-data',tokens[index],base)
    assert result['code']==200,result.get('message')
    assert float(result['data']['rows'][0][a.field])==a.expected[index], 'Viewer cache/scope mismatch'
    injected=request('/app/boardstudio/api/document-data',tokens[index],{**base,'parameters':{'undeclared_scope_identity':1}})
    assert injected['code']!=200, 'Client identity injection accepted'
print('PASS distinct viewer results, cache isolation, anonymous rejection, undeclared identity rejection')
