#!/usr/bin/env python3
"""Exercise scoped publication only in a disposable local WordPress instance."""
import json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[2]
cache=Path.home()/'.cache/chinamudlogging-wordpress'
blueprint=json.loads((cache/'blueprint.json').read_text())
blueprint['steps'].append({'step':'runPHP','code':"<?php require '/wordpress/wp-load.php'; define('CML_INTEGRATION_TEST',true); require '/cml/tests/full-release.php';"})
for index,mode in enumerate(['check','apply','verify','apply']):
    condition=" || $r['changed']!==60" if index==1 else (" || $r['changed']!==0 || $r['unchanged']!==60" if index==3 else '')
    blueprint['steps'].append({'step':'runPHP','code':"<?php require '/wordpress/wp-load.php'; wp_set_current_user(1); define('CML_FULL_RELEASE_ADMIN',true); $args=['/cml-results/local-full-release.json']; $cml_full_action='"+mode+"'; ob_start(); require '/cml/scripts/publish-full-translations.php'; $r=json_decode(ob_get_clean(),true); if ($r['records']!==60"+condition+") throw new RuntimeException('Release assertion failed'); file_put_contents('/cml-results/release-"+str(index)+".json',wp_json_encode($r));"})
file=cache/'full-release-test-blueprint.json';file.write_text(json.dumps(blueprint))
command=['npx','--yes','@wp-playground/cli@3.1.62','run-blueprint','--wp=7.1.4','--php=8.3','--blueprint='+str(file)]
for source,target in [(root/'wordpress/theme/cml','/wordpress/wp-content/themes/cml'),(root/'wordpress/plugin/cml-core','/wordpress/wp-content/plugins/cml-core'),(Path.home()/'.cache/cml-wordpress/polylang','/wordpress/wp-content/plugins/polylang'),(root/'wordpress','/cml'),(cache/'bundle','/bundle'),(cache/'results','/cml-results'),(cache/'full-translation','/full-release')]:command+=['--mount-dir',str(source),target]
subprocess.run(command,cwd=root,check=True,timeout=300)
for index in range(4):
    report=json.loads((cache/'results'/f'release-{index}.json').read_text());print({key:report[key] for key in ['action','records','changed','unchanged']})
