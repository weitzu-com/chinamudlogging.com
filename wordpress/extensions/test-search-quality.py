#!/usr/bin/env python3
"""Exercise schema and sitemap boundaries in a disposable WordPress only."""
import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[2]
output = Path.home() / '.cache/chinamudlogging-wordpress/search-quality-tests'
output.mkdir(parents=True, exist_ok=True)
result_path = output / 'search-quality.json'
result_path.unlink(missing_ok=True)
blueprint = output / 'blueprint.json'
blueprint.write_text(json.dumps({'steps': [
    {'step': 'activatePlugin', 'pluginPath': 'cml-core/cml-core.php'},
    {'step': 'runPHP', 'code': "<?php define('CML_SEARCH_ISOLATED_TEST',true); require '/cml/extensions/test-search-quality.php';"},
]}))
subprocess.run([
    'npx', '--yes', '@wp-playground/cli@3.1.62', 'run-blueprint',
    '--blueprint=' + str(blueprint), '--wp=6.8', '--php=8.3',
    '--mount-dir', str(root / 'wordpress'), '/cml',
    '--mount-dir', str(root / 'wordpress/plugin/cml-core'), '/wordpress/wp-content/plugins/cml-core',
    '--mount-dir', str(output), '/cml-test-results',
], cwd=root, check=True, timeout=180)
result = json.loads(result_path.read_text())
assert result['passed'] is True and result['languages'] == 7 and result['assertions'] == 68
print(json.dumps(result))
