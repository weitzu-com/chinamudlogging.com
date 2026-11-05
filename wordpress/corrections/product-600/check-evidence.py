"""Read-only offline verification; rebuild only in a disposable temporary copy."""
import argparse
import copy
import hashlib
import json
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parent
PREFIX = 'wordpress/corrections/product-600/'
WORKFLOW = '.github/workflows/product-600-evidence.yml'
FROZEN = 'fea48d81c28365116ed15d696128c4de9a8cdb59'


def require(condition, message):
    if not condition:
        raise ValueError(message)


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load(name):
    return json.loads((ROOT / name).read_text(encoding='utf-8'))


def check_manifest():
    entries = {}
    for line in (ROOT / 'SHA256SUMS').read_text().splitlines():
        match = re.fullmatch(r'([0-9a-f]{64})  (.+)', line)
        require(match is not None, 'Malformed checksum entry')
        expected, name = match.groups()
        path = Path(name)
        require(not path.is_absolute() and '..' not in path.parts, 'Unsafe checksum path')
        require(name not in entries and name != 'SHA256SUMS', 'Duplicate or self checksum')
        entries[name] = expected
    files = {}
    for path in ROOT.rglob('*'):
        require(not path.is_symlink(), 'Symlink in evidence package')
        if path.is_file() and path != ROOT / 'SHA256SUMS':
            files[path.relative_to(ROOT).as_posix()] = path
    require(set(entries) == set(files), 'Checksum manifest must cover every package file exactly')
    for name, path in files.items():
        require(digest(path) == entries[name], 'Checksum mismatch: ' + name)
        if path.suffix == '.json':
            json.loads(path.read_text(encoding='utf-8'))
    return entries


class IntroductionText(HTMLParser):
    def __init__(self):
        super().__init__()
        self.parts = []

    def handle_data(self, text):
        if text.strip():
            self.parts.append(text.strip())


def check_content_scope():
    before = load('evidence/before-review.json')
    after = load('after-review.json')
    plan = load('change-plan.json')
    source = load('evidence/epec-15487160-dom-20261105.json')
    verification = load('evidence/epec-15487160-verification-20261105.json')
    provenance = load('evidence/pr8-excerpt-provenance.json')
    require((before['id'], before['type'], before['slug'], before['language']) ==
            (600, 'cml_product', 'ultrasonic-sensor-bracket-mud-logging', 'en'), 'Wrong product identity')
    fields = {'名称': '超声波传感器支架', '规格型号': '超声波体积', '说明': '传感器配件'}
    url = 'https://emall.epec.com/productd/141652/15487160'
    parser = IntroductionText()
    parser.feed(source['introductionHTML'])
    require('\n'.join(parser.parts) == source['introductionText'], 'EPEC HTML/text mismatch')
    require(source['introductionText'] == '\n'.join(k + ':' + v for k, v in fields.items()) + '\n备注:',
            'EPEC original fields changed')
    require(source['url'] == plan['epec'] == verification['sourceURL'] == url, 'Source URL mismatch')
    require(source['productNumber'] == verification['productNumber'] == '40246960-2101310115-157722016',
            'EPEC product number mismatch')
    require(plan['productId'] == 600 and plan['sourceFields'] == verification['sourceFields'] == fields,
            'Source field mapping mismatch')
    old = '<tr><th scope="row">Category note</th><td><bdi> 超声波体积 传感器配件</bdi></td></tr>'
    new = '<tr><th scope="row">Source specification label</th><td><bdi> 超声波体积</bdi></td></tr><tr><th scope="row">Category note</th><td><bdi> 传感器配件</bdi></td></tr>'
    require(before['content']['raw'].count(old) == 1, 'Original table row must be unique')
    expected = copy.deepcopy(before)
    expected['content']['raw'] = before['content']['raw'].replace(old, new, 1)
    original_specs = before['meta']['_cml_specifications']
    require(len(original_specs) == 3 and original_specs[1] ==
            {'label': 'Category note', 'raw': '超声波体积 传感器配件.', 'unit': '', 'value': ''},
            'Original meta row changed')
    replacements = [dict(label='Source specification label', raw='超声波体积', unit='', value=''),
                    dict(label='Category note', raw='传感器配件', unit='', value='')]
    require(plan['proposedRows'] == replacements, 'Plan proposes unconfirmed values')
    expected['meta']['_cml_specifications'][1:2] = replacements
    require(after == expected, 'Changes exceed the single HTML row and corresponding specification meta')
    require(load('proposed-fields.json') == {'content': after['content']['raw'],
            'meta': {'_cml_specifications': after['meta']['_cml_specifications']}}, 'Payload scope mismatch')
    require(digest(ROOT / 'evidence' / verification['snapshot']) == verification['snapshotSHA256'],
            'EPEC snapshot hash mismatch')
    for mapping in [verification['comparedProposalHashes'], provenance['unchangedEvidence']]:
        for name, expected_hash in mapping.items():
            require(digest(ROOT / name) == expected_hash, 'Frozen evidence hash mismatch: ' + name)
    report = load('evidence/offline-validation.json')
    require(report['phpSourceSHA256'] == digest(ROOT / 'export-readonly.php'), 'PHP source hash mismatch')


def check_rebuild():
    with tempfile.TemporaryDirectory(prefix='product-600-evidence-') as temp:
        target = Path(temp) / 'product-600'
        shutil.copytree(ROOT, target)
        result = subprocess.run([sys.executable, '-B', str(target / 'build-review.py')],
                                capture_output=True, text=True, timeout=30)
        require(result.returncode == 0, 'Rebuild failed: ' + result.stderr)
        original_files = {p.relative_to(ROOT) for p in ROOT.rglob('*') if p.is_file()}
        rebuilt_files = {p.relative_to(target) for p in target.rglob('*') if p.is_file()}
        require(original_files == rebuilt_files, 'Rebuild added or removed package files')
        for name in original_files:
            require((ROOT / name).read_bytes() == (target / name).read_bytes(),
                    'Non-reproducible file: ' + name.as_posix())


def check_pr_scope(base, head):
    repo = Path(__file__).resolve().parents[3]
    def git(*args):
        return subprocess.check_output(['git', '-C', str(repo), *args])
    names = git('diff', '--name-only', '-z', base + '...' + head).decode().split('\0')
    require(all(not n or n.startswith(PREFIX) or n == WORKFLOW for n in names),
            'PR includes files outside product600 package and dedicated CI workflow')
    frozen = git('ls-tree', '-r', '--name-only', FROZEN, '--', PREFIX + 'evidence/').decode().splitlines()
    require(bool(frozen), 'Frozen evidence inventory missing')
    for name in frozen:
        require(git('show', FROZEN + ':' + name) == git('show', head + ':' + name),
                'Original evidence changed in PR: ' + name)


def main():
    args = argparse.ArgumentParser()
    args.add_argument('--base')
    args.add_argument('--head')
    opts = args.parse_args()
    require(bool(opts.base) == bool(opts.head), 'Pass both --base and --head')
    check_manifest()
    check_content_scope()
    check_rebuild()
    if opts.base:
        check_pr_scope(opts.base, opts.head)
    print('PASS: manifest coverage and hashes, product600-only content, frozen evidence, byte-identical rebuild')


if __name__ == '__main__':
    try:
        main()
    except (ValueError, KeyError, OSError, subprocess.SubprocessError) as error:
        print('FAIL: ' + str(error), file=sys.stderr)
        sys.exit(1)
