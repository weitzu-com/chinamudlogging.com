"""Verify multilingual inquiry boundaries in a disposable WordPress database."""
import json, subprocess
from pathlib import Path
root = Path(__file__).resolve().parents[2]
output = Path.home()/'.cache/chinamudlogging-wordpress/ego-inquiry-fixtures'
output.mkdir(parents=True, exist_ok=True)
(output/'results.json').unlink(missing_ok=True)
blueprint = output/'blueprint.json'
blueprint.write_text(json.dumps({'steps': [
    {'step': 'activatePlugin', 'pluginPath': 'cml-core/cml-core.php'},
    {'step': 'runPHP', 'code': "<?php define('CML_EGO_QA_ISOLATED',true); require '/cml/tests/ego-qa.php';"},
]}))
subprocess.run(['npx', '--yes', '@wp-playground/cli@3.1.62', 'run-blueprint',
    '--wp=7.1.4', '--php=8.3', '--blueprint='+str(blueprint),
    '--mount-dir', str(root/'wordpress/plugin/cml-core'), '/wordpress/wp-content/plugins/cml-core',
    '--mount-dir', str(root/'wordpress'), '/cml', '--mount-dir', str(output), '/cml-qa'],
    cwd=root, check=True, timeout=180)
result = json.loads((output/'results.json').read_text())
print(json.dumps(result))
assert result['passed'] == result['total'] == 15
