from pathlib import Path
import json,urllib.request,urllib.error
import argparse
parser=argparse.ArgumentParser(description='Disposable V2 release regression; modifies only the specified screen.')
parser.add_argument('--url',required=True)
parser.add_argument('--token-file',required=True)
parser.add_argument('--screen-id',required=True,type=int)
parser.add_argument('--dataset-id',required=True,type=int)
parser.add_argument('--code',required=True)
args=parser.parse_args()
base=args.url.rstrip('/');token=Path(args.token_file).read_text().strip()
def req(path,data=None,auth=True):
 headers={'Content-Type':'application/json'}
 if auth:headers['Authorization']='Bearer '+token
 request=urllib.request.Request(base+path,headers=headers,data=json.dumps(data).encode() if data is not None else None)
 try:return json.load(urllib.request.urlopen(request))
 except urllib.error.HTTPError as e:return json.load(e)
root='/app/boardstudio/admin/Document/'
a=req(root+f'read?id={args.screen_id}')['data'];doc=a['document'];dataset=args.dataset_id
doc['bindings']={'orders':{'id':'orders','datasetId':dataset,'params':{'customer':1},'refresh':{'mode':'interval','seconds':30}}}
doc['nodes']['metric_0']['binding']={'instanceId':'orders','mapping':{'valueField':'total'}}
saved=req(root+'save',{'id':args.screen_id,'revision':a['revision'],'document':doc});assert saved['code']==200,saved
rev=saved['data']['revision']
check=req(root+f'check?id={args.screen_id}');assert check['code']==200 and not check['data']['errors'],check
pub=req(root+'publish',{'id':args.screen_id,'revision':rev,'accessMode':'public','acceptWarnings':True});assert pub['code']==200,pub
release=pub['data']['releaseId'];rev=pub['data']['revision']
p=req('/app/boardstudio/api/document/'+args.code,auth=False);assert p['code']==200,p
version=p['data']['document']['bindings']['orders']['datasetVersion'];assert version>0
r=req('/app/boardstudio/api/document-data',{'code':args.code,'releaseId':release,'bindingId':'orders'},False);assert float(r['data']['rows'][0]['total'])==30,r
original=p['data']['document'];doc['nodes']['metric_0']['name']='未发布改动'
saved=req(root+'save',{'id':args.screen_id,'revision':rev,'document':doc});rev=saved['data']['revision']
assert req('/app/boardstudio/api/document/'+args.code,auth=False)['data']['document']==original
pub=req(root+'publish',{'id':args.screen_id,'revision':rev,'accessMode':'token','acceptWarnings':True});assert pub['code']==200,pub
rev=pub['data']['revision']
assert req('/app/boardstudio/api/document/'+args.code,auth=False)['code']==401
created=req('/app/boardstudio/admin/Screen/createToken',{'id':args.screen_id,'name':'V2 access regression'});assert created['code']==200,created
access=created['data']['token'];tid=created['data']['row']['id']
assert req('/app/boardstudio/api/document/'+args.code+'?token='+access,auth=False)['code']==200
r=req('/app/boardstudio/admin/Screen/changeTokenStatus',{'id':args.screen_id,'token_id':tid,'status':2});assert r['code']==200,r
assert req('/app/boardstudio/api/document/'+args.code+'?token='+access,auth=False)['code']==401
act=req(root+'activate',{'id':args.screen_id,'releaseId':release,'revision':rev});assert act['code']==200,act
assert req(root+f'read?id={args.screen_id}')['data']['document']['nodes']['metric_0']['name']=='未发布改动'
print('PASS release snapshot, pinned dataset version, public access, token revoke, rollback preserves draft')
