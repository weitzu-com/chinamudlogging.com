#!/usr/bin/env python3
"""Compare only our public plugin/theme files with the dedicated target host."""
import hashlib,json,shlex,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1]
files={}
for source,prefix in [(root/'plugin/cml-core','plugins/cml-core'),(root/'theme/cml','themes/cml')]:
 for file in source.rglob('*'):
  if file.is_file():files[prefix+'/'+str(file.relative_to(source))]=hashlib.sha256(file.read_bytes()).hexdigest()
code='$files=json_decode(stream_get_contents(STDIN),true);$base=getenv("HOME")."/www/chinamudlogging.com/public_html/wp-content/";$bad=[];foreach($files as $file=>$hash){if(!is_file($base.$file)||hash_file("sha256",$base.$file)!==$hash)$bad[]=$file;}echo json_encode(["checked"=>count($files),"failed"=>$bad]);exit($bad?1:0);'
result=subprocess.run(['ssh','cml-siteground','php -r '+shlex.quote(code)],input=json.dumps(files),text=True,capture_output=True,check=True)
report=json.loads(result.stdout);report['files']=files
cache=Path.home()/'.cache/chinamudlogging-wordpress/results';(cache/'deployed-code.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({k:report[k] for k in ['checked','failed']}))
