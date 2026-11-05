#!/usr/bin/env python3
"""Join retained GSC, public HTTP and CMS evidence; no network, mutations or ranking promises."""
import argparse
import collections
import csv
import datetime as dt
import hashlib
import json
import pathlib
import re
import urllib.parse
import urllib.robotparser

def instant(value):
    if not value:return None
    value=value.replace(' ', 'T').replace('Z','+00:00')
    result=dt.datetime.fromisoformat(value)
    return result.replace(tzinfo=dt.timezone.utc) if result.tzinfo is None else result

def analyse(url, baseline, google, http, cms, lastmod, robots_allowed, incoming, pages):
    state=(google.get('result') or {}).get('indexStatusResult') or {}
    failures=[]
    if not http or http.get('error'):failures.append('live_http_unverified')
    else:
        if http.get('status')!=200:failures.append('http_'+str(http.get('status')))
        if http.get('finalUrl')!=url:failures.append('redirected_canonical_destination')
        if http.get('canonical')!=[url]:failures.append('live_canonical')
        if len(http.get('h1',[]))!=1:failures.append('heading_count')
        directives=' '.join(http.get('robots',[]))+' '+http.get('headerRobots','')
        if re.search(r'\b(?:noindex|none)\b',directives,re.I):failures.append('live_noindex')
        if not http.get('lang','').lower().startswith(baseline['language']):failures.append('live_language')
    if not robots_allowed:failures.append('robots_disallow')
    crawl=instant(state.get('lastCrawlTime'))
    modified=instant(lastmod or cms.get('modifiedAt'))
    published=instant(cms.get('publishedAt'))
    older=bool(crawl and modified and crawl<modified)
    flags=[]
    article=(http or {}).get('articleText','')
    short=cms.get('type')=='post' and len(article)<400
    if short:flags.append('short_legacy_article_review_not_proven_google_cause')
    cjk=len(re.findall('[\u4e00-\u9fff]',article))
    if baseline['language']=='en' and cjk>=30:flags.append('retained_source_language_fields_review')
    utility=bool(re.search(r'/(?:contact-us(?:-[a-z]{2})?|privacy(?:-[a-z]{2})?)/$',url))
    if failures:category='technical_verification_required' if failures==['live_http_unverified'] else 'technical_issue'
    elif google.get('error') or not state:category='google_status_unavailable'
    elif state.get('verdict')=='PASS':category='indexed'
    elif short:category='legacy_content_review'
    elif utility:category='utility_page_normal_selection'
    elif state.get('coverageState')=='URL is unknown to Google' and published and published>=instant('2026-11-04T07:20:33.125473Z'):category='new_publication_wait'
    elif older:category='await_updated_content_crawl'
    elif state.get('coverageState')=='Discovered - currently not indexed':category='discovered_wait'
    elif state.get('coverageState')=='URL is unknown to Google':category='legacy_discovery_wait'
    else:category='google_selection_wait'
    return {'url':url,'language':baseline['language'],'baselineCoverage':baseline['coverage'],
        'googleCoverage':state.get('coverageState',google.get('error','unavailable')),'googleVerdict':state.get('verdict',''),
        'googleLastCrawl':state.get('lastCrawlTime',''),'googleCanonical':state.get('googleCanonical',''),
        'publishedAt':cms.get('publishedAt',''),'lastmod':lastmod or cms.get('modifiedAt',''),
        'crawlPredatesCurrentContent':older,'httpStatus':(http or {}).get('status'),
        'liveCanonical':(http or {}).get('canonical',[]),'robotsAllowed':robots_allowed,
        'technicalIssues':failures,'category':category,'contentFlags':flags,'articleCharacters':len(article),
        'sourceChineseCharacters':cjk,'observedIncomingSources':sorted(incoming),'cataloguePages':sorted(pages),
        'discoveryRepair':'self_canonical_catalogue_pagination' if any('catalog_page=' in p for p in pages) else '',
        'nextCheck':'72h then 7d; inspect fresh Google snapshot and last crawl, not just HTTP status'}

def build(directory):
    baseline=json.loads((directory/'baseline-266.json').read_text())
    inventory=json.loads((directory/'inventory.json').read_text())
    sitemap={x['url']:x.get('lastmod') for x in inventory['urls']}
    google={}
    for file in sorted(directory.glob('inspection-*.json')):
        for row in json.loads(file.read_text()).get('results',[]):google[row['url']]=row
    captures=json.loads((directory/'http-results.json').read_text())
    http={x['url']:x for x in captures['results']}
    cms={x['url']:x for x in json.loads((directory/'cms-public-content.json').read_text())['posts']}
    robot=urllib.robotparser.RobotFileParser();robot.parse(inventory['robots']['body'].splitlines())
    incoming=collections.defaultdict(set)
    for source,row in http.items():
        if row.get('status')!=200:continue
        for link in row.get('links',[]):
            if 'nofollow' in link.get('rel','').lower().split():continue
            target=urllib.parse.urldefrag(urllib.parse.urljoin(source,link['href']))[0]
            if target!=source:incoming[target].add(source)
    pages=collections.defaultdict(set)
    pagefile=directory/'pagination-discovery.json'
    if pagefile.exists():
        for page in json.loads(pagefile.read_text())['rows']:
            if page['status']!=200:continue
            for target in page['products']:pages[target].add(page['url']);incoming[target].add(page['url'])
    result=[]
    for row in baseline:
        url=row['url'];item=analyse(url,row,google.get(url,{}),http.get(url),cms.get(url,{}),sitemap.get(url),robot.can_fetch('Googlebot',url),incoming[url],pages[url]);item['inCapturedSitemap']=url in sitemap;result.append(item)
    summary={'assembledAt':dt.datetime.now(dt.timezone.utc).isoformat(),'baselineTargets':len(baseline),'rows':len(result),
        'categories':dict(collections.Counter(r['category'] for r in result)),
        'googleCoverage':dict(collections.Counter(r['googleCoverage'] for r in result)),
        'technicalFailures':sum(bool(r['technicalIssues']) for r in result),
        'withObservedIncomingSource':sum(bool(r['observedIncomingSources']) for r in result),
        'withCataloguePage':sum(bool(r['cataloguePages']) for r in result),
        'sourceLanguageReviewFlags':sum(bool(r['sourceChineseCharacters']>=30 and r['language']=='en') for r in result),
        'retainedHttpFailures':sum(bool(r.get('error')) for r in captures['results']),
        'limitations':['Google existing-index snapshot, not live Googlebot crawl.','Observed inbound links are a lower bound of the captured site graph, not all internet backlinks.','Content flags do not prove Googles indexing decision.','An indexable response is not a guarantee of indexing.']}
    for name,data in [('diagnosis-ledger.json',result),('diagnosis-summary.json',summary)]:
        path=directory/name;path.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n');path.chmod(0o600)
    path=directory/'diagnosis-ledger.csv'
    with path.open('w')as file:
        writer=csv.DictWriter(file,fieldnames=list(result[0]));writer.writeheader()
        for row in result:writer.writerow({k:json.dumps(v,ensure_ascii=False) if isinstance(v,(list,dict)) else v for k,v in row.items()})
    path.chmod(0o600)
    return summary

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('directory',type=pathlib.Path);args=p.parse_args()
    print(json.dumps(build(args.directory),ensure_ascii=False,indent=2))
