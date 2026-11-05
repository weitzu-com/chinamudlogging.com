#!/usr/bin/env python3
import json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[2];out=Path.home()/'.cache/chinamudlogging-wordpress/product-copy-tests';out.mkdir(parents=True,exist_ok=True)
(out/'results.json').unlink(missing_ok=True)
(out/'blueprint.json').write_text(json.dumps({'steps':[{'step':'activatePlugin','pluginPath':'cml-core/cml-core.php'},{'step':'runPHP','code':"<?php define('CML_COPY_ISOLATED_TEST',true);try{require '/cml/tests/test-product-copy.php';}catch(Throwable $e){echo $e->getMessage().' AT '.$e->getFile().':'.$e->getLine();throw $e;}"}]}))
subprocess.run(['npx','--yes','@wp-playground/cli@3.1.62','run-blueprint','--wp=7.1.4','--php=8.3','--blueprint='+str(out/'blueprint.json'),'--mount-dir',str(root/'wordpress'),'/cml','--mount-dir',str(root/'wordpress/plugin/cml-core'),'/wordpress/wp-content/plugins/cml-core','--mount-dir',str(out),'/copy-results'],cwd=root,check=True,timeout=180)
result=json.loads((out/'results.json').read_text());assert result['passed']==result['total'];print(json.dumps({'passed':result['passed'],'total':result['total']}))
