#!/usr/bin/env python3
import collections,html,json,re
from pathlib import Path
from full_content import load_approved, render, digest
root=Path(__file__).resolve().parents[2]
bundle=json.loads((Path.home()/'.cache/chinamudlogging-wordpress/bundle/bundle.json').read_text())
rows=bundle['records']; keys=[r['sourceId']+':'+r['language'] for r in rows]
assert len(keys)==len(set(keys)), 'Duplicate source/language key'
en=[r for r in rows if r['language']=='en']
full=load_approved()
products={p['id']:p for p in json.loads((root/'src/data/products.json').read_text())}
assert sum(r['type']=='cml_product' for r in en)==63
assert sum(r['type']=='post' for r in en)==8
assert len(bundle['categories'])==5
for lang in bundle['languages']:
    assert any(r.get('pageKey')=='home' and r['language']==lang for r in rows)
    for key in ['about','services','products','applications','contact','privacy']:
        assert any(r.get('pageKey')==key and r['language']==lang for r in rows),(lang,key)
    if lang!='en':
        translated=[r for r in rows if r['type']=='cml_product' and r['language']==lang]
        assert len(translated)==10,(lang,'expected 10 priority product drafts',len(translated))
        for translated_row in translated:
            original=next(r for r in en if r['sourceId']==translated_row['sourceId'])
            def numbers(r):
                text=html.unescape(re.sub(r'<[^>]+>', ' ',r['content']))
                return collections.Counter(re.findall(r'\d+(?:[.,]\d+)*',text))
            if translated_row.get('localizationMode')=='full-source-translation-reviewed':
                identifier=translated_row['sourceId'].split(':')[1]
                reviewed=full[identifier+':'+lang]
                assert translated_row['content']==render(products[int(identifier)],lang,reviewed)
                assert translated_row['review']=='approved'
                assert translated_row['fullTranslationSHA256']==digest(reviewed)
            elif translated_row.get('localizationMode')=='curated-selection-with-complete-english-reference':
                source=html.unescape(re.sub(r'<[^>]+>', ' ',original['content']))+original['title']+original['description']
                normalize=lambda text:''.join(text.split())
                assert len(translated_row['sourceParameters'])>=4
                for value in translated_row['sourceParameters']:assert normalize(value) in normalize(source),(translated_row['sourceId'],value)
                assert 'hreflang="en" href="'+original['sourceUrl']+'"' in translated_row['content']
                assert translated_row['review']=='approved'
            else:
                assert numbers(original)==numbers(translated_row),(translated_row['sourceId'],lang,'numeric mismatch')
                assert translated_row['review']=='pending','Machine translations require review'
            assert not re.search('CMLTOKEN|CMLPARAGRAPH|⟦\\d+⟧|⟪⟪⟪',translated_row['content']),'Unresolved translation token'
for source,target in bundle['redirects'].items():
    assert source!=target and target not in bundle['redirects'],'Redirect chain or loop'
assert '/product/' not in bundle['redirects']
print('Bundle verified: 63 English products, 8 articles, seven page sets, 60 reviewed localized product records.')
