#!/usr/bin/env python3
"""Compare only our public plugin/theme files with the dedicated target host."""
import argparse,hashlib,json,shlex,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--component',choices=['all','core','hubspot','theme'],default='all')
args=parser.parse_args()
files={}
components={'core':(root/'plugin/cml-core','plugins/cml-core'),'hubspot':(root/'plugin/cml-hubspot','plugins/cml-hubspot'),'theme':(root/'theme/cml','themes/cml')}
for name,(source,prefix) in components.items():
 if args.component not in ['all',name]:continue
 for file in source.rglob('*'):
  if file.is_file():files[prefix+'/'+str(file.relative_to(source))]=hashlib.sha256(file.read_bytes()).hexdigest()
code='$files=json_decode(stream_get_contents(STDIN),true);$base=getenv("HOME")."/www/chinamudlogging.com/public_html/wp-content/";$bad=[];$actual=[];foreach($files as $file=>$hash){$actual[$file]=is_file($base.$file)?hash_file("sha256",$base.$file):null;if($actual[$file]!==$hash)$bad[]=$file;}echo json_encode(["checked"=>count($files),"failed"=>$bad,"actualSha256"=>$actual]);exit($bad?1:0);'
result=subprocess.run(['ssh','-o','BatchMode=yes','-o','ConnectTimeout=12','-o','ServerAliveInterval=5','-o','ServerAliveCountMax=1','cml-siteground','php -r '+shlex.quote(code)],input=json.dumps(files),text=True,capture_output=True,timeout=35)
if not result.stdout:
 raise SystemExit('SiteGround SSH verification unavailable: '+result.stderr.strip())
report=json.loads(result.stdout);report['files']=files;report['component']=args.component
cache=Path.home()/'.cache/chinamudlogging-wordpress/results';cache.mkdir(parents=True,exist_ok=True)
name='deployed-code.json' if args.component=='all' else 'deployed-code-'+args.component+'.json'
(cache/name).write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({k:report[k] for k in ['checked','failed']}))
raise SystemExit(1 if report['failed'] else result.returncode)
