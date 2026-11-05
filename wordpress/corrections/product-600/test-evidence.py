"""Negative checks in temporary copies; never mutate the submitted evidence."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parent


def manifest(root):
    entries = [hashlib.sha256(p.read_bytes()).hexdigest() + '  ' + p.relative_to(root).as_posix()
               for p in sorted(root.rglob('*')) if p.is_file() and p.name != 'SHA256SUMS']
    (root / 'SHA256SUMS').write_text('\n'.join(entries) + '\n')


def reject(mutate, expected, refresh=False):
    with tempfile.TemporaryDirectory(prefix='product600-negative-') as temp:
        root = Path(temp) / 'wordpress/corrections/product-600'
        shutil.copytree(ROOT, root)
        mutate(root)
        if refresh:
            manifest(root)
        result = subprocess.run([sys.executable, '-B', str(root / 'check-evidence.py')],
                                capture_output=True, text=True, timeout=60)
        if result.returncode == 0 or expected not in result.stderr:
            raise RuntimeError('Expected rejection ' + expected + ': ' + result.stdout + result.stderr)
        print('PASS rejection: ' + expected)


def change_overview(root):
    path = root / 'after-review.json'
    data = json.loads(path.read_text())
    data['content']['raw'] = data['content']['raw'].replace('This ultrasonic sensor bracket',
                                                         'This independently certified bracket', 1)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')


def change_generator(root):
    path = root / 'build-review.py'
    path.write_text(path.read_text().replace("'productionContentOrSpecificationWrites': 0,",
                                            "'productionContentOrSpecificationWrites': 99,"))


reject(lambda root: (root / 'evidence/before-review.json').write_bytes(b'{}\n'), 'Checksum mismatch:')
reject(lambda root: (root / 'unlisted.txt').write_text('extra'), 'cover every package file exactly')
reject(change_overview, 'Changes exceed the single HTML row', refresh=True)
reject(change_generator, 'Non-reproducible file: evidence/offline-validation.json', refresh=True)

with tempfile.TemporaryDirectory(prefix='product600-scope-') as temp:
    repo = Path(temp)
    root = repo / 'wordpress/corrections/product-600'
    shutil.copytree(ROOT, root)
    def git(*args):
        return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.DEVNULL).decode().strip()
    git('init', '-q')
    git('add', '.')
    git('-c', 'user.name=Scope Test', '-c', 'user.email=scope@example.invalid', 'commit', '-qm', 'baseline')
    base = git('rev-parse', 'HEAD')
    other = repo / 'wordpress/corrections/product-601/other.json'
    other.parent.mkdir(parents=True)
    other.write_text('{}\n')
    git('add', '.')
    git('-c', 'user.name=Scope Test', '-c', 'user.email=scope@example.invalid', 'commit', '-qm', 'unrelated product')
    head = git('rev-parse', 'HEAD')
    result = subprocess.run([sys.executable, '-B', str(root / 'check-evidence.py'), '--base', base, '--head', head],
                            capture_output=True, text=True, timeout=60)
    if result.returncode == 0 or 'PR includes files outside product600' not in result.stderr:
        raise RuntimeError('Unrelated product was not rejected: ' + result.stdout + result.stderr)
    print('PASS rejection: unrelated product in PR Git diff')
