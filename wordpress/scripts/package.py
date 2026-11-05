#!/usr/bin/env python3
import hashlib,json,zipfile
from pathlib import Path
root=Path(__file__).resolve().parents[2]
cache=Path.home()/'.cache/chinamudlogging-wordpress';out=cache/'release';out.mkdir(exist_ok=True)
components=[('cml',root/'wordpress/theme/cml')]+[(p.name,p) for p in sorted((root/'wordpress/plugin').iterdir()) if p.is_dir()]
for name,source in components:
    with zipfile.ZipFile(out/(name+'.zip'),'w',zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(source.rglob('*')):
            if path.is_file():archive.write(path,Path(name)/path.relative_to(source))
with zipfile.ZipFile(out/'cml-migration.zip','w',zipfile.ZIP_DEFLATED) as archive:
    for path in sorted((cache/'bundle').rglob('*')):
        if path.is_file():archive.write(path,Path('cml-migration')/path.relative_to(cache/'bundle'))
    for path in sorted((root/'wordpress/scripts').glob('*.php')):archive.write(path,Path('cml-migration/scripts')/path.name)
checksums={p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(out.glob('*.zip'))}
(out/'SHA256SUMS.json').write_text(json.dumps(checksums,indent=2)+'\n')
print(out)
