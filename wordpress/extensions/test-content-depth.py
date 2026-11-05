"""Run role, localization, provenance and inquiry fixtures in disposable WordPress."""
import json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[2];out=Path.home()/'.cache/chinamudlogging-wordpress/content-depth-tests';out.mkdir(parents=True,exist_ok=True)
(out/'depth-results.json').unlink(missing_ok=True)
(out/'blueprint.json').write_text(json.dumps({'steps':[{'step':'activatePlugin','pluginPath':'cml-core/cml-core.php'},{'step':'runPHP','code':"<?php define('CML_DEPTH_ISOLATED_TEST',true); try { require '/cml/extensions/test-content-depth.php'; } catch (Throwable $e) { echo $e->getMessage().' AT '.$e->getFile().':'.$e->getLine(); throw $e; }"}]}))
subprocess.run(['npx','--yes','@wp-playground/cli@3.1.62','run-blueprint','--wp=7.1.4','--php=8.3','--blueprint='+str(out/'blueprint.json'),'--mount-dir',str(root/'wordpress'),'/cml','--mount-dir',str(root/'wordpress/plugin/cml-core'),'/wordpress/wp-content/plugins/cml-core','--mount-dir',str(out),'/cml-depth-results','--mount-dir',str(out),'/cml-priority-results'],cwd=root,check=True,timeout=180)
r=json.loads((out/'depth-results.json').read_text());assert r['passed'];print(json.dumps(r))
