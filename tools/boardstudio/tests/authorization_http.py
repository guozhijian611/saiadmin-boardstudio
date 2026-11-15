#!/usr/bin/env python3
"""Destructive only to a designated disposable screen/fixture grants.
Uses two existing users with host menu permissions, no seeded account assumptions.
Publishes the supplied screen in login mode and removes fixture user grants at end.
"""
from pathlib import Path
import argparse,json,urllib.request,urllib.error
parser=argparse.ArgumentParser(description=__doc__)
for field in ['url','admin-token-file','actor-token-file','code','parameter']:
 parser.add_argument('--'+field,required=True)
for field in ['actor-id','screen-id','source-id','dataset-id']:
 parser.add_argument('--'+field,type=int,required=True)
parser.add_argument('--value',type=float,required=True)
parser.add_argument('--disposable-fixture',action='store_true',required=True)
args=parser.parse_args()
admin=Path(args.admin_token_file).read_text().strip();actor=Path(args.actor_token_file).read_text().strip();url=args.url.rstrip('/');aid=args.actor_id

def req(path,data=None,who=admin):
 r=urllib.request.Request(url+path,data=json.dumps(data).encode() if data is not None else None,headers={'Content-Type':'application/json','Authorization':'Bearer '+who})
 try: response=urllib.request.urlopen(r,timeout=15)
 except urllib.error.HTTPError as error: response=error
 body=response.read().decode()
 if not body.startswith('{'): raise AssertionError('Non-JSON response from '+path)
 return json.loads(body)
dataset=args.dataset_id
grant={'resource_type':'dataset','resource_id':dataset,'subject_type':'user','subject_id':aid,'action':'use'}
sg={'resource_type':'screen','resource_id':args.screen_id,'subject_type':'user','subject_id':aid,'action':'view'}
for resource in [grant,sg]:
 existing=req('/app/boardstudio/admin/Dataset/grants?resource_type='+resource['resource_type']+'&resource_id='+str(resource['resource_id']))['data']['items']
 if any(row['subject_type']=='user' and int(row['subject_id'])==aid for row in existing):
  raise AssertionError('Fixture user already has direct resource grants; use a clean disposable user')
try:
 assert req('/app/boardstudio/admin/Dataset/grant',grant)['code']==200
 r=req('/app/boardstudio/admin/Dataset/index',who=actor);assert r['code']==200,r
 item=next(x for x in r['data']['items'] if x['id']==dataset);assert item['definition']['query']=={} and item['permissions']=={'use':True,'edit':False,'manage':False},item
 r=req('/app/boardstudio/admin/Dataset/execute',{'id':dataset,'params':{args.parameter:args.value}},actor);assert r['code']==200,r
 r=req(f'/app/boardstudio/admin/Datasource/read?id={args.source_id}',who=actor);assert r['code']!=200,r
 assert req('/app/boardstudio/admin/Dataset/grant',{**grant,'remove':True})['code']==200
 r=req('/app/boardstudio/admin/Dataset/execute',{'id':dataset,'params':{args.parameter:args.value}},actor);assert r['code']!=200,r
 screen=req(f'/app/boardstudio/admin/Document/read?id={args.screen_id}')['data']
 p=req('/app/boardstudio/admin/Document/publish',{'id':args.screen_id,'revision':screen['revision'],'accessMode':'login','acceptWarnings':True});assert p['code']==200,p
 assert req('/app/boardstudio/api/document/'+args.code,who=actor)['code']!=200
 sg={'resource_type':'screen','resource_id':args.screen_id,'subject_type':'user','subject_id':aid,'action':'view'}
 assert req('/app/boardstudio/admin/Dataset/grant',sg)['code']==200
 r=req('/app/boardstudio/api/document/'+args.code,who=actor);assert r['code']==200,r
 assert req(f'/app/boardstudio/admin/Document/read?id={args.screen_id}',who=actor)['code']!=200
 assert req('/app/boardstudio/admin/Dataset/grant',{**sg,'remove':True})['code']==200
 assert req('/app/boardstudio/api/document/'+args.code,who=actor)['code']!=200
 print('PASS non-admin HTTP: use-only query hidden; execute allowed; connection/draft denied; dataset and screen revocation immediate')
finally:
 for resource in [grant,sg]:
  req('/app/boardstudio/admin/Dataset/grant',{**resource,'remove':True})
