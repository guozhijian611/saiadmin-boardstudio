#!/usr/bin/env python3
"""Create one disposable SQL dataset and verify its immutable output contracts.
Requires a MySQL source usable by the supplied account; never writes source business tables.
Leaves the versioned fixture dataset available for inspection.
"""
import argparse, copy, json, urllib.request, urllib.error
from pathlib import Path
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--url',required=True)
p.add_argument('--token-file',required=True)
p.add_argument('--source-id',type=int,required=True)
p.add_argument('--disposable-fixture',action='store_true',required=True)
a=p.parse_args()
token=Path(a.token_file).read_text().strip()
def request(action,data):
    req=urllib.request.Request(a.url.rstrip('/')+'/app/boardstudio/admin/Dataset/'+action,
        data=json.dumps(data).encode(),headers={'Content-Type':'application/json','Authorization':'Bearer '+token})
    try:r=urllib.request.urlopen(req,timeout=15)
    except urllib.error.HTTPError as e:r=e
    return json.load(r)
def success(action,data):
    result=request(action,data)
    assert result['code']==200, result.get('message')
    return result['data']
fields=[{'name':'region','label':'地区','type':'string','nullable':False},
        {'name':'total','label':'合计','type':'number','nullable':False}]
definition={'mode':'sql','scope':{'mode':'shared'},'parameters':{},'fields':fields,
 'query':{'sql':"SELECT 'east' AS region, 42 AS total, 'not exposed' AS extra"},
 'refreshSeconds':30,'cacheSeconds':0,'limit':10,'timeoutMs':1000}
base={'name':'V2 disposable output contract','datasource_id':a.source_id,'status':1,'definition':definition}
result=success('preview',base)
assert result['rows']==[{'region':'east','total':42}]
assert result['fields']==fields
for sql in ["SELECT 'east' AS region", "SELECT 'east' AS region, 'invalid' AS total", "SELECT 'east' AS region, NULL AS total"]:
    bad=copy.deepcopy(base);bad['definition']['query']['sql']=sql
    assert request('preview',bad)['code']!=200,'Invalid output contract accepted'
empty=copy.deepcopy(base);empty['definition']['query']['sql']+=' WHERE 1 = 0'
result=success('preview',empty)
assert result['rows']==[] and result['fields']==fields
saved=success('save',base)
base.update(saved)
base['definition']['query']['sql']="SELECT 'west' AS region, 84 AS total"
second=success('save',base)
assert second['revision']==saved['revision']+1
for version,expected in [(saved['revision'],42),(second['revision'],84),(saved['revision'],42)]:
    result=success('execute',{'id':saved['id'],'version':version,'params':{}})
    assert result['rows'][0]['total']==expected and result['fields']==fields
print('PASS projection, empty result schema, missing/null/wrong type rejection, immutable version execution; fixture dataset',saved['id'])
