import json
import pathlib
import subprocess

root=pathlib.Path(__file__).resolve().parents[2]
out=pathlib.Path.home()/'.cache/chinamudlogging-wordpress/source-content-tests'
out.mkdir(parents=True,mode=0o700,exist_ok=True)
for name in ['before.json','before.tar','before.json.tar.gz','fixture.json','before-snapshots.json','wrong-scope.json','failure.json']:
    (out/name).unlink(missing_ok=True)
phases=['setup','scope','check','apply','verify','idempotent','drift','rollback']
steps=[{'step':'activatePlugin','pluginPath':'cml-core/cml-core.php'}]
for phase in phases:
    (out/(phase+'.json')).unlink(missing_ok=True)
    steps.append({'step':'runPHP','code':"<?php try {define('CML_SOURCE_REVIEW_ISOLATED',true);define('CML_SOURCE_REVIEW_PHASE','"+phase+"');require '/cml/tests/source-content-review.php';} catch(Throwable $e){file_put_contents('/cml-review-tests/failure.json',json_encode(['phase'=>'"+phase+"','error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]));throw $e;}"})
(out/'blueprint.json').write_text(json.dumps({'steps':steps}))
subprocess.run(['npx','--yes','@wp-playground/cli@3.1.62','run-blueprint','--wp=6.8','--php=8.3',
    '--blueprint='+str(out/'blueprint.json'),'--mount-dir',str(root/'wordpress'),'/cml',
    '--mount-dir',str(root/'wordpress/plugin/cml-core'),'/wordpress/wp-content/plugins/cml-core',
    '--mount-dir',str(out),'/cml-review-tests'],cwd=root,check=True,timeout=180)
results={p:json.loads((out/(p+'.json')).read_text()) for p in phases if p!='setup'}
assert all(x['passed'] for x in results.values())
print(json.dumps({'passed':True,'records':36,'scenarios':len(results),'phases':list(results)}))
