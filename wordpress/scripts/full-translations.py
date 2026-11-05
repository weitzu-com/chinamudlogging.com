#!/usr/bin/env python3
"""Public-source translation workbench. Output is never approved automatically.

Short aligned chunks prevent the old long-paragraph omissions. Technical codes,
quantities and explicit terminology are protected independently of prose.
"""
import argparse, collections, concurrent.futures, hashlib, importlib.util, json, re, string, urllib.parse, urllib.request, threading, time
from pathlib import Path
ROOT = Path(__file__).resolve().parents[2]
CACHE = Path.home()/'.cache/chinamudlogging-wordpress/full-translation/cache'
CACHE.mkdir(parents=True, exist_ok=True)
GLOSSARY = json.loads((ROOT/'wordpress/localization/glossary.json').read_text())
LANGS = GLOSSARY['languages']
NETWORK_LOCK=threading.Lock()
THROTTLED=threading.Event()
LAST_REQUEST=0.0
SEP = '\n⟪⟪⟪⟫⟫⟫\n'
NUMBERS = re.compile(r'\d+(?:[.,]\d+)*')
UNIT = r'(?:ms/cm|g/cm3|VDC|%RH|％FS|%FS|％|%|℃|°C|°F|kHz|Hz|kPa|pA|mA|mm|cm|nm|min|CFM|Grms|KG|kg|dB|V|W|w|m|s|ms/cm)'
QUANTITY = r'(?:[<>≤≥±+－−-]\s*)?\d+(?:[.,]\d+)?(?:\s*[×~～/:-]\s*[+－−-]?\d+(?:[.,]\d+)?)*(?:\s*'+UNIT+r'(?![A-Za-z]))?'
REPAIRS = {
 'under compaction':'undercompaction', 'well lost':'lost circulation', 'bit freezing':'stuck drillstring',
 'upper computer':'host computer', 'turntable':'rotary table', 'butter':'grease',
 'leucorrhea blue':'whitish blue', 'wall of the well wall':'sidewall samples',
 'buzzer113':'buzzer 113', 'Non-operating:':'Non-operating:',
 'Product Name: Industrial computer Fan 2 (12 cm / 82 CFM)':'Product: industrial computer. Cooling fans: 2 (12 cm / 82 CFM).',
 '1.4U rackmount':'1. 4U rackmount', '2.Shock-resistant':'2. Shock-resistant',
 '3.Front':'3. Front', '4.Dual':'4. Dual', '1.Material':'1. Material', '2.Surface':'2. Surface',
 '3.Intel and outlet':'3. Inlet and outlet', '4.Inlet,outlet':'4. Inlet, outlet', '5.Screen':'5. Screen',
 '1.The':'1. The', '2.The':'2. The', '（1）':'(1) ', '（2）':'(2) ',
 '(1）Remove':'(1) Remove', '(2）Press':'(2) Follow', '(3）The':'(3) The',
 '(1）The':'(1) The', '(2）The':'(2) The', '(4）When':'(4) When',
 'wdlding':'welding', 'Tecperature':'Temperature', 'strataand':'strata and',
 'mudstoneconsists':'mudstone consists', 'Compositionand':'Composition and',
 'StandardApplicable':'Standard. Applicable', 'installationAnti-jamming':'installation. Anti-jamming',
 'Software Windows':'Software: Windows', 'platformData':'platform. Data', 'CDAEnglish':'CDA. English',
 'VersionRealtime':'Version. Realtime', 'curve)Engineering':'curve). Engineering',
 'KitData':'Kit. Data', 'ProgramOil':'Program. Oil', 'principleDetection':'principle. Detection',
 'protectionOver-current':'protection. Over-current', 'protectionLow-power':'protection. Low-power',
 'charge Communication':'charge. Communication', '300m WITS':'300m. WITS',
 'By transmitting a constant amplitude and fixed frequency AC signal':'By transmitting an AC signal with constant amplitude and fixed frequency',
 'The demarcation is incorrect and the calibration is re calibrated.':'The calibration is incorrect; recalibrate the sensor.',
 'Fluorescent dry and wet photos.':'Dry and wet fluorescence observations and photographs.',
 'SLS Mud Logging Unit is new generation logging equipment which is designed and manufactured by us, cooperated with famous International mud logging comany.':'The original supplier catalogue describes the SLS Mud Logging Unit as new-generation logging equipment designed and manufactured by its author in cooperation with a well-known international mud logging company.',
}
NOTES = {
 'common':'This is the complete translation of the legacy supplier catalogue. Specifications and statements about certification, safety, performance and delivery remain source statements, not current independent verification. Confirm the ordered configuration and manufacturer documentation before selection, installation or calibration. Figures mentioned in the text are source references; no missing drawing has been reconstructed.',
 '245':'Source clarification: CNPS-8D08 and SK-8D08 both appear in the source. The conductivity notation ms/cm is preserved; its letter case and intended unit must be confirmed. The cable sheath diameter is given as 8 without a unit. The source names both J and RTD calibration and cites legacy standards; confirm the model, units, calibration procedure and applicable edition before use.',
 '251':'Source clarification: the legacy text combines EJA118W, BT200 and HART. Confirm the actual transmitter communication option and matching communicator; the model name alone does not prove protocol compatibility. All pressure, water-column and current values are preserved as supplied, not recalculated calibration instructions.',
 '261':'Source clarification: the stated non-operating range 20 ~ 60 °C does not match the accompanying -4 ~ 140 °F. Both are retained; confirm the lower limit. The source list item 1.4U is formatted as item 1 with a 4U chassis, consistent with its separate form-factor entry. The trailing drive-bay and port counts retain the source values.',
 '260':'Source clarification: the voltage variants and motor Ex marking are copied from the catalogue. Verify the actual motor nameplate, power configuration and certificate; a translated Ex marking is not a current certificate.',
 '258':'Source clarification: AC100-AC240 DC12 and W WF1 WF2 are the literal source notations. The source mixes division and zone terminology. Do not infer a voltage option, corrosion standard or hazardous-area approval without the ordered equipment documentation.',
 '259':'Source clarification: the source gives A0 and A-60 fire classes in different sections, and gives both a cycle time ≥30 s and an analysis cycle ≤30s. These statements are retained separately. DNV and IEC references, the 7890 identifier, and legacy communications or operating systems require configuration and certificate confirmation; they are not platform manufacturing credentials.',
 '257':'Source clarification: the product uses UV fluorescence observation, not X-ray fluoroscopy. The ambiguous source material names silk oil and lead oil remain quoted without assigning a chemical identity. Fluorescence colours are source observations, not a definitive assay. Any UV or solvent procedure requires the equipment instructions and suitable laboratory controls.',
 '273':'Source clarification: density deviation is an interpretation indicator, not a standalone quantitative pore-pressure prediction. The general source paragraph about EX sensors and barriers does not establish certification for this density meter. The source references to bit freezing and well lost are translated in context as stuck drillstring and lost circulation.',
 '297':'Source clarification: the source states accuracy ±1 without a unit, minimum action distance >8mm, and an installation distance within 30 mm. These are not silently reconciled. Confirm the switching distance, tolerance, target and installation arrangement before use.',
 '307':'Source clarification: battery duration, communication distance, installation time, lightning protection and complete replacement of wired sensors are legacy supplier statements under unspecified conditions. Confirm the radio configuration, operating environment, power budget and field acceptance criteria.',
}

def sha(value): return hashlib.sha256(value.encode()).hexdigest()
def clean(text):
    result = text
    for old,new in REPAIRS.items(): result=result.replace(old,new)
    return result

def alpha(n):
    return ''.join(string.ascii_uppercase[(n//(26**power))%26] for power in [2,1,0])

def protect(text,lang):
    entries=[]
    terms=sorted(GLOSSARY['terms'],key=len,reverse=True)
    codes=sorted(GLOSSARY['protectedCodes'],key=len,reverse=True)
    pattern=re.compile(r'(?<![A-Za-z])(?:(?i:'+'|'.join(re.escape(x) for x in terms)+r')|'+'|'.join(re.escape(x) for x in codes)+r')(?![A-Za-z])|'+QUANTITY)
    term_keys={k.lower():k for k in GLOSSARY['terms']}
    def mask(match):
        source=match.group(); key=term_keys.get(source.lower())
        value=GLOSSARY['terms'][key][lang] if key else source
        marker='QZX'+alpha(len(entries))+'XZQ'
        entries.append({'marker':marker,'source':source,'value':value,'kind':'term' if key else 'technical'})
        return ' '+marker+' '
    return pattern.sub(mask,text),entries

def chunks(text,limit=500):
    parts=[]; start=0
    while start<len(text):
        end=min(start+limit,len(text))
        if end<len(text):
            stops=[m.end() for m in re.finditer(r'[.!?。;]\s+',text[start:end])]
            end=start+stops[-1] if stops and stops[-1]>limit//3 else text.rfind(' ',start,end)
            if end<=start:raise RuntimeError('Unbreakable source segment')
        value=text[start:end].strip()
        if value:parts.append(value)
        start=end
    if ''.join(text.split())!=''.join(''.join(parts).split()):raise RuntimeError('Chunk coverage loss')
    return parts or ['']

def request(masked,lang):
    key=sha('full-raw-v3\0'+lang+'\0'+masked);file=CACHE/(key+'.txt')
    if file.exists():return file.read_text()
    query=urllib.parse.urlencode({'client':'gtx','sl':'en','tl':'zh-CN' if lang=='zh' else lang,'dt':'t','q':masked})
    req=urllib.request.Request('https://translate.googleapis.com/translate_a/single?'+query,headers={'User-Agent':'ChinaMudLogging-Public-Translation/1.0'})
    global LAST_REQUEST
    with NETWORK_LOCK:
        if THROTTLED.is_set():raise RuntimeError('Translation service throttled; no further requests')
        time.sleep(max(0,1.2-(time.monotonic()-LAST_REQUEST)));LAST_REQUEST=time.monotonic()
        try:
            with urllib.request.urlopen(req,timeout=35) as response:data=json.load(response)
        except urllib.error.HTTPError as error:
            if error.code==429:THROTTLED.set()
            raise
    value=''.join(x[0] for x in data[0] if x[0]);file.write_text(value);return value

def translate_many(texts,lang):
    output=[]; batch=[];length=0
    def flush():
        if not batch:return
        translated=request(SEP.join(texts[i] for i in batch),lang).split(SEP.strip())
        if len(translated)!=len(batch):translated=[request(texts[i],lang) for i in batch]
        if len(translated)!=len(batch) or any(not x.strip() for x in translated):raise RuntimeError('Incomplete translation response')
        output.extend(re.sub(r' {2,}', ' ',x).strip() for x in translated)
        batch.clear()
    for i,text in enumerate(texts):
        if batch and length+len(text)>2200:flush();length=0
        batch.append(i);length+=len(text)+len(SEP)
    flush()
    return output

def translate_product(product,lang):
    segments=[];mapping=[]
    def add(key,text):
        repaired=clean(text);pieces=chunks(repaired)
        start=len(segments);segments.extend(pieces);mapping.append((key,text,repaired,start,len(pieces)))
    for si,section in enumerate(product['sections']):
        add(f's{si}.heading',section['heading'])
        for pi,text in enumerate(section['paragraphs']):add(f's{si}.p{pi}',text)
    add('note.common',NOTES['common']);add('note.product',NOTES[str(product['id'])])
    translated=translate_many(segments,lang);aligned=[]
    for key,original,repaired,start,count in mapping:
        aligned.append({'key':key,'source':original,'input':repaired,'sourceSHA256':sha(original),'inputSHA256':sha(repaired),'chunks':segments[start:start+count],'translatedChunks':translated[start:start+count],'translation':' '.join(translated[start:start+count])})
    return {'sourceId':product['id'],'language':lang,'sourceSHA256':sha(json.dumps(product,ensure_ascii=False,sort_keys=True)),'review':'pending','method':'short aligned raw translation segments; glossary and exact technical-token editorial review required','segments':aligned}

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--product',type=int);parser.add_argument('--language',choices=LANGS);args=parser.parse_args()
    selected=json.loads((ROOT/'wordpress/migration/priority-localizations.json').read_text())['products']
    products=[p for p in json.loads((ROOT/'src/data/products.json').read_text()) if str(p['id']) in selected and (not args.product or p['id']==args.product)]
    file=ROOT/'wordpress/localization/full-translations.json';data=json.loads(file.read_text()) if file.exists() else {}
    tasks=[(p,l) for p in products for l in LANGS if (not args.language or l==args.language) and str(p['id'])+':'+l not in data]
    errors=[]
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        futures={pool.submit(translate_product,p,l):(p['id'],l) for p,l in tasks}
        for future in concurrent.futures.as_completed(futures):
            pid,lang=futures[future]
            try:data[str(pid)+':'+lang]=future.result()
            except Exception as error:errors.append([pid,lang,str(error)]);print('FAILED',pid,lang,error,flush=True);continue
            file.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n');print('DRAFT',pid,lang,flush=True)
    print(json.dumps({'drafts':len(data),'errors':errors},ensure_ascii=False))
    if errors:raise SystemExit(1)
