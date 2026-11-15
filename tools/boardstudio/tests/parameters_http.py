#!/usr/bin/env python3
"""Read-only regression against a disposable published screen with a numeric page parameter.

Requires a prepared screen whose chosen binding depends on that parameter. No temp paths,
credentials, or fixture IDs are hardcoded. URL can be the backend or a frontend API proxy.
"""
import argparse
import json
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--url', required=True)
parser.add_argument('--code', required=True)
parser.add_argument('--binding-id', required=True)
parser.add_argument('--parameter', required=True)
parser.add_argument('--field', required=True)
parser.add_argument('--values', type=float, nargs=2, required=True)
parser.add_argument('--expected', type=float, nargs=2, required=True)
parser.add_argument('--access-token-file', type=Path)
args = parser.parse_args()
headers = {'Content-Type': 'application/json'}
if args.access_token_file:
    headers['X-Boardstudio-Token'] = args.access_token_file.read_text().strip()


def request(path, payload=None):
    req = urllib.request.Request(
        args.url.rstrip('/') + path, headers=headers,
        data=None if payload is None else json.dumps(payload).encode(),
    )
    try:
        with urllib.request.urlopen(req, timeout=15) as response:
            return json.load(response)
    except urllib.error.HTTPError as error:
        return json.load(error)


published = request('/app/boardstudio/api/document/' + urllib.parse.quote(args.code, safe=''))
assert published['code'] == 200, published.get('message')
base = {'code': args.code, 'releaseId': published['data']['releaseId'], 'bindingId': args.binding_id}
for value, expected in zip(args.values, args.expected):
    result = request('/app/boardstudio/api/document-data', {**base, 'parameters': {args.parameter: value}})
    assert result['code'] == 200, result.get('message')
    assert float(result['data']['rows'][0][args.field]) == expected, 'Unexpected parameterized result'
for values in [{args.parameter: None}, {args.parameter: True}, {args.parameter: '1 OR 1=1'}, {'undeclared_parameter': 1}]:
    result = request('/app/boardstudio/api/document-data', {**base, 'parameters': values})
    assert result['code'] != 200, 'Invalid or undeclared parameter was accepted'
print('PASS published parameter switching and invalid/undeclared parameter rejection')
