#!/usr/bin/env python3
"""Check the reviewed display's literal provenance and uncertainty policy."""
import json,re
from pathlib import Path
root=Path(__file__).resolve().parents[2]
d=json.loads((root/'wordpress/theme/cml/assets/product-details.json').read_text())
assert len(d['products'])==375
assert set(d['copy'])=={'en','zh','es','ru','ar','fr','de'}
keys=set(d['copy']['en'])
for c in d['copy'].values(): assert set(c)==keys and all(c.values())
total=0
for p in d['products'].values():
    assert re.fullmatch('[a-f0-9]{64}',p['fingerprint'])
    assert p['language'] in d['copy'] and p['checks'] and p['rows']
    assert len(p['checks'])==len(set(p['checks']))
    seen=set()
    for row in p['rows']:
        total+=1
        key=(row['label'],row['value']);assert key not in seen;seen.add(key)
        assert row['status'] in ['','pending','unclear','conflict','marking','confirm']
        if row['status']: assert row['value']==''
        else:
            assert row['value']==row['source'].replace('三相','Three-phase ').replace('不小于','At least')
            if p['language']=='en': assert not re.search('[\u4e00-\u9fff]',row['value'])
        assert row['label'] not in ['Name','Item name','Source URL','Catalogue specification','Category note','Model / SKU']
    for link in p['links']: assert link.startswith('https://') and ('drive.google' not in link)
assert all(r['status']=='pending' for r in d['products']['125']['rows'])
assert any(r['status']=='conflict' for r in d['products']['102']['rows'])
assert any(r['status']=='unclear' for r in d['products']['100']['rows'])
print(f'375 reviewed presentations, {total} rows: provenance, uncertainty, deduplication and seven-language checks passed')
