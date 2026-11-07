import json
import pathlib
import subprocess

root = pathlib.Path(__file__).resolve().parents[2]
out = pathlib.Path.home() / '.cache/chinamudlogging-wordpress/legacy-query-tests'
out.mkdir(mode=0o700, parents=True, exist_ok=True)
(out / 'result.json').unlink(missing_ok=True)
(out / 'blueprint.json').write_text(json.dumps({'steps': [{'step': 'runPHP', 'code': "<?php require '/cml/tests/legacy-query.php';"}]}))
subprocess.run(['npx', '--yes', '@wp-playground/cli@3.1.62', 'run-blueprint', '--wp=6.8', '--php=8.3',
                '--blueprint=' + str(out / 'blueprint.json'), '--mount-dir', str(root / 'wordpress'), '/cml',
                '--mount-dir', str(out), '/cml-query-tests'], cwd=root, check=True, timeout=180)
result = json.loads((out / 'result.json').read_text())
assert result['passed'] and result['scenarios'] == 45
print(json.dumps(result))
