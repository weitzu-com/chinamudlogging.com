#!/usr/bin/env python3
"""Run fixtures only in an isolated Playground; never seed the production database."""
import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[2]
output = Path.home() / '.cache/chinamudlogging-wordpress/sensor-selection-tests'
output.mkdir(parents=True, exist_ok=True)
blueprint = output / 'blueprint.json'
result_path = output / 'sensor-integration.json'
result_path.unlink(missing_ok=True)
blueprint.write_text(json.dumps({'steps': [
    {'step': 'activatePlugin', 'pluginPath': 'cml-core/cml-core.php'},
    {'step': 'runPHP', 'code': "<?php define('CML_SENSOR_ISOLATED_TEST',true); require '/cml/extensions/test-sensor-selection.php';"},
]}))
subprocess.run([
    'npx', '--yes', '@wp-playground/cli@3.1.62', 'run-blueprint',
    '--blueprint=' + str(blueprint), '--wp=6.8', '--php=8.3',
    '--mount-dir', str(root / 'wordpress'), '/cml',
    '--mount-dir', str(root / 'wordpress/plugin/cml-core'), '/wordpress/wp-content/plugins/cml-core',
    '--mount-dir', str(output), '/cml-test-results',
], cwd=root, check=True, timeout=180)
result = json.loads(result_path.read_text())
assert result['passed'] is True and result['products'] == 26 and result['assertions'] == 265
print(json.dumps(result))
