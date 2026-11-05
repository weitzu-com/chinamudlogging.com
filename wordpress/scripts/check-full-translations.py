#!/usr/bin/env python3
"""Fail closed on missing paragraphs, changed values, units, codes or review."""
import argparse,collections,json,re,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parent))
from full_content import DIRECTORY,ROOT,LANGS,digest,segment_errors,text,render,load_approved
parser=argparse.ArgumentParser();parser.add_argument('--require-approved',action='store_true');args=parser.parse_args()
data=load_approved() if args.require_approved else json.loads((DIRECTORY/'full-translations.json').read_text())
products={p['id']:p for p in json.loads((ROOT/'src/data/products.json').read_text())}
priority=json.loads((ROOT/'wordpress/migration/priority-localizations.json').read_text())['products']
glossary=json.loads((DIRECTORY/'glossary.json').read_text())
expected={pid+':'+lang for pid in priority for lang in LANGS};errors=[];segments=0
if set(data)!=expected:errors.append(['inventory','expected exactly 60 selected source/language pairs'])
units=r'(?:ms/cm|g/cm3|VDC|%RH|％FS|%FS|％|%|℃|°C|°F|kHz|Hz|kPa|pA|mA|mm|cm|nm|min|CFM|Grms|KG|kg|dB|V|W|w|m|s)'
quantity=re.compile(r'(?:[<>≤≥±+－−-]\s*)?\d+(?:[.,]\d+)?(?:\s*[×x~～/:-]\s*[+－−-]?\d+(?:[.,]\d+)?)*\s*'+units+r'(?![A-Za-z])')
norm=lambda s:re.sub(r'\s+','',s)
for key,row in data.items():
    product=products[row['sourceId']];lang=row['language']
    if digest(product)!=row['sourceSHA256']:errors.append([key,'source changed'])
    for segment in row['segments']:
        if segment['key'].startswith('note.'):continue
        segments+=1
        for error in segment_errors(segment):errors.append([key,segment['key'],error])
        source=segment['input'];target=text(segment)
        for value in quantity.findall(source):
            if norm(value) not in norm(target):errors.append([key,segment['key'],'unit/value expression',value])
        for code in glossary['protectedCodes']:
            if re.search(r'(?<![A-Za-z])'+re.escape(code)+r'(?![A-Za-z])',source) and norm(code) not in norm(target):errors.append([key,segment['key'],'technical code',code])
        if re.search(r'Stecklinge|esquejes|boutures|冻钻头|白带|leucorrhée|Schlammgewinnung|CMLTOKEN|QZX',target,re.I):errors.append([key,segment['key'],'known incorrect term'])
    try:render(product,lang,row)
    except Exception as error:errors.append([key,'render',str(error)])
report={'records':len(data),'languages':LANGS,'sourceParagraphsAndHeadings':segments,'errors':errors,'reviewRequired':not args.require_approved,'evidenceBoundary':'AI editorial and technical consistency review; unresolved legacy source claims remain labelled, not manufacturer confirmation.'}
cache=Path.home()/'.cache/chinamudlogging-wordpress/full-translation';(cache/'validation.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(report,ensure_ascii=False,indent=2))
if errors:raise SystemExit(1)
