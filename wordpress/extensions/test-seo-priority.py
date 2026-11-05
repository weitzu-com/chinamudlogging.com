"""Verify new filters with existing extensions in disposable WordPress only."""
import json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[2]
out=Path.home()/'.cache/chinamudlogging-wordpress/seo-priority-tests';out.mkdir(parents=True,exist_ok=True)
(out/'results.json').unlink(missing_ok=True)
(out/'blueprint.json').write_text(json.dumps({'steps':[
 {'step':'activatePlugin','pluginPath':'cml-core/cml-core.php'},
 {'step':'runPHP','code':"<?php define('CML_PRIORITY_ISOLATED_TEST',true); try { require '/cml/extensions/test-seo-priority.php'; } catch (Throwable $e) { echo $e->getMessage().' LINE '.$e->getLine().' FILE '.$e->getFile().' TRACE '.$e->getTraceAsString(); throw $e; }"}]}))
subprocess.run(['npx','--yes','@wp-playground/cli@3.1.62','run-blueprint','--wp=7.1.4','--php=8.3','--blueprint='+str(out/'blueprint.json'),'--mount-dir',str(root/'wordpress'),'/cml','--mount-dir',str(root/'wordpress/plugin/cml-core'),'/wordpress/wp-content/plugins/cml-core','--mount-dir',str(out),'/cml-priority-results'],cwd=root,check=True,timeout=180)
r=json.loads((out/'results.json').read_text());assert r['passed'];print(json.dumps(r))
