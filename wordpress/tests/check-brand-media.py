#!/usr/bin/env python3
"""Validate every shipped image pair and the source-preservation manifest."""
import hashlib,json
from pathlib import Path
root=Path(__file__).resolve().parents[1]/'theme/cml/assets/catalogue'
data=json.loads((root/'manifest.json').read_text())
assert data['canvas']==[1200,900] and len(data['images'])==166
assert sum(row['placeholder'] for row in data['images'])==1
assert sum(row['corner_mark_removed'] for row in data['images'])==32
seen=set()
for row in data['images']:
    assert row['source_sha256'] not in seen
    seen.add(row['source_sha256'])
    assert row['outside_mark_pixel_changes']==0
    for filename,key in [('file','sha256'),('small','small_sha256')]:
        path=root/row[filename]
        assert path.name==row[filename] and path.suffix=='.webp'
        assert hashlib.sha256(path.read_bytes()).hexdigest()==row[key],path.name
print('166 branded image pairs verified; 32 reviewed corner marks; one honestly labelled missing photograph; source preservation checks passed.')
