"""Offline only: generate an exact two-field proposal from the captured raw record."""
from pathlib import Path
import copy
import difflib
import hashlib
import json

ROOT = Path(__file__).resolve().parent
before_bytes = (ROOT / 'evidence/before-review.json').read_bytes()
before = json.loads(before_bytes)
assert (before['id'], before['type'], before['slug'], before['language']) == (
    600, 'cml_product', 'ultrasonic-sensor-bracket-mud-logging', 'en')
old = '<tr><th scope="row">Category note</th><td><bdi> 超声波体积 传感器配件</bdi></td></tr>'
new = '<tr><th scope="row">Source specification label</th><td><bdi> 超声波体积</bdi></td></tr><tr><th scope="row">Category note</th><td><bdi> 传感器配件</bdi></td></tr>'
assert before['content']['raw'].count(old) == 1
specs = before['meta']['_cml_specifications']
matches = [i for i, row in enumerate(specs) if row['label'] == 'Category note']
assert len(matches) == 1
index = matches[0]
assert specs[index] == {'label': 'Category note', 'raw': '超声波体积 传感器配件.', 'unit': '', 'value': ''}
plan = json.loads((ROOT / 'change-plan.json').read_text())
after = copy.deepcopy(before)
after['content']['raw'] = before['content']['raw'].replace(old, new, 1)
after['meta']['_cml_specifications'][index:index+1] = [
    {key: row[key] for key in specs[index]} for row in plan['proposedRows']
]
restored = copy.deepcopy(after)
restored['content'] = before['content']
restored['meta']['_cml_specifications'] = specs
assert restored == before
assert after['content']['raw'].replace(new, old, 1) == before['content']['raw']
assert len(after['meta']['_cml_specifications']) == len(specs) + 1
payload = {'content': after['content']['raw'], 'meta': {'_cml_specifications': after['meta']['_cml_specifications']}}
def write_json(name, value):
    (ROOT / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')
write_json('after-review.json', after)
write_json('proposed-fields.json', payload)
before_text = before_bytes.decode()
after_text = (ROOT / 'after-review.json').read_text()
(ROOT / 'exact.diff').write_text(''.join(difflib.unified_diff(before_text.splitlines(True), after_text.splitlines(True), fromfile='before-review.json', tofile='after-review.json')))
(ROOT / 'table-row.diff').write_text(''.join(difflib.unified_diff((old+'\n').splitlines(True), (new.replace('</tr><tr>', '</tr>\n<tr>')+'\n').splitlines(True), fromfile='post_content/original-row', tofile='post_content/proposed-rows')))
assert (ROOT / 'evidence/before-review.json').read_bytes() == before_bytes
write_json('evidence/offline-validation.json', {
    'result': 'PASS', 'target': 600,
    'changedPaths': ['content.raw', 'meta._cml_specifications'],
    'beforeSHA256': hashlib.sha256(before_bytes).hexdigest(),
    'originalContentSHA256': hashlib.sha256(before['content']['raw'].encode()).hexdigest(),
    'proposedContentSHA256': hashlib.sha256(after['content']['raw'].encode()).hexdigest(),
    'checks': ['identity', 'unique exact HTML row', 'unique exact meta row including punctuation', 'only two allowed paths changed', 'HTML round-trip restoration', 'unrelated meta and excerpt unchanged', 'immutable baseline'],
    'productionContentOrSpecificationWrites': 0
})
print('PASS: exact local proposal; only content.raw and meta._cml_specifications changed')
