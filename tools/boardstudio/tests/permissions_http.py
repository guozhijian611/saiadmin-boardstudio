#!/usr/bin/env python3
"""Real HTTP authorization/data-scope regression. Only a lab.py-created disposable lab is accepted.

Creates synthetic users/departments/records and private test JWT files. Never prints credentials.
Use --setup-only on 2.1.1 before upgrading, then --existing after installing the new ZIP.
"""
import argparse
import json
import re
import shutil
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

p = argparse.ArgumentParser(description=__doc__)
p.add_argument('--lab', type=Path, required=True)
p.add_argument('--lab-script', type=Path, required=True)
p.add_argument('--disposable-fixture', action='store_true', required=True)
p.add_argument('--setup-only', action='store_true')
p.add_argument('--existing', action='store_true')
p.add_argument('--reseed', action='store_true', help='Reuse tracked test accounts after an empty uninstall/reinstall')
a = p.parse_args()
lab = a.lab.resolve()
meta = json.loads((lab / 'lab.json').read_text())
assert lab.name == meta['project'] and lab.name.startswith('sai6lab-')
server = lab / 'app' / meta['backend']
root = Path(__file__).resolve().parents[3]

def compose(*args):
    return subprocess.check_output(['python3', str(a.lab_script), 'compose', str(lab), *args], text=True)

url = 'http://' + compose('port', 'backend', '8787').strip()
(lab / 'url').write_text(url)
assert urllib.parse.urlsplit(url).hostname == '127.0.0.1'
admin = (lab / 'http-token').read_text().strip()
shutil.copy(Path(__file__).with_name('permissions_fixture.php'), server / 'permissions_fixture.php')

def fixture(mode, *args):
    try:
        output = compose('exec', '-T', 'backend', 'php', 'permissions_fixture.php', '--disposable-fixture', mode, *args)
    except subprocess.CalledProcessError as error:
        private_log = lab / 'permission-fixture-error.log'
        private_log.write_text(error.output or 'Fixture returned no diagnostic output')
        private_log.chmod(0o600)
        raise RuntimeError('Fixture failed; private diagnostic: ' + str(private_log)) from None
    if output.strip():
        print(output.strip(), flush=True)

if not a.existing:
    fixture('reseed' if a.reseed else 'setup')
state = json.loads((server / '.boardstudio-permissions.json').read_text())
if a.setup_only:
    print('PASS pre-upgrade fixture ready; private credentials remain in the lab', flush=True)
    raise SystemExit

def req(path, data=None, who='actor', method=None):
    token = admin if who == 'admin' else state['tokens'].get(who)
    headers = {'Content-Type': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    request = urllib.request.Request(url + path, data=None if data is None else json.dumps(data).encode(), headers=headers, method=method)
    try:
        response = urllib.request.urlopen(request, timeout=25)
    except urllib.error.HTTPError as error:
        response = error
    try:
        return json.load(response)
    except json.JSONDecodeError:
        raise AssertionError('Non-JSON response: ' + path) from None

def ok(path, data=None, who='actor', method=None):
    result = req(path, data, who, method)
    assert result['code'] == 200, (path, result.get('message'))
    return result['data']

def denied(path, data=None, who='actor', method=None):
    result = req(path, data, who, method)
    assert result['code'] != 200, 'Unexpected access: ' + path
    return result

def scope(value):
    role = ok('/core/role/read?id=' + str(state['roles']['actor']), who='admin')
    payload = {key: role[key] for key in ['id', 'name', 'code', 'level', 'status']}
    payload['data_scope'] = value
    ok('/core/role/update', payload, 'admin', 'PUT')

def rows(controller):
    data = ok('/app/boardstudio/admin/' + controller + '/index?name=' + state['prefix'] + '&saiType=all')
    return data['items'] if controller == 'Dataset' else data

def visible(controller, expected):
    key = {'Screen': 'screens', 'Datasource': 'sources', 'QueryTemplate': 'queries', 'Dataset': 'datasets'}[controller]
    wanted = {state[key][who] for who in expected}
    actual = {int(row['id']) for row in rows(controller) if int(row['id']) in state[key].values()}
    assert actual == wanted, (controller, actual, wanted)

denied('/app/boardstudio/admin/Screen/index', who='anonymous')
count = 0
for file in sorted((root / 'server/plugin/boardstudio/app/admin/controller').glob('*.php')):
    controller = file.stem.removesuffix('Controller')
    for slug, action in re.findall(r"#\[Permission\('[^']*',\s*'([^']*)'\)\]\s*public function (\w+)", file.read_text()):
        get = action in ['index', 'read', 'options', 'schema', 'versions', 'tokens', 'runtimeMetrics', 'sources', 'grants', 'check', 'releases']
        method = 'GET' if get else 'DELETE' if action in ['destroy', 'deleteVersion', 'deleteToken'] else 'PUT' if action == 'update' and controller != 'Dataset' else 'POST'
        result = denied(f'/app/boardstudio/admin/{controller}/{action}', None if get else {}, 'menuOnly', method)
        assert '权限不足' in result.get('message', ''), (controller, action, result.get('message'))
        count += 1
assert count >= 45
print(f'PASS {count} real admin routes reject menu-only accounts before entering business code', flush=True)
assert ok('/app/boardstudio/admin/Screen/index', who='actionOnly') is not None
denied('/app/boardstudio/admin/Screen/index', who='none')

for value, expected in [(1, ['actor', 'peer', 'child', 'other']), (2, ['other']), (3, ['actor', 'peer']), (4, ['actor', 'peer', 'child']), (5, ['actor'])]:
    if value == 2:
        fixture('custom')
    scope(value)
    for controller in ['Screen', 'Datasource', 'QueryTemplate', 'Dataset']:
        visible(controller, expected)
    page = ok('/app/boardstudio/admin/Screen/index?name=' + state['prefix'] + '&limit=1')
    assert int(page['total']) == len(expected) and len(page['data']) == min(1, len(expected))
    allowed_sources = {int(item['id']) for item in ok('/app/boardstudio/admin/Dataset/sources')['items']}
    assert allowed_sources.intersection(state['sources'].values()) == {state['sources'][key] for key in expected}
    for key, record_id in state['screens'].items():
        path = '/app/boardstudio/admin/Screen/read?id=' + str(record_id)
        (ok if key in expected else denied)(path)
    print(f'PASS role scope {value}: CRUD lists, totals, detail and resource selectors agree', flush=True)

scope(2)
fixture('custom', 'empty')
for controller in ['Screen', 'Datasource', 'QueryTemplate', 'Dataset']:
    visible(controller, [])
print('PASS empty custom departments deny all records', flush=True)
scope(5)
own = state['screens']['actor']
foreign = state['screens']['other']
own_path = '/app/boardstudio/admin/Screen/read?id=' + str(own)
foreign_path = '/app/boardstudio/admin/Screen/read?id=' + str(foreign)
before = ok(own_path, who='admin')
payload = {key: before[key] for key in ['name', 'code', 'width', 'height', 'is_public']}
ok('/app/boardstudio/admin/Screen/update', {**payload, 'id': own, 'created_by': state['users']['other'], 'published_release_id': 999999, 'draft_document': '{}', 'access_mode': 'public'}, method='PUT')
after = ok(own_path, who='admin')
assert after['created_by'] == before['created_by'] and after['published_release_id'] == before['published_release_id'] and after['draft_document'] == before['draft_document']
foreign_row = ok(foreign_path, who='admin')
denied('/app/boardstudio/admin/Screen/update', {**payload, 'id': foreign}, method='PUT')
denied('/app/boardstudio/admin/Screen/destroy', {'ids': [own, foreign]}, method='DELETE')
assert ok(foreign_path, who='admin') == foreign_row and ok(own_path, who='admin')['id'] == own
for action in ['versions', 'tokens', 'runtimeMetrics']:
    denied('/app/boardstudio/admin/Screen/' + action + '?id=' + str(foreign))
for action in ['read', 'check', 'releases']:
    denied('/app/boardstudio/admin/Document/' + action + '?id=' + str(foreign))
for action, body in [('save', {'revision': 0, 'document': state['document']}), ('publish', {'revision': 0, 'accessMode': 'public', 'acceptWarnings': True}), ('activate', {'revision': 0, 'releaseId': 1})]:
    denied('/app/boardstudio/admin/Document/' + action, {'id': foreign, **body})
assert ok(foreign_path, who='admin') == foreign_row
created_name = state['prefix'] + '_forged'
ok('/app/boardstudio/admin/Screen/save', {**payload, 'name': created_name, 'code': created_name, 'created_by': state['users']['other']})
created = ok('/app/boardstudio/admin/Screen/index?name=' + created_name + '&saiType=all')[0]
assert created['created_by'] == state['users']['actor']
print('PASS forged ownership/publish fields, foreign draft/release/token/metric access and atomic mixed-ID deletion', flush=True)

dataset = state['datasets']['peer']
execute = '/app/boardstudio/admin/Dataset/execute'
scope(3)
result = ok(execute, {'id': dataset, 'params': {}})
assert result['rows'][0]['value'] == 42
scope(5)
denied(execute, {'id': dataset, 'params': {}})
denied('/app/boardstudio/admin/Datasource/read?id=' + str(state['sources']['peer']))
denied('/app/boardstudio/admin/Dataset/grants?resource_type=dataset&resource_id=' + str(dataset))
scope(3)
assert ok(execute, {'id': dataset, 'params': {}})['rows'][0]['value'] == 42
print('PASS role-scope changes constrain resource grants and reject a warmed cached dataset immediately', flush=True)

# Preserve use-only sharing, while adding the independent row-scope gate.
grant = {'resource_type': 'dataset', 'resource_id': dataset, 'subject_type': 'user', 'subject_id': state['users']['actor']}
ok('/app/boardstudio/admin/Dataset/grant', {**grant, 'action': 'manage', 'remove': True}, 'admin')
ok('/app/boardstudio/admin/Dataset/grant', {**grant, 'action': 'use'}, 'admin')
item = next(row for row in rows('Dataset') if int(row['id']) == dataset)
assert item['definition']['query'] == {} and item['permissions'] == {'use': True, 'edit': False, 'manage': False}
denied('/app/boardstudio/admin/Dataset/update', {'id': dataset, 'revision': 1, 'name': item['name'], 'datasource_id': state['sources']['actor'], 'definition': state['definition'], 'status': 1})
ok('/app/boardstudio/admin/Dataset/grant', {**grant, 'action': 'use', 'remove': True}, 'admin')
denied(execute, {'id': dataset, 'params': {}})
scope(5)
invalid = {'resource_type': 'screen', 'resource_id': own, 'subject_type': 'user', 'subject_id': 999999999, 'action': 'view'}
denied('/app/boardstudio/admin/Dataset/grant', invalid)

# Published login ACLs are distinct from draft management scopes.
doc = ok('/app/boardstudio/admin/Document/read?id=' + str(own))
ok('/app/boardstudio/admin/Document/publish', {'id': own, 'revision': doc['revision'], 'accessMode': 'login', 'acceptWarnings': True})
view_grant = {'resource_type': 'screen', 'resource_id': own, 'subject_type': 'user', 'subject_id': state['users']['peer'], 'action': 'view'}
ok('/app/boardstudio/admin/Dataset/grant', view_grant)
code = state['prefix'] + '_actor'
ok('/app/boardstudio/api/document/' + code, who='peer')
denied('/app/boardstudio/admin/Document/read?id=' + str(own), who='peer')
ok('/app/boardstudio/admin/Dataset/grant', {**view_grant, 'remove': True})
denied('/app/boardstudio/api/document/' + code, who='peer')
# A disabled role must stop authorizing a published viewer even with a warm host identity cache.
role_grant = {**view_grant, 'subject_type': 'role', 'subject_id': state['roles']['creators']}
ok('/app/boardstudio/admin/Dataset/grant', role_grant)
ok('/app/boardstudio/api/document/' + code, who='peer')
viewer_role = ok('/core/role/read?id=' + str(state['roles']['creators']), who='admin')
role_payload = {key: viewer_role[key] for key in ['id', 'name', 'code', 'level', 'status', 'data_scope']}
ok('/core/role/update', {**role_payload, 'status': 2}, 'admin', 'PUT')
denied('/app/boardstudio/api/document/' + code, who='peer')
ok('/core/role/update', {**role_payload, 'status': 1}, 'admin', 'PUT')
ok('/app/boardstudio/api/document/' + code, who='peer')
ok('/app/boardstudio/admin/Dataset/grant', {**role_grant, 'remove': True})
print('PASS use-only sharing, grant revocation, invalid subjects and independent published-view authorization', flush=True)

# Real role-menu API invalidates already-warmed action caches for the same JWT.
role = state['roles']['actor']
menus = ok('/core/role/getMenuByRole?id=' + str(role), who='admin')
menu_ids = [int(row['id']) for row in menus['menus']]
point = next(row['id'] for row in menus['menus'] if row.get('slug') == 'boardstudio:dataset:update')
ok('/core/role/menuPermission', {'id': role, 'menu_ids': [mid for mid in menu_ids if mid != point]}, 'admin')
denied('/app/boardstudio/admin/Dataset/update', {'id': state['datasets']['actor']})
denied('/app/boardstudio/admin/Dataset/save', {'id': state['datasets']['actor'], 'name': 'cannot update through create'})
fixture('probe')
denied('/app/boardstudio/admin/Dataset/update', {'id': state['datasets']['actor']})
ok('/core/role/menuPermission', {'id': role, 'menu_ids': menu_ids}, 'admin')
print('PASS separate create/update checks, same-session permission revocation and no regrant on repeated integration', flush=True)
print('PASS complete HTTP permission and data-scope matrix on ' + meta['db'], flush=True)
