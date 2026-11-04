#!/usr/bin/env python3
"""Start isolated real WordPress/PHP/SQLite for acceptance without Docker or PHP."""
import json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[2]
cache=Path.home()/'.cache/chinamudlogging-wordpress';cache.mkdir(parents=True,exist_ok=True)
bundle=cache/'bundle';results=cache/'results';results.mkdir(exist_ok=True)
steps=[
 {'step':'defineWpConfigConsts','consts':{'WP_DEBUG':True,'WP_DEBUG_DISPLAY':True,'WP_DISABLE_FATAL_ERROR_HANDLER':True}},
 {'step':'activatePlugin','pluginPath':'polylang/polylang.php'},
 {'step':'activatePlugin','pluginPath':'cml-core/cml-core.php'},
 {'step':'runPHP','code':"<?php define('PLL_ADMIN', true); require '/wordpress/wp-load.php'; require '/cml/scripts/setup-languages.php';"},
 {'step':'runPHP','code':"<?php require '/wordpress/wp-load.php'; add_filter('intermediate_image_sizes_advanced', '__return_empty_array'); cml_import_bundle('/bundle/bundle.json');"},
 {'step':'activateTheme','themeFolderName':'cml'},
 {'step':'setSiteOptions','options':{'blogname':'ChinaMudLogging','blog_public':'0'}},
 {'step':'runPHP','code':"<?php require '/wordpress/wp-load.php'; define('CML_INTEGRATION_TEST', true); require '/cml/tests/integration.php';"},
]
blueprint={'preferredVersions':{'php':'8.3','wp':'latest'},'steps':steps}
file=cache/'blueprint.json';file.write_text(json.dumps(blueprint,indent=2))
command=['npx','--yes','@wp-playground/cli@3.1.62','server','--port=9400','--workers=6','--blueprint='+str(file)]
mounts=[(root/'wordpress/theme/cml','/wordpress/wp-content/themes/cml'),(root/'wordpress/plugin/cml-core','/wordpress/wp-content/plugins/cml-core'),(Path.home()/'.cache/cml-wordpress/polylang','/wordpress/wp-content/plugins/polylang'),(root/'wordpress','/cml'),(bundle,'/bundle'),(results,'/cml-results')]
for local,remote in mounts:
 if not local.exists(): raise SystemExit('Missing: '+str(local))
 command += ['--mount-dir',str(local),remote]
subprocess.run(command,cwd=root,check=True)
