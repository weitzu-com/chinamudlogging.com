"""Exercise SEO filters in a disposable WordPress, without production mutation."""
import json
import pathlib
import subprocess

root=pathlib.Path(__file__).resolve().parents[2]
out=pathlib.Path.home()/'.cache/chinamudlogging-wordpress/pagination-seo-tests'
out.mkdir(parents=True,exist_ok=True)
(out/'results.json').unlink(missing_ok=True)
(out/'blueprint.json').write_text(json.dumps({'steps':[
    {'step':'activatePlugin','pluginPath':'cml-core/cml-core.php'},
    {'step':'runPHP','code':"<?php try {define('CML_PAGINATION_ISOLATED',true);require '/cml/tests/pagination-seo.php';} catch (Throwable $e) {file_put_contents('/cml-pagination-results/failure.json',json_encode(['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]));throw $e;}"}
]}))
subprocess.run(['npx','--yes','@wp-playground/cli@3.1.62','run-blueprint',
    '--blueprint='+str(out/'blueprint.json'),'--wp=6.8','--php=8.3',
    '--mount-dir',str(root/'wordpress'),'/cml',
    '--mount-dir',str(root/'wordpress/plugin/cml-core'),'/wordpress/wp-content/plugins/cml-core',
    '--mount-dir',str(out),'/cml-pagination-results'],cwd=root,check=True,timeout=180)
result=json.loads((out/'results.json').read_text())
assert result['passed'] and result['assertions']>=200 and result['languages']==7
print(json.dumps(result))
