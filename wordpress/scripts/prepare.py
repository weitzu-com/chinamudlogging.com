#!/usr/bin/env python3
"""Build a portable, checksummed WordPress bundle without changing Astro sources."""
import argparse, collections, concurrent.futures, csv, hashlib, html, json, re, shutil, urllib.request
from urllib.parse import urlparse
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parents[2]
LANGS = ['en', 'zh', 'es', 'ru', 'ar', 'fr', 'de']
BASE = 'https://www.chinamudlogging.com'

def specification(label, raw):
    # Preserve exact source ranges/precision; never infer or convert a unit.
    match = re.fullmatch(r'\s*([<>≤≥~±]?\s*-?\d+(?:\.\d+)?(?:\s*(?:-|–|~|to)\s*-?\d+(?:\.\d+)?)?)\s*([A-Za-z°℃%µμΩ/³²·]+)\s*', raw)
    return dict(label=label.strip(), raw=raw.strip(), value=match.group(1).strip() if match else '', unit=match.group(2) if match else '')

def source_specifications(product):
    rows=[]
    for section in product['sections']:
        for text in section.get('paragraphs', []):
            parts=re.split('[:：]',text,maxsplit=1)
            if len(parts)==2 and 0<len(parts[0])<65 and 0<len(parts[1])<240:
                rows.append(specification(*parts))
    return rows

def paragraph(text):
    return '<!-- wp:paragraph --><p>' + html.escape(text) + '</p><!-- /wp:paragraph -->'

def heading(text):
    return '<!-- wp:heading --><h2 class="wp-block-heading">' + html.escape(text) + '</h2><!-- /wp:heading -->'

def section_content(sections):
    result = ''
    for section in sections:
        result += heading(section['heading'])
        pairs=[]
        def flush():
            nonlocal result,pairs
            if pairs:
                result += '<!-- wp:table --><figure class="wp-block-table"><table><tbody>'
                for label,value in pairs: result += '<tr><th scope="row">'+html.escape(label)+'</th><td><bdi>'+html.escape(value)+'</bdi></td></tr>'
                result += '</tbody></table></figure><!-- /wp:table -->';pairs=[]
        for text in section.get('paragraphs', []):
            parts=re.split('[:：]',text,maxsplit=1)
            if len(parts)==2 and 0<len(parts[0])<65 and 0<len(parts[1])<240:
                pairs.append(parts)
            else:
                flush();result += paragraph(text)
        flush()
    return result

class Page(HTMLParser):
    def __init__(self):
        super().__init__(); self.main = False; self.skip = 0; self.parts = []; self.images = []; self.description = ''; self.urls = []
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'main': self.main = True
        if tag in ['script', 'style']: self.skip += 1
        if tag == 'meta' and a.get('name') == 'description': self.description = a.get('content', '')
        if tag == 'a' and a.get('href'): self.urls.append(a['href'])
        if tag == 'img' and a.get('src'): self.images.append(a['src'])
    def handle_endtag(self, tag):
        if tag == 'main': self.main = False
        if tag in ['script', 'style']: self.skip = max(0, self.skip - 1)
    def handle_data(self, text):
        if self.main and not self.skip and text.strip(): self.parts.append(text.strip())

def fetch(url):
    request = urllib.request.Request(url, headers={'User-Agent': 'ChinaMudLogging-Migration-Audit/1.0'})
    with urllib.request.urlopen(request, timeout=35) as response:
        return response.status, response.geturl(), response.read().decode('utf-8')

def build(output, live=False):
    output.mkdir(parents=True, exist_ok=True)
    source_digest = hashlib.sha256(b''.join((ROOT/'src/data'/name).read_bytes() for name in ['products.json','articles.json','categories.json'])).hexdigest()
    products = json.loads((ROOT/'src/data/products.json').read_text())
    articles = json.loads((ROOT/'src/data/articles.json').read_text())
    categories = json.loads((ROOT/'src/data/categories.json').read_text())
    strings = json.loads((ROOT/'wordpress/plugin/cml-core/strings.json').read_text())
    page_copy = json.loads((ROOT/'wordpress/migration/page-copy.json').read_text())
    category_names = json.loads((ROOT/'wordpress/migration/category-names.json').read_text())
    priority = json.loads((ROOT/'wordpress/migration/priority-localizations.json').read_text())
    source_dates = json.loads((ROOT/'wordpress/migration/source-publication-dates.json').read_text())
    source_galleries = json.loads((ROOT/'wordpress/migration/source-galleries.json').read_text())
    translations_file = ROOT/'wordpress/migration/product-translations.json'
    translations = json.loads(translations_file.read_text()) if translations_file.exists() else {}
    selected = []
    for category in categories:
        candidates = [p for p in products if p['category']['slug'] == category['slug']]
        candidates.sort(key=lambda p: (-sum(len(x) for s in p['sections'] for x in s['paragraphs']), p['slug']))
        selected += [p['id'] for p in candidates[:2]]
    records = []; archive = {'products': products, 'articles': articles, 'pages': {}}
    original = ROOT.parent/'chinamudlogging.com/.source-private/posts.jsonl'
    if original.exists():
        archive['originalPublicWordPressRecords'] = [
            {key: row.get(key) for key in ['ID','post_date','post_name','post_type','post_title','post_parent','post_content']}
            for line in original.read_text().splitlines() if line.strip().startswith('{')
            for row in [json.loads(line)] if row.get('post_type') in ['page','post','product']
        ]
        raw_directory = output/'original-wordpress-extraction'; raw_directory.mkdir(exist_ok=True)
        for name in ['posts.jsonl','attachments.jsonl','postmeta.jsonl','terms.jsonl','relationships.jsonl','media-files.txt']:
            raw_file = original.parent/name
            if raw_file.exists(): shutil.copyfile(raw_file,raw_directory/name)
        archived_fonts=[]
        attachments=[json.loads(line) for line in (original.parent/'attachments.jsonl').read_text().splitlines() if line.lstrip().startswith('{')]
        for attachment in attachments:
            if not attachment['attached_file'].lower().endswith('.ttf'):continue
            source=ROOT/'public/images/source'/attachment['attached_file'];target=output/'retired-theme-fonts'/attachment['attached_file'];target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,target)
            archived_fonts.append({'sourceAttachmentId':attachment['ID'],'sourcePath':'/wp-content/uploads/'+attachment['attached_file'],'sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'disposition':'private archive; unused legacy theme font'})
        archive['retiredThemeFonts']=archived_fonts
    assets = set()
    def record(source_id, lang, typ, title, slug, content, description, url, **extra):
        row = dict(sourceId=source_id, language=lang, type=typ, title=title, slug=slug,
                   content=content, description=description, sourceUrl=url, review='source-preserved' if lang == 'en' else 'pending', **extra)
        records.append(row); return row
    for p in products:
        body = paragraph('Source catalogue record. The configuration and supplier statements below are retained for reference and are confirmed for the actual quotation.') + section_content(p['sections'])
        gallery=source_galleries.get(str(p['id']),[])
        if gallery:
            body += heading('Source product gallery') + '<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default">'
            for index,image in enumerate(gallery):
                assets.add(image['path'])
                body += '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="'+html.escape(image['path'])+'" alt="'+html.escape(p['title']+' — source gallery '+str(index+1))+'"/></figure><!-- /wp:image -->'
            body += '</figure><!-- /wp:gallery -->'
        if p.get('downloads'):
            body += heading('Downloads')
            for download in p['downloads']:
                href = download['href']; assets.add(href)
                body += '<!-- wp:paragraph --><p><a href="' + html.escape(href) + '">' + html.escape(download['label']) + '</a></p><!-- /wp:paragraph -->'
        record('product:' + str(p['id']), 'en', 'cml_product', p['title'], p['slug'], body, p['summary'], p['legacyUrl'],
               image=p['image'], category=p['category']['slug'], publishedAt=source_dates.get('product:'+str(p['id']),''), fields={'sku': p.get('sku', ''), 'weight': p.get('weight', ''), 'specifications': source_specifications(p)})
        assets.add(p['image'])
        if p['id'] in selected:
            for lang in LANGS[1:]:
                selection = priority['products'][str(p['id'])]
                translation = selection['translations'][lang]
                localized_copy = priority['copy'][lang]
                source_text = p['title'] + p['summary'] + ' '.join(t for s in p['sections'] for t in s['paragraphs'])
                normalize = lambda value: ''.join(value.split())
                for spec in selection['specs']:
                    if normalize(spec['value']) not in normalize(source_text): raise RuntimeError('Priority specification changed in source: '+str(p['id'])+' '+spec['label'])
                curated = paragraph(translation['summary']) + heading(localized_copy['heading'])
                curated += '<!-- wp:table --><figure class="wp-block-table"><table><tbody>'
                for spec in selection['specs']:
                    value = ('<' if spec['label']=='conductivity_reference' else '') + spec['value']
                    curated += '<tr><th scope="row">'+html.escape(priority['labels'][lang][spec['label']])+'</th><td><bdi>'+html.escape(value)+'</bdi></td></tr>'
                curated += '</tbody></table></figure><!-- /wp:table -->'
                curated += heading(localized_copy['selection_heading']) + paragraph(localized_copy['selection']) + paragraph(localized_copy['reference_note'])
                curated += '<!-- wp:paragraph --><p><a hreflang="en" href="'+html.escape(p['legacyUrl'])+'">'+html.escape(localized_copy['reference'])+'</a></p><!-- /wp:paragraph -->'
                row = record('product:' + str(p['id']), lang, 'cml_product', translation['title'], p['slug'] + '-' + lang,
                             curated, translation['summary'], p['legacyUrl'],
                             image=p['image'], category=p['category']['slug'], fields={'sku': p.get('sku', ''), 'weight': p.get('weight', ''), 'specifications': [specification(s['label'],s['value']) for s in selection['specs']]})
                row['review'] = priority['review']
                row['localizationMode'] = priority['mode']
                row['sourceParameters'] = [spec['value'] for spec in selection['specs']]
                # Reference downloads are kept with their original language labels.
                if p.get('downloads'): row['content'] += body[body.find(heading('Downloads')):]
    for article in articles:
        row = record('article:' + str(article['id']), 'en', 'post', article['title'], article['slug'],
                     ''.join(paragraph(x) for x in article['paragraphs']), article['summary'], article['legacyUrl'],
                     image=article['image'], publishedAt=source_dates.get('article:'+str(article['id']),article['publishedAt'][:10]))
        assets.add(article['image'])
        correction = page_copy.get('articleCorrections', {}).get(str(article['id']))
        if correction:
            row['content'] = ''.join(paragraph(x) for x in correction['paragraphs'])
            for reference in correction.get('references',[]):
                row['content'] += '<!-- wp:paragraph --><p><a href="'+html.escape(reference['url'])+'">'+html.escape(reference['title'])+'</a></p><!-- /wp:paragraph -->'
            row['description'] = correction['description']; row['review'] = 'corrected-see-ledger'
    hero = '/images/source/2021/08/services.png'; assets.add(hero)
    page_paths = {'home':'/', 'products':'/product/', 'applications':'/applications/', 'services':'/services/', 'about':'/about-us/', 'insights':'/blog/', 'contact':'/contact-us/', 'privacy':'/privacy/'}
    source_paths = {key: ROOT/'dist'/path.strip('/')/'index.html' if path != '/' else ROOT/'dist/index.html' for key,path in page_paths.items()}
    for key,path in page_paths.items():
        parser = Page()
        if source_paths[key].exists(): parser.feed(source_paths[key].read_text())
        archive['pages'][path] = {'text': parser.parts, 'description': parser.description, 'images':parser.images}
        for image in parser.images:
            if image.startswith('/images/'): assets.add(image)
        for lang in LANGS:
            if key == 'insights' and lang != 'en': continue
            slug = 'home' if key == 'home' else path.strip('/')
            if lang != 'en': slug += '-' + lang
            title = page_copy[lang]['site_title'] if key == 'home' else strings[lang][key]
            if key == 'home': content = '<!-- wp:cml/home /-->'
            elif key == 'products': content = '<!-- wp:cml/catalogue /-->'
            elif key == 'applications': content = '<!-- wp:cml/applications /-->'
            elif key == 'contact': content = '<!-- wp:cml/inquiry /-->'
            elif key == 'insights': content = ''
            else: content = ''.join(paragraph(x) for index,x in enumerate(page_copy[lang][key]) if key!='privacy' or index!=1)
            if key == 'privacy': content += '<!-- wp:cml/measurement /-->'
            row = record('page:' + key, lang, 'page', title, slug, content,
                         page_copy[lang]['intro'] if key in ['about', 'services', 'privacy'] else strings[lang]['intro'], path, pageKey=key, image=hero if key=='home' else '')
            if lang == 'en' and key in ['about', 'services', 'privacy']: row['review'] = 'rewritten-source-archived'
            if lang != 'en': row['review'] = 'approved'
    # Preserve every public image and document, including currently unlinked original media.
    for path in (ROOT/'public').rglob('*'):
        if path.is_file() and path.suffix.lower() in ['.jpg','.jpeg','.png','.gif','.webp','.pdf']: assets.add('/'+str(path.relative_to(ROOT/'public')))
    media=[]
    for asset in sorted(assets):
        source = (ROOT/'public'/asset.lstrip('/')).resolve()
        if not source.is_relative_to((ROOT/'public').resolve()) or not source.is_file(): raise RuntimeError('Missing media: '+asset)
        destination = output/'assets'/asset.lstrip('/'); destination.parent.mkdir(parents=True,exist_ok=True)
        shutil.copyfile(source,destination)
        media.append({'path':asset,'sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'bytes':source.stat().st_size})
    redirects = {r['source']:r['destination'] for r in json.loads((ROOT/'vercel.json').read_text())['redirects'] if not r.get('has')}
    bundle=dict(schemaVersion=1, generatedAt=datetime.now(timezone.utc).isoformat(), heroImage=hero, languages=LANGS,
                selectedProductIds=selected, categories=[dict(slug=c['slug'],translations={lang:category_names[lang][c['slug']] for lang in LANGS}) for c in categories],
                records=records, media=media, redirects=redirects)
    (output/'bundle.json').write_text(json.dumps(bundle,ensure_ascii=False,indent=2)+'\n')
    (output/'source-archive.json').write_text(json.dumps(archive,ensure_ascii=False,indent=2)+'\n')
    with (ROOT/'wordpress/migration/content-ledger.csv').open('w') as stream:
        writer=csv.writer(stream,lineterminator="\n");writer.writerow(['source_id','language','type','source_url','target_slug','review','publication_rule','media_paths','source_published_at','localization_mode'])
        for r in records:
            media_paths=sorted({r.get('image','')} | {x for x in assets if '"'+x+'"' in r['content']})
            writer.writerow([r['sourceId'],r['language'],r['type'],r['sourceUrl'],r['slug'],r['review'],'English baseline' if r['language']=='en' else 'explicit --publish-reviewed required',';'.join(x for x in media_paths if x),r.get('publishedAt',''),r.get('localizationMode','full-source-record')])
        for c in categories:
            for lang in LANGS: writer.writerow(['family:'+c['slug'],lang,'cml_family','/product-category/'+c['slug']+'/',c['slug']+('' if lang=='en' else '-'+lang),'source-preserved' if lang=='en' else 'approved','linked to published products','','','localized-family'])
    with (ROOT/'wordpress/migration/url-map.csv').open('w') as stream:
        writer=csv.writer(stream,lineterminator="\n");writer.writerow(['source_path','target_path','action'])
        for r in records:
            if r['language']=='en': writer.writerow([r['sourceUrl'],r['sourceUrl'],'preserve'])
        for source,target in redirects.items(): writer.writerow([source,target,'301'])
        for c in categories: writer.writerow(['/product-category/'+c['slug']+'/', '/product-category/'+c['slug']+'/', 'preserve'])
    report={'localProducts':len(products),'localArticles':len(articles),'families':len(categories),'media':len(media),'records':len(records),
            'selectedProductIds':selected,'translationProducts':sum(r['type']=='cml_product' and r['language']!='en' for r in records),
            'liveAudit':'not-run','baselineOnly':True,'sourceDataSha256':source_digest,'releaseReport':'../docs/验收报告.md'}
    previous_file = ROOT/'wordpress/migration/baseline-report.json'
    previous = json.loads(previous_file.read_text()) if previous_file.exists() else {}
    if not live and previous.get('sourceDataSha256') == source_digest and previous.get('liveAudit') in ['passed','frozen-passed']:
        for key in ['liveProductCount','missingOnline','missingLocal','existingBrokenPaths','checkedUrls','frozenAt']:
            if key in previous: report[key] = previous[key]
        report['liveAudit'] = 'frozen-passed'
    if live:
        _,_,sitemap=fetch(BASE+'/sitemap.xml')
        sitemap_root = ET.fromstring(sitemap)
        if not sitemap_root.tag.endswith('urlset'): raise RuntimeError('Source URL set is no longer served here. Use the frozen source audit and WordPress acceptance scripts after cutover.')
        paths=sorted({urlparse(x.text).path for x in sitemap_root.iter() if x.tag.endswith('}loc') and x.text})
        def inspect(path):
            try:
                status,url,body=fetch(BASE+path); parser=Page();parser.feed(body)
                return {'path':path,'status':status,'finalUrl':url,'sha256':hashlib.sha256(body.encode()).hexdigest(),'productLinks':sorted(set(x for x in parser.urls if re.match(r'^/product/[^/]+/$',x)))}
            except Exception as error: return {'path':path,'error':str(error)}
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool: results=list(pool.map(inspect,paths))
        expected={p['legacyUrl'] for p in products}
        catalog=next((x for x in results if x['path']=='/product/'),{})
        found=set(catalog.get('productLinks',[]))
        failures=[x['path'] for x in results if x.get('status')!=200]
        report.update(liveAudit='passed' if expected==found and not failures else 'needs-review',
                      liveProductCount=len(found),missingOnline=sorted(expected-found),missingLocal=sorted(found-expected),
                      existingBrokenPaths=failures,checkedUrls=len(results))
        (output/'live-url-audit.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
    (ROOT/'wordpress/migration/baseline-report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps(report,ensure_ascii=False))

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--output',type=Path,default=Path.home()/'.cache/chinamudlogging-wordpress/bundle');parser.add_argument('--live',action='store_true');args=parser.parse_args()
    build(args.output,args.live)
