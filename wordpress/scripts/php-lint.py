#!/usr/bin/env python3
"""Parse PHP on an already authorized SiteGround SSH host, without writing files."""
import json,shlex,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1]
files={str(p.relative_to(root)):p.read_text() for p in root.rglob('*.php')}
code='$files=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); foreach($files as $name=>$source){try{token_get_all($source,TOKEN_PARSE);echo "PASS ".$name.PHP_EOL;}catch(Throwable $e){fwrite(STDERR,$name.": ".$e->getMessage().PHP_EOL);exit(1);}}'
subprocess.run(['ssh','-o','BatchMode=yes','-o','ConnectTimeout=10','cml-siteground','php -r '+shlex.quote(code)],input=json.dumps(files),text=True,check=True)
