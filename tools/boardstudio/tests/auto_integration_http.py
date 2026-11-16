#!/usr/bin/env python3
"""Lifecycle test for an already installed disposable SaiAdmin lab; destroys its plugin fixtures."""
from pathlib import Path
import argparse,subprocess,sys,json,time,shutil,urllib.request,urllib.error,zipfile
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--lab',required=True,type=Path);p.add_argument('--lab-script',required=True,type=Path)
p.add_argument('--zip',required=True,type=Path);p.add_argument('--old-zip',required=True,type=Path)
p.add_argument('--manual-old',action='store_true');p.add_argument('--disposable-fixture',required=True,action='store_true')
p.add_argument('--permissions',action='store_true',help='Seed old-version role grants and run the full permission matrix after upgrade')
a=p.parse_args();lab=a.lab.resolve();meta=json.loads((lab/'lab.json').read_text());assert lab.name==meta['project'] and lab.name.startswith('sai6lab-')
root=Path(__file__).resolve().parents[3];front=lab/'app'/meta['frontend'];server=lab/'app'/meta['backend'];main=front/'src/main.ts';header=front/'src/components/core/layouts/art-header-bar/index.vue';role=front/'src/views/system/role/modules/permission-dialog.vue'
token=(lab/'http-token').read_text().strip()
def compose(*args):return subprocess.check_output([sys.executable,str(a.lab_script),'compose',str(lab),*args],text=True)
def request(path,data=None):
 r=urllib.request.Request((lab/'url').read_text().strip()+path,data=None if data is None else json.dumps(data).encode(),headers={'Authorization':'Bearer '+token,'Content-Type':'application/json'})
 try:response=urllib.request.urlopen(r,timeout=45)
 except urllib.error.HTTPError as error:response=error
 return json.load(response)
def ok(path,data=None):
 result=request(path,data);assert result['code']==200,result.get('message');return result['data']
def stable():
 compose('restart','backend');port=compose('port','backend','8787').strip().rsplit(':',1)[1];(lab/'url').write_text('http://127.0.0.1:'+port)
 for _ in range(40):
  try:ok('/core/system/user');return
  except Exception:time.sleep(.25)
 raise AssertionError('Host did not stabilize')
def snapshot():return (main.read_bytes(),header.read_bytes(),role.read_bytes(),(server/'plugin/saiadmin/app/logic/system/SystemRoleLogic.php').read_bytes())
def integrated():
 assert main.read_text().count('// <boardstudio:bootstrap>')==1
 assert header.read_text().count('// <boardstudio:language-refresh>')==1
 assert main.read_text().count('i18n/host.ts')==1
 assert header.read_text().count("startsWith('/boardstudio/')")==1
 assert role.read_text().count('<!-- <boardstudio:permission-label> -->')==1
 assert role.read_text().count('__boardstudioMenuTitle(data.label)')==1
 if meta['db']=='pgsql':assert (server/'plugin/saiadmin/app/logic/system/SystemRoleLogic.php').read_text().count('// <boardstudio:role-menu-insert>')==1

def state():return json.loads(compose('exec','-T','backend','php','auto-integration-state.php'))
def complete():print(compose('exec','-T','backend','php','install_probe.php').strip(),flush=True)
def install(package):
 subprocess.run([sys.executable,str(root/'tools/boardstudio/tests/package_http.py'),'--url',(lab/'url').read_text().strip(),'--token-file',str(lab/'http-token'),'--zip',str(package),'--disposable-fixture'],check=True)
 stable()
def uninstall():
 compose('exec','-T','backend','php','uninstall_permissions.php','--disposable-fixture','capture')
 ok('/app/saipackage/install/uninstall',{'appName':'boardstudio'});stable()
 print(compose('exec','-T','backend','php','uninstall_permissions.php','--disposable-fixture','verify').strip(),flush=True)
 assert not (server/'plugin/boardstudio').exists()
 assert 'boardstudio:bootstrap' not in main.read_text() and 'boardstudio:language-refresh' not in header.read_text()
 assert 'boardstudio:permission-label' not in role.read_text()
 assert 'boardstudio:role-menu-insert' not in (server/'plugin/saiadmin/app/logic/system/SystemRoleLogic.php').read_text()

def cleanup():compose('exec','-T','backend','php','cleanup_fixture.php','--disposable-fixture')
for name in ['install_probe.php','cleanup_fixture.php','uninstall_permissions.php']:shutil.copy(root/'tools/boardstudio/tests'/name,server/name)
(server/'auto-integration-state.php').write_text("<?php require __DIR__.'/vendor/autoload.php';require __DIR__.'/support/bootstrap.php';$i=new \\plugin\\saipackage\\app\\logic\\InstallLogic('boardstudio');$v=\\support\\think\\Db::table('phinxlog_boardstudio')->column('version');echo json_encode(['state'=>$i->getInstallState(),'count'=>count($v)]);")
(server/'auto-complete.php').write_text("<?php require __DIR__.'/vendor/autoload.php';require __DIR__.'/support/bootstrap.php';(new \\plugin\\saipackage\\app\\logic\\InstallLogic('boardstudio'))->dependentInstallComplete('npm');")
# Initial installation must already have integrated itself; no integration command is called here.
integrated();complete();assert state()['count']==10
first=snapshot()
subprocess.run([sys.executable,str(root/'tools/boardstudio/tests/v2_install_http.py'),'--url',(lab/'url').read_text().strip(),'--token-file',str(lab/'http-token'),'--disposable-fixture'],check=True)
assert snapshot()==first,'Data-protected uninstall changed host integration'
cleanup()
main.write_bytes(main.read_bytes()+b'// user main edit after installation\n')
header.write_bytes(header.read_bytes()+b'\n<!-- user header edit after installation -->\n')
role.write_bytes(role.read_bytes()+b'\n<!-- user role edit after installation -->\n')
uninstall()
assert b'user main edit after installation' in main.read_bytes() and b'user header edit after installation' in header.read_bytes()
assert b'user role edit after installation' in role.read_bytes()
plain=snapshot()
print('PASS data-protected uninstall leaves host intact; empty uninstall cleans only owned fragments',flush=True)
# Same ZIP reinstall and repeated application are idempotent.
install(a.zip);integrated();complete()
source=snapshot();compose('exec','-T','backend','php','plugin/boardstudio/bin/integrate-frontend.php','--frontend','/app/'+meta['frontend'],'--apply')
assert snapshot()==source,'Repeated integration changed host files'
cleanup();uninstall();assert snapshot()==plain,'Reinstall/uninstall lost external edits'
# Unrecognized host must fail without a half-patched main file, then be recoverable.
header.write_text('<!-- intentionally unsupported disposable test header -->\n')
subprocess.run([sys.executable,str(root/'tools/boardstudio/tests/package_http.py'),'--url',(lab/'url').read_text().strip(),'--token-file',str(lab/'http-token'),'--zip',str(a.zip),'--disposable-fixture'],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
stable();failed=state();assert failed['state']!=1 and failed['count']==8,failed
assert main.read_bytes()==plain[0] and 'intentionally unsupported' in header.read_text()
if meta['db']=='pgsql':
 count=compose('exec','-T','db','psql','-U','postgres','-d','saiadmin','-tAc',"SELECT count(*) FROM pg_stat_activity WHERE datname=current_database() AND state='idle in transaction'")
 assert int(count.strip())==0,'Failure left a PostgreSQL transaction open'
header.write_bytes(plain[1]);ok('/app/saipackage/install/install',{'appName':'boardstudio'});stable();integrated();complete()
print('PASS unsupported host rejection, no partial frontend patch, pending install state and retry recovery',flush=True)
# Upgrade from the public previous version, both with and without its optional manual setup.
cleanup();uninstall();assert snapshot()==plain
install(a.old_zip)
with zipfile.ZipFile(a.old_zip) as old,zipfile.ZipFile(a.zip) as new:
 assert old.read('config.json')==new.read('config.json'),'Upgrade fixture requires matching already-installed dependencies'
compose('exec','-T','backend','php','auto-complete.php')
if a.manual_old:
 subprocess.run([sys.executable,str(server/'plugin/boardstudio/bin/integrate-frontend.py'),'--frontend',str(front),'--apply'],check=True,stdout=subprocess.DEVNULL)
if a.permissions:
    fixture_args=['--reseed'] if (server/'.boardstudio-permissions.json').exists() else []
    subprocess.run([sys.executable,str(root/'tools/boardstudio/tests/permissions_http.py'),'--lab',str(lab),'--lab-script',str(a.lab_script),'--setup-only','--disposable-fixture',*fixture_args],check=True)
code='auto_upgrade_fixture'
ok('/app/boardstudio/admin/Screen/save',{'name':'Automatic integration upgrade fixture','code':code,'width':800,'height':600,'is_public':2})
row=ok('/app/boardstudio/admin/Screen/index?code='+code)['data'][0];before=ok('/app/boardstudio/admin/Screen/read?id='+str(row['id']))
install(a.zip);integrated();complete();after=ok('/app/boardstudio/admin/Screen/read?id='+str(row['id']));assert after==before
print('PASS previous ZIP upgrade preserves screen data and adopts existing setup; manual-old='+str(a.manual_old),flush=True)
if a.permissions:
    print(compose('exec','-T','backend','php','permissions_fixture.php','--disposable-fixture','upgrade-check').strip(),flush=True)
    subprocess.run([sys.executable,str(root/'tools/boardstudio/tests/permissions_http.py'),'--lab',str(lab),'--lab-script',str(a.lab_script),'--existing','--disposable-fixture'],check=True)
print('PASS automatic integration lifecycle on '+meta['db'],flush=True)
