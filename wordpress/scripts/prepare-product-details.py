#!/usr/bin/env python3
"""Compile public presentation from source records, without changing those records."""
import argparse, hashlib, html, json, re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
def load(path): return json.loads(path.read_text())
def compact(value): return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))
def fingerprint(p):
    m=p['meta']
    return hashlib.sha256((p['content']+'\n'+str(m.get('_cml_sku',''))+'\n'+compact(m.get('_cml_specifications',[]))+'\n'+str(m.get('_cml_source_id',''))).encode()).hexdigest()

# Only normalize language/format, never a number or unit. Unlisted fields remain in the source.
LABELS = {
 'Charger Output voltage':'Charger output voltage', 'Rechargeable Battery Nominal voltage':'Battery nominal voltage',
 'Working temperature':'Operating temperature', 'Overall size':'Overall dimensions',
 'Supply Power':'Power supply', 'Supply':'Power supply', 'Supply voltage':'Supply voltage',
 'Power supply voltage':'Supply voltage', 'Working voltage':'Supply voltage', 'Rated working voltage':'Rated supply voltage',
 'The maximum output current':'Maximum output current', 'The highest working frequency':'Maximum operating frequency',
 'The baffle angle':'Paddle opening angle', 'Open Angle of damper':'Paddle opening angle',
 'Effective action distance':'Actuation distance', 'Measuring range':'Measurement range', 'Measuring accuracy':'Measurement accuracy',
 'Size(L*W*H)':'Dimensions (L × W × H)', 'Size':'Dimensions', 'Temperature Operating':'Operating temperature',
 'Humidity Operating':'Operating humidity', 'Working environment temperature':'Ambient temperature',
 'Channel':'Analogue channels', 'No. of pulse channel':'Pulse channels',
}
SAFE = set('Output current|Input voltage|Nominal capacity|Operating temperature|Supply voltage|Power supply|Power consumption|Response time|Output level|Output signal|Measurement range|Measurement accuracy|Accuracy|Output|Power|Motor power|Frequency response|Resolution|Relative humidity|Overall dimensions|Stability|Temperature effect|Measurement method|Power requirement|Primary application|Rated voltage|Rated current|Motor speed|Stirring shaft speed|Range|Operating frequency|Detection range|Temperature range|Minimum detectable concentration|Minimum sample weight|Minimum sample mass|Switching distance|Maximum operating current|Maximum speed|Number of encoder disc teeth|Baffle opening angle|Zero offset (-40°C - +50°C)|Repeatability|Accuracy class|Sensitivity|Rated supply|Measurement principle|Ranges|Accuracy, 3 m version|Blanking distance|Ambient operating temperature|Paddle opening angle|Motor and agitator speed|Gas-collection chamber height adjustment|Total equipment weight|Maximum measurement range|Process temperature|Process connection|Cable entry|Beam angle|Process pressure|Lens size|Bluetooth|Principle|Measurand|Output (configured)|Power (two-wire)|Resolution / sensitivity|Motor / agitator speed|Gas-collecting cylinder adjustable height|Total weight|Zero drift|Repeatability / linearity|Encoder teeth|Max speed|Mounting|Linearity & hysteresis|Yearly stability|Min actuation distance|Max frequency|Probe operating temperature|Node operating temperature|Radar frequency|Backplane version'.split('|')) | set(LABELS.values())
SAFE.update(['Weight','Power source','Operating temperature range','Rated working voltage','Maximum output current','Cycle time','Baseline drift','Baseline noise','Repetition error','Reactivity','Aperture','Environmental conditions'])
def main():
    a=argparse.ArgumentParser();a.add_argument('inventory',type=Path);a.add_argument('--audit',type=Path,required=True);args=a.parse_args()
    inventory=load(args.inventory)['products']; locales=load(ROOT/'wordpress/content-repairs/detail-copy-20261105.json')
    curated=load(ROOT/'wordpress/migration/priority-localizations.json')
    depth=load(ROOT/'wordpress/extensions/cml-content-depth.json'); priority=load(ROOT/'wordpress/extensions/cml-seo-priority.json')
    products={}; audit=[]
    for p in inventory:
        lang=p['language']; c=locales[lang]; m=p['meta']; sid=m.get('_cml_source_id','').removeprefix('product:')
        specs=m.get('_cml_specifications',[]); source_name=next((s.get('raw','') for s in specs if s['label'] in ['Name','Item name']),'')
        role=next((k for k,v in depth['profiles'].items() if any(n in source_name for n in v['names'])),None)
        checks=c['checks']; intro=''; rows=[]; decisions=[]
        if lang=='en':
            guide=priority['equipment'].get(p['slug']) or depth.get('legacy',{}).get(p['slug'])
            if guide:
                intro=guide.get('intro',''); checks=guide.get('checks') or [x for s in guide.get('sections',[]) for x in s['items']]
            elif role:
                intro=depth['profiles'][role]['scope']; checks=depth['profiles'][role]['checks']
            else:
                guide=next((v for k,v in priority['measurements'].items() if k in source_name),None)
                if guide: checks=guide['checks']
        canonical=curated['products'].get(sid)
        system=p['slug'] in priority['equipment'] or sid=='259'
        if canonical and not system:
            for s in canonical['specs']:
                key=s['label']; status=''
                if key=='model': continue # identity appears once, above the details
                if key=='conductivity_reference': status='unclear'
                elif key=='legacy_marking': status='marking'
                elif key in ['battery_days','distance_los','distance_obstructed']: status='confirm'
                rows.append({'label':curated['labels'][lang][key],'value':s['value'] if not status else '', 'status':status, 'source':s['value']})
            decisions.append({'action':'approved-localized-selection','source':sid})
        elif not system:
            seen=set()
            for s in specs:
                raw=s.get('raw','').strip(); original=s['label']; label=re.sub(r'^\d+[,.]\s*','',original).strip(); label=LABELS.get(label,label)
                reason='duplicate or non-parameter source text'; status=''
                if label in SAFE or ('notation' in label.lower()):
                    reason='retained source literal'
                    if ('notation' in label.lower() or 'unconfirmed' in label.lower() or not raw or len(raw)>150 or re.search(r'250MW|150\s*S\s*3|1P65|F·S|^-40~$',raw)):
                        status='unclear'
                    if p['id']==102 and ('range' in label.lower()): status='conflict'
                    if raw and re.fullmatch(r'[±+-]?\d+(?:\.\d+)?\.?',raw) and re.search(r'temperature|voltage|power|current|range|accuracy',label,re.I): status='unclear'
                    if p['id']==205 and label=='Temperature range': status='unclear'
                    key=(label.casefold(),raw.casefold())
                    if key not in seen:
                        display=raw.replace('三相','Three-phase ').replace('不小于','At least')
                        seen.add(key); rows.append({'label':label,'value':display if not status else '', 'status':status,'source':raw})
                    else: reason='duplicate parameter'
                elif re.search(r'explosion|ex.mark|protection|hazardous|security level|full level',label,re.I):
                    # Source markings are not proof of an applicable certificate.
                    if not any(r.get('status')=='marking' for r in rows): rows.append({'label':'Configuration approval and protection','value':'','status':'marking','source':raw})
                    reason='certificate/configuration confirmation'
                decisions.append({'label':original,'raw':raw,'disposition':reason,'status':status})
        else: decisions.append({'action':'historical package ratings require current itemized schedule'})
        if not rows:
            fields=['empty_model','empty_function','empty_interface']
            if system: fields=['empty_channels','empty_system','empty_power']
            elif role=='board': fields=['empty_revision','empty_host','empty_interface']
            elif role=='mount': fields=['empty_dimensions','empty_materials','empty_host']
            elif role in ['charger','battery']: fields=['empty_battery','empty_interface','empty_host']
            elif role=='cable': fields=['empty_cable','empty_interface','empty_host']
            rows=[{'label':c[k],'value':'','status':'pending','source':''} for k in fields]
        if p['id']==125:
            rows=[{'label':c[k],'value':'','status':'pending','source':''} for k in ['empty_model','empty_function','empty_range','empty_interface']]
        unique={}
        for row in rows:
            key=row['label'].casefold()
            if key in unique:
                previous=unique[key]
                if previous['value']!=row['value']:
                    previous['value']='';previous['status']='conflict'
                decisions.append({'action':'merged repeated parameter','label':row['label']})
            else: unique[key]=row
        rows=list(unique.values())
        links=[]
        for u in re.findall(r'https?://[^\s<>"\']+',html.unescape(p['content'])):
            if re.search(r'\.pdf(?:[?#]|$)',u,re.I) and u not in links: links.append(u)
        source_url=m.get('_cml_source_url','')
        if source_url.startswith('https://') and 'epec' in source_url.lower(): links.append(source_url)
        # Canonical reviewed model or explicit source designation, never decoding ratings from SKU.
        designation=next((s['value'] for s in canonical['specs'] if s['label']=='model'),'') if canonical else ''
        if not designation:
            designation=next((s.get('raw','') for s in specs if s['label'] in ['Supplier catalogue designation','Supplier catalogue model','Model / SKU','Model']),'')
        if len(designation)>90 or re.search(r'\\|[\u4e00-\u9fff]|https?://',designation): designation=''
        products[str(p['id'])]={'slug':p['slug'],'language':lang,'fingerprint':fingerprint(p),'intro':intro,'rows':rows,'checks':list(dict.fromkeys(checks)),'links':list(dict.fromkeys(links)),'designation':designation}
        audit.append({'id':p['id'],'slug':p['slug'],'language':lang,'role':role,'decisions':decisions,'display_rows':rows})
    out={'revision':'2026-11-05','copy':locales,'products':products}
    (ROOT/'wordpress/theme/cml/assets/product-details.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
    args.audit.write_text(json.dumps(audit,ensure_ascii=False,indent=2)+'\n')
    print(f'Compiled {len(products)} guarded product presentations; {sum(len(p["rows"]) for p in products.values())} rows')
if __name__=='__main__': main()
