#!/usr/bin/env python3
"""V2 draft-only uninstall guard and immutable release smoke on a disposable install.
Leaves the named screen; does not clean or delete user data.
"""
from pathlib import Path
import argparse,json,uuid,urllib.request,urllib.error
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--url',required=True);p.add_argument('--token-file',type=Path,required=True)
p.add_argument('--disposable-fixture',action='store_true',required=True)
a=p.parse_args();token=a.token_file.read_text().strip()
def req(path,data=None,auth=True):
 h={'Content-Type':'application/json'}
 if auth:h['Authorization']='Bearer '+token
 r=urllib.request.Request(a.url.rstrip('/')+path,data=None if data is None else json.dumps(data).encode(),headers=h)
 try:response=urllib.request.urlopen(r,timeout=30)
 except urllib.error.HTTPError as e:response=e
 return json.load(response)
def ok(path,data=None,auth=True):
 r=req(path,data,auth);assert r['code']==200,r.get('message');return r['data']
code='v2_'+uuid.uuid4().hex[:20]
ok('/app/boardstudio/admin/Screen/save',{'name':'V2 installation fixture','code':code,'width':800,'height':600,'is_public':2})
rows=ok('/app/boardstudio/admin/Screen/index?code='+code)['data'];assert len(rows)==1
id=rows[0]['id']
placement={'x':0,'y':0,'width':800,'height':600,'zIndex':1,'grow':0,'shrink':1,'basis':'auto','columnSpan':1,'rowSpan':1}
root={'id':'root','kind':'container','name':'页面','hidden':False,'locked':False,'placement':placement,'appearance':{'background':'transparent','color':'#ffffff','borderColor':'#000000','borderWidth':0,'borderRadius':0,'opacity':1},'children':[],'layout':{'mode':'free','direction':'row','gap':0,'padding':0,'align':'stretch','justify':'start','wrap':False,'columns':3,'rowHeight':180}}
doc={'schemaVersion':2,'page':{'width':800,'height':600,'sizing':'fixed','background':'#07111f'},'rootId':'root','nodes':{'root':root},'bindings':{},'parameters':{}}
read=lambda:ok('/app/boardstudio/admin/Document/read?id='+str(id))
rev=ok('/app/boardstudio/admin/Document/save',{'id':id,'revision':read()['revision'],'document':doc})['revision']
assert req('/app/boardstudio/admin/Document/save',{'id':id,'revision':rev-1,'document':doc})['code']==409
before=read()
blocked=req('/app/saipackage/install/uninstall',{'appName':'boardstudio'})
assert blocked['code']!=200,'Populated uninstall unexpectedly allowed'
after=read();assert after==before,'Failed uninstall altered draft/schema'
release=ok('/app/boardstudio/admin/Document/publish',{'id':id,'revision':rev,'accessMode':'public','acceptWarnings':True})
public=ok('/app/boardstudio/api/document/'+code,auth=False);assert public['document']==doc
changed=json.loads(json.dumps(doc));changed['nodes']['root']['name']='changed draft'
rev=ok('/app/boardstudio/admin/Document/save',{'id':id,'revision':release['revision'],'document':changed})['revision']
assert ok('/app/boardstudio/api/document/'+code,auth=False)['document']==doc
release2=ok('/app/boardstudio/admin/Document/publish',{'id':id,'revision':rev,'accessMode':'public','acceptWarnings':True})
ok('/app/boardstudio/admin/Document/activate',{'id':id,'releaseId':release['releaseId'],'revision':release2['revision']})
assert ok('/app/boardstudio/api/document/'+code,auth=False)['document']==doc
assert read()['document']==changed
print(json.dumps({'passed':True,'screenId':id,'code':code,'checks':['save conflict','draft-only uninstall preserves all schema','public immutable snapshot','activate history preserves draft']}))
