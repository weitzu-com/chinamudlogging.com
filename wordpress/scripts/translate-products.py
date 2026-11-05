#!/usr/bin/env python3
"""Generate review-only translations of already public catalogue text.

Uses Google's public web translation endpoint, with a local cache. This is not a
paid API or a production dependency. Stop on throttling; never send private data.
All output remains pending review and cannot be published by the importer.
"""
import collections,concurrent.futures,hashlib,json,re,urllib.parse,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
CACHE=Path.home()/'.cache/chinamudlogging-wordpress/translations';CACHE.mkdir(parents=True,exist_ok=True)
LANGS=['zh','es','ru','ar','fr','de']
SEPARATOR='\n\n⟪⟪⟪⟫⟫⟫\n\n'

def translate_raw(text,lang):
    query=urllib.parse.urlencode({'client':'gtx','sl':'en','tl':'zh-CN' if lang=='zh' else lang,'dt':'t','q':text.replace('。','. ').replace('”',chr(34)).replace('“',chr(34))})
    request=urllib.request.Request('https://translate.googleapis.com/translate_a/single?'+query,headers={'User-Agent':'ChinaMudLogging-Content-Migration/1.0'})
    with urllib.request.urlopen(request,timeout=40) as response:result=json.load(response)
    value=''.join(part[0] for part in result[0] if part[0])
    expected=collections.Counter(re.findall(r'\d+(?:[.,]\d+)*',text))
    actual=collections.Counter(re.findall(r'\d+(?:[.,]\d+)*',value))
    if actual!=expected:
        # Some target languages use a decimal comma; restore the source notation.
        by_normalized={n.replace(',','.'):n for n in expected}
        value=re.sub(r'\d+(?:[.,]\d+)*',lambda m:by_normalized.get(m.group().replace(',','.'),m.group()),value)
    if collections.Counter(re.findall(r'\d+(?:[.,]\d+)*',value))!=expected:
        (CACHE/('numeric-'+lang+'.json')).write_text(json.dumps({'source':text,'translation':value},ensure_ascii=False,indent=2))
        raise RuntimeError('Numeric mismatch in raw translation for '+lang)
    return value

def translate(text,lang):
    key=hashlib.sha256((lang+'\0'+text).encode()).hexdigest();file=CACHE/(key+'.txt')
    if file.exists():return file.read_text()
    protected=[]
    def mask(match):
        protected.append(match.group());return '⟦'+f'{len(protected)-1:04d}'+'⟧'
    masked=re.sub(r'\S*\d\S*|\b[A-Z]{2,}(?:[-/][A-Za-z0-9]+)*\b',mask,text)
    query=urllib.parse.urlencode({'client':'gtx','sl':'en','tl':'zh-CN' if lang=='zh' else lang,'dt':'t','q':masked})
    request=urllib.request.Request('https://translate.googleapis.com/translate_a/single?'+query,headers={'User-Agent':'ChinaMudLogging-Content-Migration/1.0'})
    with urllib.request.urlopen(request,timeout=40) as response:result=json.load(response)
    value=''.join(part[0] for part in result[0] if part[0])
    for i,original in enumerate(protected):
        token='⟦'+f'{i:04d}'+'⟧'
        matches=list(re.finditer(r'\s*'.join(re.escape(c) for c in token),value,re.I))
        if len(matches)!=1:
            (CACHE/('failed-'+lang+'.txt')).write_text(value)
            value=translate_raw(text,lang);file.write_text(value);return value
        value=value[:matches[0].start()]+token+value[matches[0].end():]
        value=value.replace(token,original)
    if collections.Counter(re.findall(r'\d+(?:[.,]\d+)*',text)) != collections.Counter(re.findall(r'\d+(?:[.,]\d+)*',value)):
        (CACHE/('numeric-'+lang+'.json')).write_text(json.dumps({'source':text,'translation':value},ensure_ascii=False,indent=2))
        value=translate_raw(text,lang)
    file.write_text(value);return value

def product_translation(product,lang):
    texts=[product['title'],product['summary']]
    for section in product['sections']:texts += [section['heading']]+section['paragraphs']
    translated=[];batch=[];length=0
    granular=(product['id']==251 and lang in ['es','ar','fr','de']) or (product['id']==259 and lang=='de')
    if granular:
        translated=[]
        for text in texts:
            try: translated.append(translate(text,lang))
            except RuntimeError:
                sentences=re.split(r'(?<=[.!?])\s+(?=[A-Z(])',text)
                if len(sentences)<2: raise
                translated.append(' '.join(translate(sentence,lang) for sentence in sentences))
        texts=[]
    for text in texts:
        if batch and length+len(text)>3300:
            value=translate(SEPARATOR.join(batch),lang).split('⟪⟪⟪⟫⟫⟫')
            if len(value)!=len(batch):raise RuntimeError('Paragraph boundary mismatch')
            translated += [x.strip() for x in value];batch=[];length=0
        batch.append(text);length += len(text)+len(SEPARATOR)
    if batch:
        value=translate(SEPARATOR.join(batch),lang).split('⟪⟪⟪⟫⟫⟫')
        if len(value)!=len(batch):raise RuntimeError('Paragraph boundary mismatch')
        translated += [x.strip() for x in value]
    result={'title':translated[0],'summary':translated[1],'sections':[],'review':'pending','method':'machine-first-pass; protected technical tokens; human review required'}
    index=2
    for section in product['sections']:
        count=len(section['paragraphs']);result['sections'].append({'heading':translated[index],'paragraphs':translated[index+1:index+1+count]});index+=count+1
    return str(product['id']),lang,result

if __name__=='__main__':
    products=json.loads((ROOT/'src/data/products.json').read_text());categories=json.loads((ROOT/'src/data/categories.json').read_text())
    selected=[]
    for category in categories:
        rows=sorted([p for p in products if p['category']['slug']==category['slug']],key=lambda p:(-sum(len(x) for s in p['sections'] for x in s['paragraphs']),p['slug']))
        selected+=rows[:2]
    file=ROOT/'wordpress/migration/product-translations.json';data=json.loads(file.read_text()) if file.exists() else {}
    tasks=[(p,l) for p in selected for l in LANGS if l not in data.get(str(p['id']),{})]
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        futures=[pool.submit(product_translation,p,l) for p,l in tasks]
        errors=[]
        for future in concurrent.futures.as_completed(futures):
            try: identifier,lang,result=future.result()
            except Exception as error:
                errors.append(str(error));print('FAILED',error,flush=True);continue
            data.setdefault(identifier,{})[lang]=result
            file.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n');print(identifier,lang,'draft complete',flush=True)
    if errors: print('Translation errors:',len(errors),flush=True)
    print('Review-only translated products:',sum(len(x) for x in data.values()))
