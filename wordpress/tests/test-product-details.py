#!/usr/bin/env python3
"""Check the reviewed display's literal provenance and uncertainty policy."""
import json,re
import sys
from pathlib import Path
root=Path(__file__).resolve().parents[2]
d=json.loads((root/'wordpress/theme/cml/assets/product-details.json').read_text())
review=json.loads((root/'wordpress/content-repairs/product-source-review-20261111.json').read_text())
sys.path.insert(0,str(root/'wordpress/scripts'))
from product_source_review import apply_review
assert len(d['products'])==375
assert set(d['copy'])=={'en','zh','es','ru','ar','fr','de'}
keys=set(d['copy']['en'])
for c in d['copy'].values(): assert set(c)==keys and all(c.values())
total=0
for product_id,p in d['products'].items():
    assert re.fullmatch('[a-f0-9]{64}',p['fingerprint'])
    assert p['language'] in d['copy'] and p['checks'] and p['rows']
    assert len(p['checks'])==len(set(p['checks']))
    seen=set()
    for row in p['rows']:
        total+=1
        key=(row['label'],row['value']);assert key not in seen;seen.add(key)
        assert row['status'] in ['','pending','unclear','conflict','marking','confirm']
        for ref in row.get('citations',[]):
            assert ref in review['sources']
            assert review['sources'][ref]['url'].startswith('https://')
        if 'verification_id' in row:
            key,index=row['verification_id'].split(':');rule=review['rules'][key];fact=rule['facts'][int(index)]
            assert rule['products'][product_id]==p['fingerprint']
            assert row['value']==fact['value'] and row['status']==fact['status']
            assert row['label']==fact['labels'][p['language']]
            assert row['citations']==fact.get('sources',rule['sources'])
            assert all(review['sources'][r]['kind']!='company-reference' for r in row['citations'])
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
# Regression: keep version-specific facts separate and retain known source problems.
for id in map(str,range(128,135)):
    p=d['products'][id];assert p['designation']=='CL-DT11 / CL-DT12'
    v=[r for r in p['rows'] if r.get('verification_id','').startswith('cldt-motor-variants')]
    assert [r['value'] for r in v]==['220 V','380 V',''] and v[-1]['status']=='pending'
assert [r['value'] for r in d['products']['209']['rows'] if r.get('verification_id','').startswith('lu240-version-accuracy')][:3]==['±10 mm','± the greater of 0.15% of range or 6 mm; valid from 0.25 m','±2 mm']
assert all(any(r['status']=='unclear' and r['source']=='300ms/cm' for r in d['products'][str(i)]['rows']) for i in range(91,98))
assert any(r['status']=='conflict' and r['source']=='0-2.5bar' for r in d['products']['102']['rows'])
assert any(r['label']=='Current signal input' for r in d['products']['179']['rows'])
assert next(r for r in d['products']['186']['rows'] if r['label']=='Detection range')['status']=='unclear'
first=next(iter(review['rules'].values()));id=next(iter(first['products']))
try:
    apply_review({'products':{id:{'fingerprint':'changed'}}},{'sources':review['sources'],'rules':{'test':first},'revision':'test'})
except ValueError:pass
else:raise AssertionError('A stale source must not accept a reviewed fact')
